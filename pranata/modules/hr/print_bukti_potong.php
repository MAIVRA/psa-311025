<?php
// pranata/modules/hr/print_bukti_potong.php
// Halaman ini HANYA untuk layout cetak.

// 1. Panggil header (hanya untuk $conn dan session check)
// Kita panggil header, tapi tidak akan panggil sidebar/footer
require '../../includes/header.php'; 

// 2. Keamanan Halaman
$app_akses = $_SESSION['app_akses'] ?? 'Karyawan';
$is_admin = ($app_akses == 'Admin');
$is_hr = ($app_akses == 'HR');
$logged_in_user_id = $_SESSION['user_id'] ?? 0; // [BARU] Ambil ID user yang login

// 4. Inisialisasi Variabel
$errors = [];
$settings = [];
$ptkp_values = [];
$perhitungan = null; 

// Helper function
if (!function_exists('number_format_rp')) {
    function number_format_rp($val) {
        return number_format($val ?? 0, 0, ',', '.');
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

// 5. Ambil data dari URL
$selected_karyawan_id = (int)($_GET['karyawan_id'] ?? 0);
$selected_tahun = (int)($_GET['tahun'] ?? 0);

if ($selected_karyawan_id == 0 || $selected_tahun == 0) {
     die("Parameter karyawan_id atau tahun tidak valid.");
}

// === [PERBAIKAN KEAMANAN BARU] ===
// Cek apakah user ini adalah pemilik Bupot, ATAU HR/Admin
$is_owner = ($selected_karyawan_id == $logged_in_user_id);

// Terapkan aturan keamanan
if (!$is_admin && !$is_hr && !$is_owner) {
    die("Akses ditolak. Anda tidak memiliki izin untuk melihat data ini.");
}
// === [AKHIR PERBAIKAN] ===


if ($selected_karyawan_id > 0 && $selected_tahun > 0) {
    
    // Inisialisasi array perhitungan
    $perhitungan = [
        'A_IDENTITAS' => [],
        'B_BRUTO' => [],
        'C_PENGURANG' => [],
        'D_KALKULASI_PPH' => [],
        'C_PEMOTONG' => [], 
    ];
    
    $conn->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);
    try {
        // 1. Ambil Data Karyawan & Master Gaji
        $sql_user = "SELECT u.nama_lengkap, u.nik as nik_karyawan, u.no_ktp, u.npwp, u.nama_jabatan, u.jenis_kelamin, u.alamat, pmg.status_ptkp 
                     FROM users u
                     LEFT JOIN payroll_master_gaji pmg ON u.id = pmg.user_id
                     WHERE u.id = ?";
                     
        $stmt_user = $conn->prepare($sql_user);
        if (!$stmt_user) {
             throw new Exception("SQL error: " . $conn->error);
        }
        
        $stmt_user->bind_param("i", $selected_karyawan_id);
        $stmt_user->execute();
        $data_karyawan = $stmt_user->get_result()->fetch_assoc();
        if (!$data_karyawan) {
            throw new Exception("Data master gaji karyawan tidak ditemukan.");
        }
        $perhitungan['A_IDENTITAS'] = [
            'NAMA' => $data_karyawan['nama_lengkap'],
            'NIK_KTP' => $data_karyawan['no_ktp'] ?? $data_karyawan['nik_karyawan'],
            'NPWP' => $data_karyawan['npwp'] ?? '-',
            'ALAMAT' => $data_karyawan['alamat'] ?? '-',
            'JENIS_KELAMIN' => $data_karyawan['jenis_kelamin'],
            'JABATAN' => $data_karyawan['nama_jabatan'],
            'STATUS_PTKP' => $data_karyawan['status_ptkp'],
        ];
        $stmt_user->close();
        
        // 2. Ambil Nilai PTKP
        if (empty($data_karyawan['status_ptkp'])) {
             throw new Exception("Status PTKP karyawan belum di-set di Master Gaji.");
        }
        $stmt_ptkp = $conn->prepare("SELECT nilai_ptkp_tahunan FROM payroll_settings_ptkp WHERE kode_ptkp = ?");
        $stmt_ptkp->bind_param("s", $data_karyawan['status_ptkp']);
        $stmt_ptkp->execute();
        $data_ptkp = $stmt_ptkp->get_result()->fetch_assoc();
        if (!$data_ptkp) {
            throw new Exception("Nilai PTKP untuk kode " . $data_karyawan['status_ptkp'] . " tidak ditemukan.");
        }
        $perhitungan['D_KALKULASI_PPH']['PTKP'] = $data_ptkp['nilai_ptkp_tahunan'];
        $stmt_ptkp->close();
        
        // 3. Ambil Payroll Settings (BPJS, dll)
        $stmt_settings = $conn->prepare("SELECT setting_key, setting_value FROM payroll_settings");
        $stmt_settings->execute();
        $result_settings = $stmt_settings->get_result();
        while ($row = $result_settings->fetch_assoc()) {
            $settings[$row['setting_key']] = (float)$row['setting_value'];
        }
        $stmt_settings->close();

        // 4. Ambil dan Agregasi Riwayat Gaji 12 Bulan
        $stmt_history = $conn->prepare(
            "SELECT periode_bulan, gaji_pokok_final, total_tunjangan_tetap, total_tunjangan_tidak_tetap, 
                    total_tunjangan_lain, total_potongan_pph21 
             FROM payroll_history 
             WHERE user_id = ? AND periode_tahun = ? 
             ORDER BY periode_bulan ASC"
        );
        $stmt_history->bind_param("ii", $selected_karyawan_id, $selected_tahun);
        $stmt_history->execute();
        $result_history = $stmt_history->get_result();
        
        $total_gaji_pokok_setahun = 0;
        $total_tunj_tetap_setahun = 0;
        $total_tunj_tdk_tetap_setahun = 0;
        $total_tunj_lain_setahun = 0;
        $total_pph21_dibayar_setahun = 0;
        
        $total_tunj_bpjs_prsh_setahun = 0;
        $total_biaya_jabatan_setahun = 0;
        $total_pot_jht_jp_karyawan_setahun = 0;
        $bulan_bekerja = 0;

        while ($month = $result_history->fetch_assoc()) {
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
        $stmt_history->close();
        
        if ($bulan_bekerja == 0) {
            throw new Exception("Tidak ditemukan riwayat gaji (payroll_history) untuk karyawan ini di tahun $selected_tahun.");
        }
        
        $max_biaya_jabatan_tahunan = 500000 * $bulan_bekerja;
        if ($total_biaya_jabatan_setahun > $max_biaya_jabatan_tahunan) {
            $total_biaya_jabatan_setahun = $max_biaya_jabatan_tahunan;
        }

        $perhitungan['B_BRUTO'] = [
            '1_GAJI_PENSIUN' => $total_gaji_pokok_setahun + $total_tunj_tetap_setahun, 
            '2_TUNJANGAN_PPH' => 0, 
            '3_TUNJANGAN_LAINNYA' => $total_tunj_tdk_tetap_setahun, 
            '4_HONORARIUM' => 0, 
            '5_PREMI_ASURANSI' => $total_tunj_bpjs_prsh_setahun, 
            '6_NATURA' => 0, 
            '7_THR_BONUS' => $total_tunj_lain_setahun, 
        ];
        $total_bruto = array_sum($perhitungan['B_BRUTO']);
        $perhitungan['B_BRUTO']['8_JUMLAH_BRUTO'] = $total_bruto;

        $perhitungan['C_PENGURANG'] = [
            '9_BIAYA_JABATAN' => $total_biaya_jabatan_setahun,
            '10_IURAN_PENSIUN' => $total_pot_jht_jp_karyawan_setahun,
        ];
        $total_pengurang = array_sum($perhitungan['C_PENGURANG']);
        $perhitungan['C_PENGURANG']['11_JUMLAH_PENGURANG'] = $total_pengurang;
        
        $neto_setahun = $total_bruto - $total_pengurang;
        $ptkp = $perhitungan['D_KALKULASI_PPH']['PTKP'];
        $pkp = $neto_setahun - $ptkp;
        
        $pkp_rounded = ($pkp > 0) ? floor($pkp / 1000) * 1000 : 0;
        
        $pph21_terutang_setahun = calculateTarifPasal17($pkp_rounded);
        
        $perhitungan['D_KALKULASI_PPH']['12_NETO_SETAHUN'] = $neto_setahun;
        $perhitungan['D_KALKULASI_PPH']['13_PTKP'] = $ptkp;
        $perhitungan['D_KALKULASI_PPH']['14_PKP_SETAHUN'] = $pkp_rounded;
        $perhitungan['D_KALKULASI_PPH']['15_PPH21_TERUTANG_SETAHUN'] = $pph21_terutang_setahun;
        $perhitungan['D_KALKULASI_PPH']['16_PPH21_DIPOTONG_SEBELUMNYA'] = 0; 
        $perhitungan['D_KALKULASI_PPH']['17_PPH21_DIPOTONG_TER'] = $total_pph21_dibayar_setahun;
        
        $pph21_desember = $pph21_terutang_setahun - $total_pph21_dibayar_setahun;
        $perhitungan['D_KALKULASI_PPH']['18_PPH21_KURANG_LEBIH_BAYAR'] = $pph21_desember;

        $stmt_company = $conn->prepare("SELECT npwp, nama_perusahaan FROM companies WHERE id = 1 LIMIT 1");
        $stmt_company->execute();
        $data_company = $stmt_company->get_result()->fetch_assoc();
        $perhitungan['C_PEMOTONG'] = [
            'NPWP' => $data_company['npwp'] ?? '76.335.061.8-613.000',
            'NAMA' => $data_company['nama_perusahaan'] ?? 'PT PUTRA NATUR UTAMA',
            'TANGGAL' => '31 Desember ' . $selected_tahun,
        ];
        $stmt_company->close();

        $conn->commit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $errors[] = $e->getMessage();
        $perhitungan = null;
    }
} else {
    // Jika ID tidak valid dari awal
    if(empty($errors)) { // Hanya tampilkan jika belum ada error lain
        $errors[] = "Parameter karyawan_id atau tahun tidak valid.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Bukti Potong 1721-A1 - <?php echo htmlspecialchars($perhitungan['A_IDENTITAS']['NAMA'] ?? 'Karyawan'); ?> - <?php echo $selected_tahun; ?></title>
    
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.5;
            background: #fff;
            margin: 0;
            padding: 20px;
        }
        .form-1721-a1-container {
            width: 100%;
            max-width: 800px;
            margin: auto;
            border: 1px solid #ccc;
            padding: 1.5rem;
        }
        .form-header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
        .form-header h1 {
            font-size: 14px;
            font-weight: bold;
            margin: 0;
            padding: 0;
        }
        .form-header p {
            font-size: 12px;
            margin: 0;
        }
        .form-title {
            text-align: right;
            font-size: 10px;
            margin-bottom: 10px;
        }
        .form-section-title {
            font-weight: bold;
            background-color: #e0e0e0;
            padding: 4px 6px;
            border: 1px solid #999;
            margin-top: 10px;
            margin-bottom: 5px;
        }
        .bupot-table-a1 {
            width: 100%;
            border-collapse: collapse;
        }
        .bupot-table-a1 td {
            border: 1px solid #999;
            padding: 4px 6px;
            vertical-align: top;
        }
        .bupot-table-a1 .col-desc {
            width: 60%;
        }
        .bupot-table-a1 .col-num {
            width: 5%;
            text-align: center;
        }
        .bupot-table-a1 .col-val {
            width: 35%;
            text-align: right;
            font-family: 'Courier New', Courier, monospace;
            font-weight: bold;
            padding-right: 10px;
        }
        .bupot-table-a1 .col-header {
            text-align: center;
            font-weight: bold;
            background-color: #e0e0e0;
        }
        .bupot-table-a1 .row-total {
            font-weight: bold;
            background-color: #f0f0f0;
        }
        .bupot-table-a1 .row-final {
            font-weight: bold;
            background-color: #e0e0e0;
        }
        .identitas-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
        }
        .identitas-grid .grid-item {
            border: 1px solid #999;
            padding: 4px 6px;
        }
        .identitas-grid .grid-label {
            font-size: 10px;
            text-transform: uppercase;
        }
        .identitas-grid .grid-value {
            font-weight: bold;
            font-family: 'Courier New', Courier, monospace;
        }
        .ttd-section {
            margin-top: 20px;
            display: grid;
            grid-template-columns: 2fr 1fr;
        }
        .ttd-box {
            border: 1px solid #999;
            height: 80px;
            padding: 4px 6px;
            text-align: center;
        }
        .ttd-box .ttd-date {
            font-weight: bold;
        }
        
        @media print {
            body {
                padding: 0;
            }
            .form-1721-a1-container {
                border: none;
                padding: 0;
                margin: 0;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>

    <?php if ($perhitungan): ?>
    <div class="form-1721-a1-container" id="bukti-potong-print">
        
        <div class="form-header">
            <h1>BUKTI PEMOTONGAN PAJAK PENGHASILAN PASAL 21</h1>
            <p>BAGI PEGAWAI TETAP ATAU PENERIMA PENSIUN ATAU THT/JHT BERKALA</p>
        </div>
        
        <div class="form-title">
            FORMULIR 1721 - A1
        </div>

        <div class="identitas-grid" style="grid-template-columns: 2fr 1fr 1fr;">
             <div class="grid-item" style="border-right: none;">
                <span class="grid-label">NPWP PEMOTONG</span><br>
                <span class="grid-value"><?php echo htmlspecialchars($perhitungan['C_PEMOTONG']['NPWP']); ?></span>
            </div>
             <div class="grid-item" style="border-right: none;">
                <span class="grid-label">MASA PEROLEHAN</span><br>
                <span class="grid-value">01 - 12</span>
            </div>
             <div class="grid-item">
                <span class="grid-label">TAHUN</span><br>
                <span class="grid-value"><?php echo $selected_tahun; ?></span>
            </div>
        </div>
         <div class="grid-item" style="border-top: none;">
            <span class="grid-label">NAMA PEMOTONG</span><br>
            <span class="grid-value"><?php echo htmlspecialchars($perhitungan['C_PEMOTONG']['NAMA']); ?></span>
        </div>


        <div class="form-section-title">A. IDENTITAS PENERIMA PENGHASILAN YANG DIPOTONG</div>
        <div class="identitas-grid">
            <div class="grid-item" style="border-right: none;">
                <span class="grid-label">1. NPWP</span><br>
                <span class="grid-value"><?php echo htmlspecialchars($perhitungan['A_IDENTITAS']['NPWP']); ?></span>
            </div>
            <div class="grid-item">
                <span class="grid-label">6. STATUS/JUMLAH TANGGUNGAN KELUARGA</span><br>
                <span class="grid-value"><?php echo htmlspecialchars($perhitungan['A_IDENTITAS']['STATUS_PTKP']); ?></span>
            </div>
             <div class="grid-item" style="border-right: none; border-top: none;">
                <span class="grid-label">2. NIK/NO. PASPOR</span><br>
                <span class="grid-value"><?php echo htmlspecialchars($perhitungan['A_IDENTITAS']['NIK_KTP']); ?></span>
            </div>
            <div class="grid-item" style="border-top: none;">
                <span class="grid-label">7. NAMA JABATAN</span><br>
                <span class="grid-value"><?php echo htmlspecialchars($perhitungan['A_IDENTITAS']['JABATAN']); ?></span>
            </div>
             <div class="grid-item" style="border-right: none; border-top: none;">
                <span class="grid-label">3. NAMA</span><br>
                <span class="grid-value"><?php echo htmlspecialchars($perhitungan['A_IDENTITAS']['NAMA']); ?></span>
            </div>
            <div class="grid-item" style="border-top: none;">
                <span class="grid-label">8. KARYAWAN ASING</span><br>
                <span class="grid-value">TIDAK</span>
            </div>
             <div class="grid-item" style="border-right: none; border-top: none;">
                <span class="grid-label">4. ALAMAT</span><br>
                <span class="grid-value" style="font-size: 10px;"><?php echo htmlspecialchars($perhitungan['A_IDENTITAS']['ALAMAT']); ?></span>
            </div>
            <div class="grid-item" style="border-top: none;">
                <span class="grid-label">5. JENIS KELAMIN</span><br>
                <span class="grid-value"><?php echo htmlspecialchars(strtoupper($perhitungan['A_IDENTITAS']['JENIS_KELAMIN'])); ?></span>
            </div>
        </div>

        <div class="form-section-title">B. RINCIAN PENGHASILAN DAN PENGHITUNGAN PPH PASAL 21</div>
        <table class="bupot-table-a1">
            <thead>
                <tr class="col-header">
                    <td>URAIAN</td>
                    <td class="col-num">KODE OBJEK PAJAK</td>
                    <td>JUMLAH (Rp)</td>
                </tr>
                <tr class="col-header">
                    <td>(1)</td>
                    <td class="col-num">(2)</td>
                    <td>(3)</td>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="3" style="font-weight: bold; background: #f0f0f0;">PENGHASILAN BRUTO:</td>
                </tr>
                <tr>
                    <td>1. GAJI/PENSIUN ATAU THT/JHT</td>
                    <td class="col-num" rowspan="7" style="vertical-align: middle; text-align: center; font-weight: bold;">21-100-01</td>
                    <td class="col-val"><?php echo number_format_rp($perhitungan['B_BRUTO']['1_GAJI_PENSIUN']); ?></td>
                </tr>
                <tr>
                    <td>2. TUNJANGAN PPh</td>
                    <td class="col-val"><?php echo number_format_rp($perhitungan['B_BRUTO']['2_TUNJANGAN_PPH']); ?></td>
                </tr>
                <tr>
                    <td>3. TUNJANGAN LAINNYA, UANG LEMBUR, DSB.</td>
                    <td class="col-val"><?php echo number_format_rp($perhitungan['B_BRUTO']['3_TUNJANGAN_LAINNYA']); ?></td>
                </tr>
                 <tr>
                    <td>4. HONORARIUM DAN IMBALAN LAIN SEJENISNYA</td>
                    <td class="col-val"><?php echo number_format_rp($perhitungan['B_BRUTO']['4_HONORARIUM']); ?></td>
                </tr>
                 <tr>
                    <td>5. PREMI ASURANSI YANG DIBAYAR PEMBERI KERJA</td>
                    <td class="col-val"><?php echo number_format_rp($perhitungan['B_BRUTO']['5_PREMI_ASURANSI']); ?></td>
                </tr>
                 <tr>
                    <td>6. PENERIMAAN DALAM BENTUK NATURA (YANG DIKENAKAN PAJAK)</td>
                    <td class="col-val"><?php echo number_format_rp($perhitungan['B_BRUTO']['6_NATURA']); ?></td>
                </tr>
                 <tr>
                    <td>7. TANTIEM, BONUS, GRATIFIKASI, JASA PRODUKSI DAN THR</td>
                    <td class="col-val"><?php echo number_format_rp($perhitungan['B_BRUTO']['7_THR_BONUS']); ?></td>
                </tr>
                <tr class="row-total">
                    <td colspan="2">8. JUMLAH PENGHASILAN BRUTO (1 S.D. 7)</td>
                    <td class="col-val"><?php echo number_format_rp($perhitungan['B_BRUTO']['8_JUMLAH_BRUTO']); ?></td>
                </tr>

                <tr>
                    <td colspan="3" style="font-weight: bold; background: #f0f0f0;">PENGURANGAN:</td>
                </tr>
                <tr>
                    <td>9. BIAYA JABATAN/BIAYA PENSIUN</td>
                    <td></td>
                    <td class="col-val"><?php echo number_format_rp($perhitungan['C_PENGURANG']['9_BIAYA_JABATAN']); ?></td>
                </tr>
                 <tr>
                    <td>10. IURAN PENSIUN ATAU IURAN THT/JHT</td>
                    <td></td>
                    <td class="col-val"><?php echo number_format_rp($perhitungan['C_PENGURANG']['10_IURAN_PENSIUN']); ?></td>
                </tr>
                <tr class="row-total">
                    <td colspan="2">11. JUMLAH PENGURANGAN (9 + 10)</td>
                    <td class="col-val"><?php echo number_format_rp($perhitungan['C_PENGURANG']['11_JUMLAH_PENGURANG']); ?></td>
                </tr>

                <tr>
                    <td colspan="3" style="font-weight: bold; background: #f0f0f0;">PENGHITUNGAN PPh PASAL 21:</td>
                </tr>
                <tr class="row-total">
                    <td colspan="2">12. JUMLAH PENGHASILAN NETO (8 - 11)</td>
                    <td class="col-val"><?php echo number_format_rp($perhitungan['D_KALKULASI_PPH']['12_NETO_SETAHUN']); ?></td>
                </tr>
                 <tr>
                    <td>13. PENGHASILAN TIDAK KENA PAJAK (PTKP)</td>
                    <td></td>
                    <td class="col-val"><?php echo number_format_rp($perhitungan['D_KALKULASI_PPH']['13_PTKP']); ?></td>
                </tr>
                <tr class="row-total">
                    <td colspan="2">14. PENGHASILAN KENA PAJAK SETAHUN (12 - 13)</td>
                    <td class="col-val"><?php echo number_format_rp($perhitungan['D_KALKULASI_PPH']['14_PKP_SETAHUN']); ?></td>
                </tr>
                <tr class="row-total">
                    <td colspan="2">15. PPh PASAL 21 TERUTANG SETAHUN (TARIF PASAL 17)</td>
                    <td class="col-val"><?php echo number_format_rp($perhitungan['D_KALKULASI_PPH']['15_PPH21_TERUTANG_SETAHUN']); ?></td>
                </tr>
                 <tr>
                    <td>16. PPh PASAL 21 YANG TELAH DIPOTONG MASA SEBELUMNYA</td>
                    <td></td>
                    <td class="col-val"><?php echo number_format_rp($perhitungan['D_KALKULASI_PPH']['16_PPH21_DIPOTONG_SEBELUMNYA']); ?></td>
                </tr>
                <tr class="row-total">
                    <td colspan="2">17. PPh PASAL 21 TELAH DIPOTONG (SESUAI METODE TER)</td>
                    <td class="col-val"><?php echo number_format_rp($perhitungan['D_KALKULASI_PPH']['17_PPH21_DIPOTONG_TER']); ?></td>
                </tr>
                <tr class="row-final">
                    <td colspan="2">18. PPh PASAL 21 KURANG / (LEBIH) BAYAR (15 - 17)</td>
                    <td class="col-val"><?php echo number_format_rp($perhitungan['D_KALKULASI_PPH']['18_PPH21_KURANG_LEBIH_BAYAR']); ?></td>
                </tr>
            </tbody>
        </table>
        
        <div class="form-section-title">C. IDENTITAS PEMOTONG</div>
        <div class="ttd-section">
            <div style="border: 1px solid #999; padding: 4px 6px;">
                <span class="grid-label">NPWP PEMOTONG</span><br>
                <span class="grid-value"><?php echo htmlspecialchars($perhitungan['C_PEMOTONG']['NPWP']); ?></span>
            </div>
             <div class="ttd-box" style="border-left: none;">
                <span class="grid-label">TANGGAL & TANDA TANGAN</span><br>
                <span class="ttd-date"><?php echo htmlspecialchars($perhitungan['C_PEMOTONG']['TANGGAL']); ?></span>
            </div>
            <div style="border: 1px solid #999; padding: 4px 6px; border-top: none;">
                <span class="grid-label">NAMA PEMOTONG</span><br>
                <span class="grid-value"><?php echo htmlspecialchars($perhitungan['C_PEMOTONG']['NAMA']); ?></span>
            </div>
            <div class="ttd-box" style="border-top: none; border-left: none;">
                <br><br>
                <span class="grid-label">(...................................................)</span>
            </div>
        </div>

    </div>
    <?php elseif (!empty($errors)): ?>
        <div style="background-color: #fff; padding: 20px; border: 1px solid #ccc; max-width: 800px; margin: auto; color: #d9534f;">
            <strong>Gagal membuat bukti potong:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php else: ?>
        <div style="background-color: #fff; padding: 20px; border: 1px solid #ccc; max-width: 800px; margin: auto;">
            Data tidak ditemukan.
        </div>
    <?php endif; ?>

    <script>
        // Script untuk otomatis memicu print
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>