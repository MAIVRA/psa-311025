<?php
// pranata/modules/hr/includes/payroll_engine.php

/**
 * Menjalankan kalkulasi payroll untuk satu karyawan pada periode tertentu.
 *
 * @param mysqli $conn Koneksi database
 * @param int $karyawan_id ID karyawan
 * @param int $run_tahun Tahun periode
 * @param int $run_bulan Bulan periode (1-12)
 * @param int $admin_id ID admin/HR yang menjalankan
 * @param string $metode_pph_pilihan 'TER' atau 'REGULER'
 * @param bool $include_thr [PERUBAHAN] Apakah menyertakan THR dalam perhitungan
 * @return array ['success' => true] atau ['success' => false, 'message' => '...']
 */
function runPayrollCalculation($conn, $karyawan_id, $run_tahun, $run_bulan, $admin_id, $metode_pph_pilihan, $include_thr = false) {
    
    // 1. Inisialisasi
    $gaji_pokok_final = 0;
    $total_tunjangan_tetap = 0;
    $total_tunjangan_tidak_tetap = 0;
    $total_tunjangan_lain = 0;
    $total_gross_income = 0;
    $total_potongan_bpjs = 0;
    $total_potongan_pph21 = 0;
    $total_potongan_lainnya = 0;
    $take_home_pay = 0;
    
    // Variabel detail (untuk modal)
    $detail_pendapatan = [];
    $detail_potongan = [];
    $detail_pph21 = [];
    
    // === [PERUBAHAN BARU: Inisialisasi THR] ===
    $pendapatan_thr = 0;
    $thr_history_id_to_update = null; // Untuk menandai status 'Paid' nanti
    // === [AKHIR PERUBAHAN] ===

    // Mulai Transaction
    $conn->begin_transaction();
    
    try {
        // 2. Ambil Data Master Gaji & User
        $stmt_master = $conn->prepare("SELECT u.nama_lengkap, u.tanggal_masuk, pmg.* FROM payroll_master_gaji pmg
                                      JOIN users u ON pmg.user_id = u.id
                                      WHERE pmg.user_id = ?");
        if (!$stmt_master) { throw new Exception("Gagal prepare master gaji: " . $conn->error); }
        $stmt_master->bind_param("i", $karyawan_id);
        $stmt_master->execute();
        $result_master = $stmt_master->get_result();
        if ($result_master->num_rows == 0) {
            throw new Exception("Master gaji tidak ditemukan.");
        }
        $master_gaji = $result_master->fetch_assoc();
        $stmt_master->close();

        // 3. Ambil Pengaturan Payroll
        $stmt_settings = $conn->prepare("SELECT setting_key, setting_value FROM payroll_settings");
        if (!$stmt_settings) { throw new Exception("Gagal prepare payroll settings: " . $conn->error); }
        $stmt_settings->execute();
        $result_settings = $stmt_settings->get_result();
        $settings = [];
        while ($row = $result_settings->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        $stmt_settings->close();
        
        // 4. Tentukan Periode & Hari Kerja
        $tgl_mulai_periode = date('Y-m-d', strtotime("$run_tahun-$run_bulan-" . $settings['periode_mulai'] . " -1 month"));
        $tgl_selesai_periode = date('Y-m-d', strtotime("$run_tahun-$run_bulan-" . $settings['periode_akhir']));
        $jumlah_hari_kerja_periode = (int)($settings['jumlah_hari_kerja'] ?? 23);

        // 5. Hitung Kehadiran Aktual (WFO, WFH, Sakit)
        $stmt_presensi = $conn->prepare(
            "SELECT COUNT(id) as total 
             FROM presensi 
             WHERE user_id = ? 
               AND tanggal_presensi BETWEEN ? AND ? 
               AND status_kerja IN ('WFO', 'WFH', 'Sakit', 'Dinas')" // Dinas dianggap masuk
        );
        if (!$stmt_presensi) { throw new Exception("Gagal prepare presensi: " . $conn->error); }
        $stmt_presensi->bind_param("iss", $karyawan_id, $tgl_mulai_periode, $tgl_selesai_periode);
        $stmt_presensi->execute();
        $jumlah_kehadiran_aktual = (int)$stmt_presensi->get_result()->fetch_assoc()['total'];
        $stmt_presensi->close();
        
        // 5b. Hitung Kehadiran WFO (untuk Tunj. Transport)
        $stmt_wfo = $conn->prepare(
             "SELECT COUNT(id) as total FROM presensi 
              WHERE user_id = ? AND tanggal_presensi BETWEEN ? AND ? AND status_kerja = 'WFO'"
        );
        if (!$stmt_wfo) { throw new Exception("Gagal prepare presensi WFO: " . $conn->error); }
        $stmt_wfo->bind_param("iss", $karyawan_id, $tgl_mulai_periode, $tgl_selesai_periode);
        $stmt_wfo->execute();
        $jumlah_kehadiran_wfo = (int)$stmt_wfo->get_result()->fetch_assoc()['total'];
        $stmt_wfo->close();

        // === [PERUBAHAN BARU: Cek & Ambil Data THR] ===
        if ($include_thr) {
            $stmt_thr = $conn->prepare("SELECT id, nominal_thr, basis_perhitungan_desc FROM payroll_thr_history 
                                        WHERE user_id = ? AND tahun_thr = ? 
                                        AND status IN ('Calculated', 'Approved') 
                                        LIMIT 1");
            if (!$stmt_thr) {
                throw new Exception("Gagal prepare cek THR: " . $conn->error);
            }
            $stmt_thr->bind_param("ii", $karyawan_id, $run_tahun);
            $stmt_thr->execute();
            $result_thr = $stmt_thr->get_result();
            if ($row_thr = $result_thr->fetch_assoc()) {
                $pendapatan_thr = $row_thr['nominal_thr'];
                $thr_history_id_to_update = $row_thr['id']; // Simpan ID untuk di-update nanti
            }
            $stmt_thr->close();
        }
        // === [AKHIR PERUBAHAN] ===

        // 6. Hitung Gaji Pokok (Pro-rata jika tidak hadir penuh)
        $gaji_pokok_master = $master_gaji['gaji_pokok'];
        $gaji_pokok_final = $gaji_pokok_master; // Asumsi penuh
        $detail_pendapatan[] = ['komponen' => 'Gaji Pokok', 'deskripsi' => 'Gaji Pokok Master', 'jumlah' => $gaji_pokok_master];
        
        $hari_absen = $jumlah_hari_kerja_periode - $jumlah_kehadiran_aktual;
        if ($hari_absen > 0) {
            // Hitung pro-rata berdasarkan kehadiran
            $gaji_pokok_final = ($jumlah_kehadiran_aktual / $jumlah_hari_kerja_periode) * $gaji_pokok_master;
            $potongan_prorata = $gaji_pokok_master - $gaji_pokok_final;
            
            $detail_pendapatan[] = [
                'komponen' => 'Potongan Pro-rata', 
                'deskripsi' => "Absen/Sakit non-note: $hari_absen hari. ($jumlah_kehadiran_aktual / $jumlah_hari_kerja_periode)", 
                'jumlah' => -$potongan_prorata // Negatif
            ];
        }

        // 7. Hitung Tunjangan Tetap (Selalu full, tidak pro-rata)
        $pendapatan_tetap = [
            ['komponen' => 'Tunjangan Jabatan', 'jumlah' => $master_gaji['tunj_jabatan']],
            ['komponen' => 'Tunjangan Kesehatan (Asuransi)', 'jumlah' => $master_gaji['tunj_kesehatan']],
        ];
        foreach ($pendapatan_tetap as $tunj) {
            if ($tunj['jumlah'] > 0) {
                $total_tunjangan_tetap += $tunj['jumlah'];
                $detail_pendapatan[] = [
                    'komponen' => $tunj['komponen'],
                    'deskripsi' => 'Tunjangan Tetap',
                    'jumlah' => $tunj['jumlah']
                ];
            }
        }
        
        // 8. Hitung Tunjangan Tidak Tetap (Berdasarkan kehadiran)
        $pendapatan_tidak_tetap = [
            ['komponen' => 'Tunjangan Makan', 'basis_hari' => $jumlah_kehadiran_aktual, 'nominal_harian' => $master_gaji['tunj_makan']],
            ['komponen' => 'Tunjangan Transportasi', 'basis_hari' => $jumlah_kehadiran_wfo, 'nominal_harian' => $master_gaji['tunj_transport']],
            ['komponen' => 'Tunjangan Rumah', 'basis_hari' => 1, 'nominal_harian' => $master_gaji['tunj_rumah']], // Bulanan
            ['komponen' => 'Tunjangan Pendidikan', 'basis_hari' => 1, 'nominal_harian' => $master_gaji['tunj_pendidikan']], // Bulanan
        ];
        foreach ($pendapatan_tidak_tetap as $tunj) {
            $jumlah_tunjangan = $tunj['basis_hari'] * $tunj['nominal_harian'];
            if ($jumlah_tunjangan > 0) {
                $total_tunjangan_tidak_tetap += $jumlah_tunjangan;
                $deskripsi = ($tunj['basis_hari'] > 1) ? "{$tunj['basis_hari']} hari x " . number_format($tunj['nominal_harian'], 0, ',', '.') : 'Tunjangan Tidak Tetap (Bulanan)';
                $detail_pendapatan[] = [
                    'komponen' => $tunj['komponen'],
                    'deskripsi' => $deskripsi,
                    'jumlah' => $jumlah_tunjangan
                ];
            }
        }

        // 9. Hitung Tunjangan Lain (Non-THP/Fasilitas + THR)
        $pendapatan_lain = [
             ['komponen' => 'Tunjangan Komunikasi (Fasilitas)', 'tipe_tunjangan' => 'LAIN', 'jumlah' => $master_gaji['tunj_komunikasi']],
             // === [PERUBAHAN BARU: Tambahkan THR ke array] ===
             ['komponen' => 'Tunjangan Hari Raya (THR)', 'tipe_tunjangan' => 'THR', 'jumlah' => $pendapatan_thr],
             // === [AKHIR PERUBAHAN] ===
        ];
        foreach ($pendapatan_lain as $tunj) {
             if ($tunj['jumlah'] > 0) {
                $total_tunjangan_lain += $tunj['jumlah'];
                $deskripsi = ($tunj['tipe_tunjangan'] == 'LAIN') ? 'Tunjangan Lainnya (Non-THP/Reimburse)' : "THR Tahun $run_tahun";
                $detail_pendapatan[] = [
                    'komponen' => $tunj['komponen'],
                    'deskripsi' => $deskripsi,
                    'jumlah' => $tunj['jumlah']
                ];
            }
        }
        
        // 10. Hitung Gross Income
        $total_gross_income = $gaji_pokok_final + $total_tunjangan_tetap + $total_tunjangan_tidak_tetap + $total_tunjangan_lain;
        
        // 11. Hitung Potongan BPJS & Biaya Jabatan (Dasar: Gaji Pokok + Tunjangan Tetap)
        $dasar_upah_bpjs_kesehatan = $gaji_pokok_final + $total_tunjangan_tetap;
        $dasar_upah_bpjs_tk = $gaji_pokok_final + $total_tunjangan_tetap;
        
        // Cek UMP/UMK (Assume UMK Surabaya 2025: 4.8jt)
        // Note: Logic UMK/Batas Atas/Bawah BPJS bisa disempurnakan
        
        // Batas Atas BPJS Kesehatan
        $max_upah_bpjs_kes = (float)($settings['bpjs_kes_max_upah'] ?? 12000000);
        if ($dasar_upah_bpjs_kesehatan > $max_upah_bpjs_kes) $dasar_upah_bpjs_kesehatan = $max_upah_bpjs_kes;
        
        // Batas Atas Jaminan Pensiun
        $max_upah_jp = (float)($settings['jp_max_upah'] ?? 6000000);
        $dasar_upah_jp = ($dasar_upah_bpjs_tk > $max_upah_jp) ? $max_upah_jp : $dasar_upah_bpjs_tk;
        
        // Potongan Karyawan
        $pot_bpjs_kes_karyawan_pct = (float)($settings['bpjs_kes_karyawan_pct'] ?? 1);
        $pot_jht_karyawan_pct = (float)($settings['jht_karyawan_pct'] ?? 2);
        $pot_jp_karyawan_pct = (float)($settings['jp_karyawan_pct'] ?? 1); // Di data 0
        
        $pot_bpjs_kes_karyawan = 0;
        if($master_gaji['pot_bpjs_kesehatan']) {
            $pot_bpjs_kes_karyawan = ($pot_bpjs_kes_karyawan_pct / 100) * $dasar_upah_bpjs_kesehatan;
            $detail_potongan[] = ['komponen' => 'Pot. BPJS Kesehatan (Karyawan)', 'deskripsi' => "$pot_bpjs_kes_karyawan_pct% x " . number_format($dasar_upah_bpjs_kesehatan, 0, ',', '.'), 'jumlah' => $pot_bpjs_kes_karyawan];
        }
        
        $pot_jht_karyawan = 0;
        $pot_jp_karyawan = 0;
        if($master_gaji['pot_bpjs_ketenagakerjaan']) {
            $pot_jht_karyawan = ($pot_jht_karyawan_pct / 100) * $dasar_upah_bpjs_tk;
            $pot_jp_karyawan = ($pot_jp_karyawan_pct / 100) * $dasar_upah_jp;
            $detail_potongan[] = ['komponen' => 'Pot. JHT (Karyawan)', 'deskripsi' => "$pot_jht_karyawan_pct% x " . number_format($dasar_upah_bpjs_tk, 0, ',', '.'), 'jumlah' => $pot_jht_karyawan];
            $detail_potongan[] = ['komponen' => 'Pot. Jaminan Pensiun (Karyawan)', 'deskripsi' => "$pot_jp_karyawan_pct% x " . number_format($dasar_upah_jp, 0, ',', '.'), 'jumlah' => $pot_jp_karyawan];
        }
        
        $total_potongan_bpjs = $pot_bpjs_kes_karyawan + $pot_jht_karyawan + $pot_jp_karyawan;

        // Tunjangan BPJS Perusahaan (Untuk PPh 21)
        $tunj_bpjs_kes_perusahaan_pct = (float)($settings['bpjs_kes_perusahaan_pct'] ?? 4);
        $tunj_jkk_perusahaan_pct = (float)($settings['jkk_perusahaan_pct'] ?? 0.24);
        $tunj_jkm_perusahaan_pct = (float)($settings['jkm_perusahaan_pct'] ?? 0.3);
        $tunj_jkp_perusahaan_pct = (float)($settings['jkp_perusahaan_pct'] ?? 0.46);
        
        $tunj_bpjs_kes_perusahaan = ($tunj_bpjs_kes_perusahaan_pct / 100) * $dasar_upah_bpjs_kesehatan;
        $tunj_jkk_perusahaan = ($tunj_jkk_perusahaan_pct / 100) * $dasar_upah_bpjs_tk;
        $tunj_jkm_perusahaan = ($tunj_jkm_perusahaan_pct / 100) * $dasar_upah_bpjs_tk;
        $tunj_jkp_perusahaan = ($tunj_jkp_perusahaan_pct / 100) * $dasar_upah_bpjs_tk;
        
        // Tunjangan BPJS yg masuk OBJEK PPH (JHT dan JP Perusahaan TIDAK TERMASUK)
        $tunj_bpjs_perusahaan_pph = $tunj_bpjs_kes_perusahaan + $tunj_jkk_perusahaan + $tunj_jkm_perusahaan;
        
        // Biaya Jabatan (Pengurang PPh)
        $biaya_jabatan_pct = 5;
        $biaya_jabatan_max_per_bulan = 500000;
        
        // === [PERUBAHAN BARU: Dasar PPh 21 Bruto menyertakan THR] ===
        $dasar_pph21_bruto = $gaji_pokok_final + $total_tunjangan_tetap + $total_tunjangan_tidak_tetap + $tunj_bpjs_perusahaan_pph + $pendapatan_thr;
        // === [AKHIR PERUBAHAN] ===
        
        $biaya_jabatan = ($biaya_jabatan_pct / 100) * $dasar_pph21_bruto;
        if ($biaya_jabatan > $biaya_jabatan_max_per_bulan) $biaya_jabatan = $biaya_jabatan_max_per_bulan;
        
        // 12. Hitung Potongan PPh 21
        if ($metode_pph_pilihan == 'TER' && $master_gaji['pot_pph']) {
            // Ambil Kategori TER
            $status_ptkp = $master_gaji['status_ptkp'] ?? 'TK/0';
            $kategori_ter = 'A'; // Default (TK/0 - TK/3, K/0)
            if (in_array($status_ptkp, ['K/1', 'K/2'])) $kategori_ter = 'B';
            if (in_array($status_ptkp, ['K/3'])) $kategori_ter = 'C';
            
            // Cari tarif TER
            $stmt_ter = $conn->prepare(
                "SELECT tarif_ter FROM payroll_settings_ter 
                 WHERE kategori = ? AND penghasilan_bruto_min <= ? AND penghasilan_bruto_max >= ? 
                 LIMIT 1"
            );
            if (!$stmt_ter) { throw new Exception("Gagal prepare TER: " . $conn->error); }
            $stmt_ter->bind_param("sdd", $kategori_ter, $dasar_pph21_bruto, $dasar_pph21_bruto);
            $stmt_ter->execute();
            $result_ter = $stmt_ter->get_result();
            $tarif_ter = 0;
            if ($row_ter = $result_ter->fetch_assoc()) {
                $tarif_ter = (float)$row_ter['tarif_ter'];
            }
            $stmt_ter->close();

            $pph21_ter_final = ($tarif_ter / 100) * $dasar_pph21_bruto;
            $total_potongan_pph21 = $pph21_ter_final;
            
            $detail_pph21[] = [
                'komponen' => 'Potongan PPh 21 (TER)',
                'deskripsi' => "Kategori $kategori_ter ($status_ptkp) - Tarif $tarif_ter% x " . number_format($dasar_pph21_bruto, 0, ',', '.'),
                'jumlah' => $pph21_ter_final
            ];

        } elseif ($metode_pph_pilihan == 'REGULER' && $master_gaji['pot_pph']) {
            // [TODO: Logika PPh 21 Metode Reguler (Pasal 17)]
            $total_potongan_pph21 = 0; 
            $detail_pph21[] = [
                'komponen' => 'Potongan PPh 21 (REGULER)',
                'deskripsi' => 'Metode Reguler (Pasal 17) belum diimplementasikan.',
                'jumlah' => 0
            ];
        }

        // 13. Ambil Potongan Lain (Manual)
        $stmt_pot_lain = $conn->prepare(
            "SELECT jenis_potongan, jumlah, keterangan 
             FROM payroll_potongan_lain 
             WHERE user_id = ? AND periode_tahun = ? AND periode_bulan = ?"
        );
        if (!$stmt_pot_lain) { throw new Exception("Gagal prepare potongan lain: " . $conn->error); }
        $stmt_pot_lain->bind_param("iii", $karyawan_id, $run_tahun, $run_bulan);
        $stmt_pot_lain->execute();
        $result_pot_lain = $stmt_pot_lain->get_result();
        while ($row_pot = $result_pot_lain->fetch_assoc()) {
            $total_potongan_lainnya += $row_pot['jumlah'];
            $detail_potongan[] = [
                'komponen' => $row_pot['jenis_potongan'],
                'deskripsi' => $row_pot['keterangan'] ?? 'Potongan manual',
                'jumlah' => $row_pot['jumlah']
            ];
        }
        $stmt_pot_lain->close();

        // 14. Hitung Take Home Pay
        $take_home_pay = $total_gross_income - $total_potongan_bpjs - $total_potongan_pph21 - $total_potongan_lainnya;
        
        // 15. Hapus Data Lama & Simpan Data Baru ke History
        
        // Hapus detail lama
        $stmt_del_detail = $conn->prepare("DELETE phd FROM payroll_history_detail phd 
                                          JOIN payroll_history ph ON phd.payroll_history_id = ph.id 
                                          WHERE ph.user_id = ? AND ph.periode_tahun = ? AND ph.periode_bulan = ?");
        if (!$stmt_del_detail) { throw new Exception("Gagal prepare delete detail lama: " . $conn->error); }
        $stmt_del_detail->bind_param("iii", $karyawan_id, $run_tahun, $run_bulan);
        $stmt_del_detail->execute();
        $stmt_del_detail->close();
        
        // Hapus master lama
        $stmt_del_hist = $conn->prepare("DELETE FROM payroll_history 
                                        WHERE user_id = ? AND periode_tahun = ? AND periode_bulan = ?");
        if (!$stmt_del_hist) { throw new Exception("Gagal prepare delete history lama: " . $conn->error); }
        $stmt_del_hist->bind_param("iii", $karyawan_id, $run_tahun, $run_bulan);
        $stmt_del_hist->execute();
        $stmt_del_hist->close();

        // Simpan Master History Baru
        $sql_insert_hist = "INSERT INTO payroll_history 
                            (user_id, periode_tahun, periode_bulan, tanggal_mulai_periode, tanggal_selesai_periode, 
                             jumlah_hari_kerja_periode, jumlah_kehadiran_aktual, 
                             gaji_pokok_final, total_tunjangan_tetap, total_tunjangan_tidak_tetap, total_tunjangan_lain, 
                             total_gross_income, total_potongan_bpjs, total_potongan_pph21, total_potongan_lainnya, 
                             take_home_pay, status, calculated_by_id, calculated_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Calculated', ?, NOW())";
        
        $stmt_insert_hist = $conn->prepare($sql_insert_hist);
        if (!$stmt_insert_hist) { throw new Exception("Gagal prepare insert history: " . $conn->error); }
        
        $stmt_insert_hist->bind_param(
            "iiissiiddddddddis",
            $karyawan_id, $run_tahun, $run_bulan, $tgl_mulai_periode, $tgl_selesai_periode,
            $jumlah_hari_kerja_periode, $jumlah_kehadiran_aktual,
            $gaji_pokok_final, $total_tunjangan_tetap, $total_tunjangan_tidak_tetap, $total_tunjangan_lain,
            $total_gross_income, $total_potongan_bpjs, $total_potongan_pph21, $total_potongan_lainnya,
            $take_home_pay, $admin_id
        );
        
        if (!$stmt_insert_hist->execute()) {
            throw new Exception("Gagal insert history: " . $stmt_insert_hist->error);
        }
        $new_history_id = $conn->insert_id;
        $stmt_insert_hist->close();

        // 16. Simpan Detail History
        $sql_insert_detail = "INSERT INTO payroll_history_detail 
                              (payroll_history_id, komponen, deskripsi, tipe, jumlah) 
                              VALUES (?, ?, ?, ?, ?)";
        $stmt_detail = $conn->prepare($sql_insert_detail);
        if (!$stmt_detail) { throw new Exception("Gagal prepare insert detail: " . $conn->error); }

        // Simpan Detail Pendapatan
        foreach ($detail_pendapatan as $item) {
            $tipe = 'Pendapatan';
            if ($item['jumlah'] < 0) { $tipe = 'Potongan'; $item['jumlah'] = abs($item['jumlah']); }
            $stmt_detail->bind_param("issed", $new_history_id, $item['komponen'], $item['deskripsi'], $tipe, $item['jumlah']);
            $stmt_detail->execute();
        }
        // Simpan Detail Potongan
        foreach ($detail_potongan as $item) {
            $tipe = 'Potongan';
            $stmt_detail->bind_param("issed", $new_history_id, $item['komponen'], $item['deskripsi'], $tipe, $item['jumlah']);
            $stmt_detail->execute();
        }
        // Simpan Detail PPh
        foreach ($detail_pph21 as $item) {
            $tipe = 'Potongan';
            $stmt_detail->bind_param("issed", $new_history_id, $item['komponen'], $item['deskripsi'], $tipe, $item['jumlah']);
            $stmt_detail->execute();
        }
        $stmt_detail->close();
        
        // === [PERUBAHAN BARU: Update status THR jika dibayarkan] ===
        if ($thr_history_id_to_update !== null) {
            $stmt_update_thr = $conn->prepare("UPDATE payroll_thr_history 
                                              SET status = 'Paid', payroll_history_id_payout = ? 
                                              WHERE id = ?");
            if (!$stmt_update_thr) {
                throw new Exception("Gagal prepare update status THR: " . $conn->error);
            }
            $stmt_update_thr->bind_param("ii", $new_history_id, $thr_history_id_to_update);
            if (!$stmt_update_thr->execute()) {
                throw new Exception("Gagal update status THR: " . $stmt_update_thr->error);
            }
            $stmt_update_thr->close();
        }
        // === [AKHIR PERUBAHAN] ===

        // 17. Commit
        $conn->commit();
        return ['success' => true];
        
    } catch (Exception $e) {
        // Rollback jika terjadi error
        $conn->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
?>