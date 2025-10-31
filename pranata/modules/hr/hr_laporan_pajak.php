<?php
// pranata/modules/hr/hr_laporan_pajak.php

// === [PERUBAHAN BARU: LOGIKA DIPINDAH KE ATAS] ===

// 1. Mulai session DULUAN
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Panggil db.php untuk koneksi $conn
require '../../includes/db.php'; 

// 3. Helper Functions (Wajib ada SEBELUM dipanggil)
if (!function_exists('number_format_rp')) {
    function number_format_rp($val) {
        return 'Rp ' . number_format($val, 0, ',', '.');
    }
}
if (!function_exists('calculateTarifPasal17')) {
    function calculateTarifPasal17($pkp) {
        if ($pkp <= 0) {
            return 0;
        }
        $pph = 0;
        $lapisan1 = 60000000;
        if ($pkp > 0) {
            $kena_pajak = min($pkp, $lapisan1);
            $pph += (0.05 * $kena_pajak);
            $pkp -= $kena_pajak;
        }
        $lapisan2 = 190000000;
        if ($pkp > 0) {
            $kena_pajak = min($pkp, $lapisan2);
            $pph += (0.15 * $kena_pajak);
            $pkp -= $kena_pajak;
        }
        $lapisan3 = 250000000;
        if ($pkp > 0) {
            $kena_pajak = min($pkp, $lapisan3);
            $pph += (0.25 * $kena_pajak);
            $pkp -= $kena_pajak;
        }
        $lapisan4 = 4500000000;
        if ($pkp > 0) {
            $kena_pajak = min($pkp, $lapisan4);
            $pph += (0.30 * $kena_pajak);
            $pkp -= $kena_pajak;
        }
        if ($pkp > 0) {
            $pph += (0.35 * $pkp);
        }
        return $pph;
    }
}

/**
 * [PERUBAHAN BARU]
 * Fungsi utama untuk kalkulasi massal, dibungkus agar bisa dipakai ulang
 */
function calculateAnnualTaxSummary($conn, $selected_tahun) {
    // Variabel ini di-scope di dalam fungsi
    $settings = [];
    $ptkp_values = [];
    $errors = []; 
    $rekap_list = [];

    try {
        // 1. Ambil Semua Pengaturan Sekaligus
        if (empty($settings)) {
            $stmt_settings = $conn->prepare("SELECT setting_key, setting_value FROM payroll_settings");
            $stmt_settings->execute();
            $result_settings = $stmt_settings->get_result();
            while ($row = $result_settings->fetch_assoc()) {
                $settings[$row['setting_key']] = (float)$row['setting_value'];
            }
            $stmt_settings->close();
        }
        
        if (empty($ptkp_values)) {
            $stmt_ptkp = $conn->prepare("SELECT kode_ptkp, nilai_ptkp_tahunan FROM payroll_settings_ptkp");
            $stmt_ptkp->execute();
            $result_ptkp = $stmt_ptkp->get_result();
            while ($row = $result_ptkp->fetch_assoc()) {
                $ptkp_values[$row['kode_ptkp']] = $row['nilai_ptkp_tahunan'];
            }
            $stmt_ptkp->close();
        }

        // 2. Ambil Semua Riwayat Gaji Setahun
        $history_data_by_user = [];
        $stmt_history = $conn->prepare(
            "SELECT user_id, periode_bulan, gaji_pokok_final, total_tunjangan_tetap, 
                    total_tunjangan_tidak_tetap, total_tunjangan_lain, total_potongan_pph21 
             FROM payroll_history 
             WHERE periode_tahun = ?"
        );
        $stmt_history->bind_param("i", $selected_tahun);
        $stmt_history->execute();
        $result_history = $stmt_history->get_result();
        while ($month = $result_history->fetch_assoc()) {
            $history_data_by_user[$month['user_id']][$month['periode_bulan']] = $month;
        }
        $stmt_history->close();

        // 3. Ambil Semua Karyawan Aktif + Master Gaji
        $stmt_users = $conn->prepare("SELECT u.id, u.nama_lengkap, u.nik, u.no_ktp, u.npwp, u.nama_jabatan, pmg.status_ptkp 
                                    FROM users u
                                    LEFT JOIN payroll_master_gaji pmg ON u.id = pmg.user_id
                                    WHERE u.status_karyawan IN ('PKWT', 'PKWTT', 'BOD', 'BOC') 
                                      AND u.tier != 'Admin'
                                    ORDER BY u.nama_lengkap ASC");
        $stmt_users->execute();
        $result_users = $stmt_users->get_result();
        
        if ($result_users->num_rows == 0) {
            throw new Exception("Tidak ada karyawan aktif yang ditemukan.");
        }
        
        // 4. Looping Per Karyawan untuk Kalkulasi
        while ($karyawan = $result_users->fetch_assoc()) {
            $karyawan_id = $karyawan['id'];
            
            if (!isset($history_data_by_user[$karyawan_id])) {
                continue; 
            }
            
            $status_ptkp_karyawan = $karyawan['status_ptkp'];
            $ptkp = 0;
            if (!empty($status_ptkp_karyawan) && isset($ptkp_values[$status_ptkp_karyawan])) {
                $ptkp = $ptkp_values[$status_ptkp_karyawan];
            }

            $total_gaji_pokok_setahun = 0;
            $total_tunj_tetap_setahun = 0;
            $total_tunj_tdk_tetap_setahun = 0;
            $total_tunj_lain_setahun = 0; // Termasuk THR
            $total_pph21_dibayar_setahun = 0;
            $total_tunj_bpjs_prsh_setahun = 0;
            $total_biaya_jabatan_setahun = 0;
            $total_pot_jht_jp_karyawan_setahun = 0;
            $bulan_bekerja = 0;
            
            foreach ($history_data_by_user[$karyawan_id] as $periode_bulan => $month) {
                $bulan_bekerja++;
                
                $total_gaji_pokok_setahun += $month['gaji_pokok_final'];
                $total_tunj_tetap_setahun += $month['total_tunjangan_tetap'];
                $total_tunj_tdk_tetap_setahun += $month['total_tunjangan_tidak_tetap'];
                $total_tunj_lain_setahun += $month['total_tunjangan_lain'];
                $total_pph21_dibayar_setahun += $month['total_potongan_pph21'];

                $gaji_bruto_sebulan = $month['gaji_pokok_final'] + $month['total_tunjangan_tetap'] + $month['total_tunjangan_tidak_tetap'] + $month['total_tunjangan_lain'];
                
                $dasar_bpjs_kes_bln = min($month['gaji_pokok_final'] + $month['total_tunjangan_tetap'], $settings['bpjs_kes_max_upah']);
                $dasar_bpjs_tk_bln = $month['gaji_pokok_final'] + $month['total_tunjangan_tetap'];
                
                $tunj_kes_prsh = ($settings['bpjs_kes_perusahaan_pct'] / 100) * $dasar_bpjs_kes_bln;
                $tunj_jkk_prsh = ($settings['jkk_perusahaan_pct'] / 100) * $dasar_bpjs_tk_bln;
                $tunj_jkm_prsh = ($settings['jkm_perusahaan_pct'] / 100) * $dasar_bpjs_tk_bln;
                $tunj_bpjs_perusahaan_pph_sebulan = $tunj_kes_prsh + $tunj_jkk_prsh + $tunj_jkm_prsh;
                $total_tunj_bpjs_prsh_setahun += $tunj_bpjs_perusahaan_pph_sebulan;

                $bruto_pph_sebulan = $gaji_bruto_sebulan + $tunj_bpjs_perusahaan_pph_sebulan;
                $biaya_jabatan_sebulan = min($bruto_pph_sebulan * 0.05, 500000);
                $total_biaya_jabatan_setahun += $biaya_jabatan_sebulan;
                
                $dasar_jp_bln = min($dasar_bpjs_tk_bln, $settings['jp_max_upah']);
                $pot_jht_karyawan_bln = ($settings['jht_karyawan_pct'] / 100) * $dasar_bpjs_tk_bln;
                $pot_jp_karyawan_bln = ($settings['jp_karyawan_pct'] / 100) * $dasar_jp_bln;
                $total_pot_jht_jp_karyawan_setahun += $pot_jht_karyawan_bln + $pot_jp_karyawan_bln;
            }
            
            $max_biaya_jabatan_tahunan = 500000 * $bulan_bekerja;
            if ($total_biaya_jabatan_setahun > $max_biaya_jabatan_tahunan) {
                $total_biaya_jabatan_setahun = $max_biaya_jabatan_tahunan;
            }

            $total_bruto = $total_gaji_pokok_setahun + $total_tunj_tetap_setahun + $total_tunj_tdk_tetap_setahun + $total_tunj_lain_setahun + $total_tunj_bpjs_prsh_setahun;
            $total_pengurang = $total_biaya_jabatan_setahun + $total_pot_jht_jp_karyawan_setahun;
            $neto_setahun = $total_bruto - $total_pengurang;
            $pkp = $neto_setahun - $ptkp;
            $pkp_rounded = ($pkp > 0) ? floor($pkp / 1000) * 1000 : 0;
            $pph21_terutang_setahun = calculateTarifPasal17($pkp_rounded);

            // Masukkan ke array rekap
            $rekap_list[] = [
                // Info Identitas
                'nama' => $karyawan['nama_lengkap'],
                'nik_ktp' => $karyawan['no_ktp'] ?? '', 
                'nik_karyawan' => $karyawan['nik'], 
                'npwp' => $karyawan['npwp'] ?? '',
                'jabatan' => $karyawan['nama_jabatan'],
                'status_ptkp' => $karyawan['status_ptkp'],
                
                'GajiDanTunjangan' => $total_gaji_pokok_setahun + $total_tunj_tetap_setahun + $total_tunj_tdk_tetap_setahun,
                'TantiemBonusThr' => $total_tunj_lain_setahun,
                'TunjanganBPJSPrsh' => $total_tunj_bpjs_prsh_setahun,
                'PotonganJHTJPKaryawan' => $total_pot_jht_jp_karyawan_setahun,
                'PPh21Dipotong' => $total_pph21_dibayar_setahun, 
                
                'bruto_setahun' => $total_bruto,
                'pph21_terutang' => $pph21_terutang_setahun, 
            ];
        }
        $stmt_users->close();
        
        return ['success' => true, 'data' => $rekap_list, 'errors' => []];

    } catch (Exception $e) {
        return ['success' => false, 'data' => [], 'errors' => [$e->getMessage()]];
    }
}
// === [AKHIR FUNGSI BARU] ===

/**
 * Fungsi untuk membuat elemen XML
 */
function createXmlElement($doc, $parent, $name, $value) {
    // Bersihkan value dari karakter non-UTF8 jika ada
    $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    $element = $doc->createElement($name, htmlspecialchars($value));
    $parent->appendChild($element);
    return $element;
}

// === [PERBAIKAN: Variabel Keamanan Global] ===
// Variabel ini harus ada SEBELUM logika Ekspor dan HTML
$app_akses = $_SESSION['app_akses'] ?? 'Karyawan';
$is_admin = ($app_akses == 'Admin');
$is_hr = ($app_akses == 'HR');
// === [AKHIR PERBAIKAN] ===


// === Logika Ekspor XML (SEBELUM HEADER.PHP) ===
$selected_tahun = (int)($_GET['tahun'] ?? date('Y'));
if (isset($_GET['action']) && $_GET['action'] == 'export_xml') {
    
    // Security check (menggunakan variabel global)
    if (!$is_admin && !$is_hr) {
        die('Akses ditolak.');
    }

    // Nonaktifkan error reporting agar tidak merusak XML
    error_reporting(0);
    
    // Jalankan kalkulasi massal
    $conn->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);
    $result = calculateAnnualTaxSummary($conn, $selected_tahun);
    $conn->commit();
    
    if (!$result['success']) {
        // Jika ada error, jangan buat XML, tampilkan error
        echo "Gagal membuat XML: \n";
        foreach ($result['errors'] as $err) {
            echo "- " . $err . "\n";
        }
        exit;
    }
    
    $rekap_list_export = $result['data'];

    // Ambil NPWP Perusahaan (PT PUTRA NATUR UTAMA)
    $stmt_company = $conn->prepare("SELECT npwp FROM companies WHERE id = 1 LIMIT 1");
    $stmt_company->execute();
    $company_npwp = $stmt_company->get_result()->fetch_assoc()['npwp'];
    $stmt_company->close();
    
    $company_npwp_clean_15 = preg_replace('/[^0-9]/', '', $company_npwp);
    $company_tin_xml = $company_npwp_clean_15 . "0"; // 16 digit
    $company_id_tku_xml = $company_tin_xml . "000000"; // 22 digit

    // Mulai Buat XML
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->formatOutput = true;

    $root = $doc->createElement('A1Bulk');
    $root->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
    $doc->appendChild($root);

    createXmlElement($doc, $root, 'TIN', $company_tin_xml);
    $list_of_a1 = $doc->createElement('ListOfA1');
    $root->appendChild($list_of_a1);

    foreach ($rekap_list_export as $rekap) {
        $a1 = $doc->createElement('A1');
        $list_of_a1->appendChild($a1);

        createXmlElement($doc, $a1, 'WorkForSecondEmployer', 'No');
        createXmlElement($doc, $a1, 'TaxPeriodMonthStart', '1');
        createXmlElement($doc, $a1, 'TaxPeriodMonthEnd', '12');
        createXmlElement($doc, $a1, 'TaxPeriodYear', $selected_tahun);
        createXmlElement($doc, $a1, 'CounterpartOpt', 'Resident');
        createXmlElement($doc, $a1, 'CounterpartPassport', '')->setAttribute('xsi:nil', 'true');
        
        $nik_clean = preg_replace('/[^0-9]/', '', $rekap['nik_ktp']);
        createXmlElement($doc, $a1, 'CounterpartTin', $nik_clean); 
        
        createXmlElement($doc, $a1, 'TaxExemptOpt', $rekap['status_ptkp']);
        createXmlElement($doc, $a1, 'StatusOfWithholding', 'FullYear'); 
        createXmlElement($doc, $a1, 'CounterpartPosition', $rekap['jabatan']);
        createXmlElement($doc, $a1, 'TaxObjectCode', '21-100-01'); 
        createXmlElement($doc, $a1, 'NumberOfMonths', '0'); 
        
        createXmlElement($doc, $a1, 'SalaryPensionJhtTht', round($rekap['GajiDanTunjangan']));
        createXmlElement($doc, $a1, 'GrossUpOpt', 'No');
        createXmlElement($doc, $a1, 'IncomeTaxBenefit', '0'); 
        createXmlElement($doc, $a1, 'OtherBenefit', '0'); 
        createXmlElement($doc, $a1, 'Honorarium', '0'); 
        createXmlElement($doc, $a1, 'InsurancePaidByEmployer', round($rekap['TunjanganBPJSPrsh']));
        createXmlElement($doc, $a1, 'Natura', '0'); 
        createXmlElement($doc, $a1, 'TantiemBonusThr', round($rekap['TantiemBonusThr']));
        
        createXmlElement($doc, $a1, 'PensionContributionJhtThtFee', round($rekap['PotonganJHTJPKaryawan']));
        createXmlElement($doc, $a1, 'Zakat', '0'); 
        
        createXmlElement($doc, $a1, 'PrevWhTaxSlip', '')->setAttribute('xsi:nil', 'true');
        createXmlElement($doc, $a1, 'TaxCertificate', 'N/A');
        
        createXmlElement($doc, $a1, 'Article21IncomeTax', round($rekap['pph21_dipotong'])); 
        
        createXmlElement($doc, $a1, 'IDPlaceOfBusinessActivity', $company_id_tku_xml);
        createXmlElement($doc, $a1, 'WithholdingDate', "$selected_tahun-12-31");
    }

    // Set Header untuk Download
    $filename = "eBupot_A1_Pranata_{$selected_tahun}.xml";
    header('Content-Description: File Transfer');
    header('Content-Type: application/xml; charset=utf-8'); 
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . strlen($doc->saveXML()));
    
    // Output XML
    echo $doc->saveXML();
    exit; // WAJIB exit
}
// === [AKHIR LOGIKA EKSPOR] ===


// === MULAI LOGIKA TAMPILAN HTML ===

// 1. Set variabel halaman
$page_title = "Pelaporan Pajak Tahunan (e-Bupot)";
$page_active = "hr_laporan_pajak";

// 2. Panggil header (SEKARANG BARU BOLEH)
// db.php dan session_start() sudah dipanggil di atas
require '../../includes/header.php'; 

// 3. Keamanan Halaman (double check, $is_admin, $is_hr sudah di-set di atas)
// Ini adalah baris 344 yang menyebabkan error
if (!$is_admin && !$is_hr) {
    echo "<main class='flex-1 p-6'><div class='card'><div class='card-content'>Akses ditolak.</div></div></main>";
    require '../../includes/footer.php';
    exit;
}

// 4. Inisialisasi Variabel Halaman
$errors = []; // Reset errors untuk tampilan HTML
$rekap_list = [];
$selected_tahun = (int)($_GET['tahun'] ?? date('Y'));

// 5. Logika Tampilan HTML
if ($selected_tahun > 0) {
    $conn->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);
    $result = calculateAnnualTaxSummary($conn, $selected_tahun);
    $conn->commit();
    
    if ($result['success']) {
        $rekap_list = $result['data'];
    } else {
        $errors = $result['errors'];
    }
}
    
// 6. Panggil Sidebar
require '../../includes/sidebar.php';
?>

<style>
    .rekap-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }
    .rekap-table th, .rekap-table td {
        border: 1px solid #e5e7eb;
        padding: 0.5rem 0.75rem;
        text-align: left;
    }
    .rekap-table thead th {
        background-color: #f9fafb;
        font-weight: 600;
        position: sticky;
        top: 0;
    }
    .rekap-table td.text-right { text-align: right; }
    .rekap-table tbody tr:nth-child(even) { background-color: #f9fafb; }
    .rekap-table tbody tr:hover { background-color: #f3f4f6; }
</style>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    
    <div id="page-title" class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Pelaporan Pajak Tahunan (e-Bupot)</h1>
    </div>

    <?php if (!empty($errors)): ?>
        <div id="form-filter" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4 max-w-full" role="alert">
            <strong class="font-bold">Error!</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div id="form-filter" class="card mb-6">
        <div class="card-content">
            <form action="hr_laporan_pajak.php" method="GET" class="flex justify-between items-end gap-4">
                <div class="flex items-end gap-4">
                    <div>
                        <label for="tahun" class="form-label">Tahun</label>
                        <select id="tahun" name="tahun" class="form-input">
                            <?php for ($y = 2024; $y <= 2026; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php if ($y == $selected_tahun) echo 'selected'; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary-sm bg-blue-600 hover:bg-blue-700 h-10">
                        Tampilkan Rekap Tahunan
                    </button>
                </div>
                
                <a href="?tahun=<?php echo $selected_tahun; ?>&action=export_xml" 
                   class="btn-primary-sm bg-green-600 hover:bg-green-700 h-10 flex items-center justify-center px-4 py-2 no-underline" 
                   title="Ekspor data rekapitulasi tahun <?php echo $selected_tahun; ?> ke format XML">
                   Ekspor e-Bupot (XML)
                </a>
            </form>
        </div>
    </div>

    <?php if (!empty($rekap_list)): ?>
    <div class="card">
        <div class="card-header">
            <h3 class="text-xl font-semibold text-gray-800">
                Rekapitulasi PPh 21 Tahun <?php echo $selected_tahun; ?> (<?php echo count($rekap_list); ?> Karyawan)
            </h3>
        </div>
        <div class="card-content" style="max-height: 60vh; overflow-y: auto;">
            <table class="rekap-table">
                <thead>
                    <tr>
                        <th class="w-1/12">No.</th>
                        <th class="w-3/12">Nama Karyawan</th>
                        <th class="w-2/12">NIK KTP</th>
                        <th class="w-2/12">NPWP</th>
                        <th class="w-2/12 text-right">Bruto Setahun (Rp)</th>
                        <th class="w-2/12 text-right">PPh 21 Terutang (Rp)</th>
                        <th class="w-2/12 text-right">PPh 21 Dipotong (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($rekap_list as $rekap): ?>
                    <tr>
                        <td><?php echo $no++; ?>.</td>
                        <td><?php echo htmlspecialchars($rekap['nama']); ?></td>
                        <td><?php echo htmlspecialchars($rekap['nik_ktp']); ?></td>
                        <td><?php echo htmlspecialchars($rekap['npwp']); ?></td>
                        <td class="text-right"><?php echo number_format($rekap['bruto_setahun'], 0, ',', '.'); ?></td>
                        <td class="text-right"><?php echo number_format($rekap['pph21_terutang'], 0, ',', '.'); ?></td>
                        <td class="text-right"><?php echo number_format($rekap['pph21_dipotong'], 0, ',', '.'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php elseif ($selected_tahun > 0 && empty($errors)): ?>
         <div class="card">
            <div class="card-content text-center">
                <p class="text-gray-500">Tidak ada data payroll yang ditemukan untuk tahun <?php echo $selected_tahun; ?>.</p>
            </div>
         </div>
    <?php endif; ?>

</main>

<?php
// 8. Panggil footer
require '../../includes/footer.php';
?>