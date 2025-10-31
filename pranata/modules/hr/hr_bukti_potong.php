<?php
// pranata/modules/hr/hr_bukti_potong.php

// 1. Panggil DB dan Session DULUAN
require '../../includes/db.php'; 
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Inisialisasi Variabel Awal
$errors = [];
$success_message = '';
$admin_id_login = $_SESSION['user_id'] ?? 0; // Ambil ID HR

// 3. Tentukan Filter (Gunakan $_REQUEST agar bisa menangkap GET dan POST)
$selected_karyawan_id = (int)($_REQUEST['karyawan_id'] ?? 0);
$selected_tahun = (int)($_REQUEST['tahun'] ?? date('Y'));


// === [LOGIKA POST: DIPINDAHKAN KE ATAS] ===
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'kirim_bupot') {
    
    // Keamanan: Cek hak akses sebelum memproses POST
    $app_akses_post = $_SESSION['app_akses'] ?? 'Karyawan';
    if ($app_akses_post != 'Admin' && $app_akses_post != 'HR') {
         $_SESSION['flash_error'] = "Akses ditolak.";
         // Redirect kembali ke halaman yang sama dengan filter yang aktif
         header("Location: hr_bukti_potong.php?karyawan_id=$selected_karyawan_id&tahun=$selected_tahun");
         exit;
    }

    // Ambil data dari POST (sudah ada di filter)
    $user_id_to_send = $selected_karyawan_id;
    $tahun_to_send = $selected_tahun;

    if ($user_id_to_send > 0 && $tahun_to_send > 0) {
        // Gunakan INSERT ... ON DUPLICATE KEY UPDATE untuk mengirim/memperbarui status
        $stmt_send = $conn->prepare(
            "INSERT INTO payroll_bupot_status (user_id, tahun, status, sent_by_id, sent_at) 
             VALUES (?, ?, 'Sent', ?, NOW())
             ON DUPLICATE KEY UPDATE 
                status = 'Sent', sent_by_id = VALUES(sent_by_id), sent_at = VALUES(sent_at)"
        );
        
        if ($stmt_send) {
            $stmt_send->bind_param("iii", $user_id_to_send, $tahun_to_send, $admin_id_login);
            if ($stmt_send->execute()) {
                $_SESSION['flash_message'] = "Bukti potong berhasil dikirim ke karyawan.";
            } else {
                $_SESSION['flash_error'] = "Gagal mengupdate status: " . $stmt_send->error;
            }
            $stmt_send->close();
        } else {
            $_SESSION['flash_error'] = "Gagal prepare statement: " . $conn->error;
        }
    } else {
        $_SESSION['flash_error'] = "ID Karyawan atau Tahun tidak valid.";
    }
    
    // Redirect kembali
    header("Location: hr_bukti_potong.php?karyawan_id=$user_id_to_send&tahun=$selected_tahun");
    exit; // Wajib exit
}
// === [AKHIR LOGIKA POST] ===


// 4. Set variabel halaman (SETELAH LOGIKA POST)
$page_title = "Bukti Potong PPh 21 Tahunan";
$page_active = "hr_bukti_potong"; // Sesuai yang di sidebar.php

// 5. Panggil header (SEKARANG BARU AMAN)
require '../../includes/header.php'; // $conn, $_SESSION, $user_id, $tier, $app_akses tersedia

// 6. Keamanan Halaman (GET Request)
$app_akses = $_SESSION['app_akses'] ?? 'Karyawan';
$is_admin = ($app_akses == 'Admin');
$is_hr = ($app_akses == 'HR');

if (!$is_admin && !$is_hr) {
    $_SESSION['flash_message'] = "Anda tidak memiliki hak akses untuk melihat halaman ini.";
    header("Location: ". BASE_URL. "/dashboard.php");
    exit;
}

// 7. Ambil flash messages dari session (setelah redirect)
if (isset($_SESSION['flash_message'])) {
    $success_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}
if (isset($_SESSION['flash_error'])) {
    $errors[] = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// 8. Inisialisasi Variabel Halaman
$karyawan_list = [];
$settings = [];
$perhitungan = null; // Ini akan berisi array hasil kalkulasi
$bupot_status = 'Pending'; // [BARU] Status default

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
        
        // Layer 1: 5%
        $lapisan1 = 60000000;
        if ($pkp > 0) {
            $kena_pajak = min($pkp, $lapisan1);
            $pph += (0.05 * $kena_pajak);
            $pkp -= $kena_pajak;
        }
        // Layer 2: 15%
        $lapisan2 = 190000000; // (250jt - 60jt)
        if ($pkp > 0) {
            $kena_pajak = min($pkp, $lapisan2);
            $pph += (0.15 * $kena_pajak);
            $pkp -= $kena_pajak;
        }
        // Layer 3: 25%
        $lapisan3 = 250000000; // (500jt - 250jt)
        if ($pkp > 0) {
            $kena_pajak = min($pkp, $lapisan3);
            $pph += (0.25 * $kena_pajak);
            $pkp -= $kena_pajak;
        }
        // Layer 4: 30%
        $lapisan4 = 4500000000; // (5M - 500jt)
        if ($pkp > 0) {
            $kena_pajak = min($pkp, $lapisan4);
            $pph += (0.30 * $kena_pajak);
            $pkp -= $kena_pajak;
        }
        // Layer 5: 35%
        if ($pkp > 0) {
            $pph += (0.35 * $pkp);
        }
        
        return $pph;
    }
}

// 9. Ambil data untuk filter (GET)
// $selected_karyawan_id and $selected_tahun already defined
$status_rekap_tahunan = ['total_bulan' => 0, 'total_karyawan' => 0]; 

// Ambil daftar karyawan
try {
    $stmt_karyawan = $conn->prepare("SELECT id, nama_lengkap, nik FROM users WHERE status_karyawan IN ('PKWT', 'PKWTT', 'BOD', 'BOC') AND tier != 'Admin' ORDER BY nama_lengkap ASC");
    $stmt_karyawan->execute();
    $result_karyawan = $stmt_karyawan->get_result();
    while ($row = $result_karyawan->fetch_assoc()) {
        $karyawan_list[] = $row;
    }
    $stmt_karyawan->close();
} catch (Exception $e) {
    $errors[] = "Gagal mengambil daftar karyawan: " . $e->getMessage();
}

// === [Logika Status Card] ===
try {
    $stmt_status = $conn->prepare("SELECT COUNT(DISTINCT periode_bulan) as total_bulan, COUNT(DISTINCT user_id) as total_karyawan 
                                  FROM payroll_history 
                                  WHERE periode_tahun = ?");
    if ($stmt_status) {
        $stmt_status->bind_param("i", $selected_tahun);
        $stmt_status->execute();
        $result_status = $stmt_status->get_result();
        if ($result_status->num_rows > 0) {
            $status_rekap_tahunan = $result_status->fetch_assoc();
        }
        $stmt_status->close();
    }
} catch (Exception $e) {
    $errors[] = "Gagal memeriksa status rekap payroll: " . $e->getMessage();
}
// === [AKHIR Logika Status Card] ===


// 10. Logika Kalkulasi Utama (jika form disubmit GET)
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
            'NIK_KTP' => $data_karyawan['no_ktp'] ?? $data_karyawan['nik_karyawan'], // Fallback ke NIK Karyawan jika KTP kosong
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
        
        // 6. Final Kalkulasi PPh 21 Pasal 17
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

        // 7. Ambil Data Pemotong (PT PNU)
        $stmt_company = $conn->prepare("SELECT npwp, nama_perusahaan FROM companies WHERE id = 1 LIMIT 1");
        $stmt_company->execute();
        $data_company = $stmt_company->get_result()->fetch_assoc();
        $perhitungan['C_PEMOTONG'] = [
            'NPWP' => $data_company['npwp'] ?? '76.335.061.8-613.000',
            'NAMA' => $data_company['nama_perusahaan'] ?? 'PT PUTRA NATUR UTAMA',
            'TANGGAL' => '31 Desember ' . $selected_tahun,
        ];
        $stmt_company->close();

        // === [LOGIKA BARU: Ambil Status Kirim Bupot] ===
        $stmt_bupot_status = $conn->prepare("SELECT status FROM payroll_bupot_status WHERE user_id = ? AND tahun = ?");
        if ($stmt_bupot_status) {
            $stmt_bupot_status->bind_param("ii", $selected_karyawan_id, $selected_tahun);
            $stmt_bupot_status->execute();
            $result_bupot_status = $stmt_bupot_status->get_result();
            if ($row_status = $result_bupot_status->fetch_assoc()) {
                $bupot_status = $row_status['status']; // 'Sent'
            }
            $stmt_bupot_status->close();
        }
        // === [AKHIR LOGIKA BARU] ===

        $conn->commit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $errors[] = $e->getMessage();
        $perhitungan = null;
    }
}

// 11. Panggil Sidebar
require '../../includes/sidebar.php';
?>

<style>
    .form-1721-a1-container {
        font-family: Arial, sans-serif;
        font-size: 11px;
        line-height: 1.5;
        background: #fff;
        padding: 1.5rem;
        border: 1px solid #ccc;
        max-width: 800px;
        margin: auto;
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
    .print-button {
        background-color: #1d4ed8;
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 0.375rem;
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .print-button:hover {
        background-color: #1e40af;
    }
    
    /* [BARU] Style untuk tombol kirim */
    .send-button {
        background-color: #8b5cf6; /* purple-500 */
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 0.375rem;
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.2s;
        border: none;
    }
    .send-button:hover {
        background-color: #7c3aed; /* purple-700 */
    }
    .status-sent {
        background-color: #D1FAE5; /* green-100 */
        color: #065F46; /* green-800 */
        padding: 0.5rem 1rem;
        border-radius: 0.375rem;
        font-weight: 600;
        font-size: 0.875rem;
    }

    @media print {
        body, .flex-1, main {
            background-color: white !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        .flex-1 {
            overflow: visible !important;
        }
        .flex-col, .h-screen {
            display: block !important;
            height: auto !important;
        }
        aside, header, #form-filter, #page-title, #flashModal, #logoutModal, #status-card, #kirimBupotModal { /* [MODIFIKASI] Sembunyikan modal saat print */
            display: none !important;
        }
        .card {
            box-shadow: none !important;
            border: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        .form-1721-a1-container {
            border: none;
            max-width: 100%;
            padding: 0;
        }
    }
</style>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    
    <div id="page-title" class="flex flex-wrap justify-between items-center mb-6 gap-4">
        <h1 class="text-3xl font-bold text-gray-800">Bukti Potong PPh 21 Tahunan</h1>
         
        <div class="flex items-center gap-4">
            <?php if ($perhitungan): ?>
                
                <?php if ($bupot_status == 'Sent'): ?>
                    <span class="status-sent">
                        Telah Dikirim ke Karyawan
                    </span>
                <?php else: ?>
                    <form id="form_kirim_bupot" action="hr_bukti_potong.php" method="POST">
                        <input type="hidden" name="action" value="kirim_bupot">
                        <input type="hidden" name="karyawan_id" value="<?php echo $selected_karyawan_id; ?>">
                        <input type="hidden" name="tahun" value="<?php echo $selected_tahun; ?>">
                        <button type="button" 
                                onclick="openKirimBupotModal('<?php echo htmlspecialchars(addslashes($perhitungan['A_IDENTITAS']['NAMA'])); ?>', '<?php echo $selected_tahun; ?>')"
                                class="send-button no-underline">
                            Kirim ke Karyawan
                        </button>
                    </form>
                <?php endif; ?>

                <button onclick="printBuktiPotong(<?php echo $selected_karyawan_id; ?>, <?php echo $selected_tahun; ?>)" class="print-button no-underline">
                    Cetak Bukti Potong
                </button>
            <?php endif; ?>
         </div>
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
    
    <?php if (!empty($success_message)): ?>
        <div id="form-filter" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative mb-4 max-w-full" role="alert">
            <strong class="font-bold">Sukses!</strong>
            <span class="block sm:inline"><?php echo htmlspecialchars($success_message); ?></span>
        </div>
    <?php endif; ?>


    <?php
        $total_bulan_rekap = $status_rekap_tahunan['total_bulan'];
        $is_lengkap = ($total_bulan_rekap == 12);
    ?>
    <div id="status-card" class="card mb-6 <?php echo $is_lengkap ? 'bg-green-50 border-green-300' : 'bg-yellow-50 border-yellow-300'; ?>">
        <div class="card-content">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <?php if ($is_lengkap): ?>
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <?php else: ?>
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <?php endif; ?>
                </div>
                <div class="ml-3">
                    <h3 class="text-lg font-semibold <?php echo $is_lengkap ? 'text-green-800' : 'text-yellow-800'; ?>">
                        Status Data Payroll Tahun <?php echo $selected_tahun; ?>
                    </h3>
                    <?php if ($total_bulan_rekap == 0): ?>
                        <p class="mt-1 text-yellow-700">
                            Data payroll untuk tahun <?php echo $selected_tahun; ?> **tidak ditemukan**.
                        </p>
                    <?php elseif (!$is_lengkap): ?>
                        <p class="mt-1 text-yellow-700">
                            Data payroll untuk tahun <?php echo $selected_tahun; ?> **belum lengkap** (Baru <strong><?php echo $total_bulan_rekap; ?> dari 12 bulan</strong> data).
                        </p>
                    <?php else: ?>
                         <p class="mt-1 text-green-700">
                            Data payroll untuk tahun <?php echo $selected_tahun; ?> terlihat **lengkap**.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>


    <div id="form-filter" class="card mb-6">
        <div class="card-content">
            <form action="hr_bukti_potong.php" method="GET" class="flex items-end gap-4">
                <div>
                    <label for="karyawan_id" class="form-label">Karyawan</label>
                    <select id="karyawan_id" name="karyawan_id" class="form-input" required>
                        <option value="">-- Pilih Karyawan --</option>
                        <?php foreach ($karyawan_list as $k): ?>
                        <option value="<?php echo $k['id']; ?>" <?php if ($k['id'] == $selected_karyawan_id) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($k['nama_lengkap']); ?> (<?php echo htmlspecialchars($k['nik']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="tahun" class="form-label">Tahun</label>
                    <select id="tahun" name="tahun" class="form-input" onchange="this.form.submit()">
                        <?php for ($y = 2024; $y <= 2026; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php if ($y == $selected_tahun) echo 'selected'; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <button type="submit" class="btn-primary-sm bg-blue-600 hover:bg-blue-700 h-10">
                    Tampilkan Bukti Potong
                </button>
            </form>
        </div>
    </div>

    <?php if ($perhitungan): ?>
    <div class="card">
        <div class="card-content">
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
        </div>
    </div>
    <?php endif; ?>

</main>

<div id="kirimBupotModal" class="modal-overlay hidden">
    <div class="bg-white p-6 rounded-lg shadow-xl max-w-lg w-full mx-4">
        <h3 class="text-xl font-semibold text-gray-800">Konfirmasi Pengiriman Bupot</h3>
        <div class="my-4 text-gray-700">
            <p>Anda akan mengirim Bukti Potong 1721-A1 Tahun <strong id="kirimBupotTahun" class="text-gray-900"></strong> kepada:</p>
            <p class="text-lg font-bold text-blue-600 mt-2" id="kirimBupotNamaKaryawan"></p>
            <p class="mt-4">Status akan diubah menjadi 'Sent' dan akan terlihat oleh karyawan. Lanjutkan?</p>
        </div>
        <div class="mt-6 flex justify-end space-x-3">
            <button type="button" onclick="closeModal('kirimBupotModal')" class="btn-primary-sm btn-secondary">
                Batal
            </button>
            <button type="button" id="kirimBupotConfirmButton" onclick="submitKirimBupotForm()" class="btn-primary-sm bg-purple-600 hover:bg-purple-700">
                Ya, Kirim
            </button>
        </div>
    </div>
</div>

<script>
function printBuktiPotong(karyawanId, tahun) {
    // Tentukan URL ke file print_bukti_potong.php
    const url = `<?php echo BASE_URL; ?>/modules/hr/print_bukti_potong.php?karyawan_id=${karyawanId}&tahun=${tahun}`;
    
    // Buka jendela baru
    const printWindow = window.open(url, '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes');
    
    // Fokus ke jendela baru
    if (printWindow) {
        printWindow.focus();
    } else {
        alert('Gagal membuka jendela cetak. Mohon izinkan pop-up untuk situs ini.');
    }
}

// --- [SCRIPT BARU UNTUK MODAL] ---
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if(modal) modal.classList.add('hidden');
}

// --- Logika Modal Konfirmasi Kirim (BARU) ---
function openKirimBupotModal(namaKaryawan, tahun) {
    const modal = document.getElementById('kirimBupotModal');
    if (!modal) return;
    
    document.getElementById('kirimBupotNamaKaryawan').innerText = namaKaryawan;
    document.getElementById('kirimBupotTahun').innerText = tahun;
    
    modal.classList.remove('hidden');
}

function submitKirimBupotForm() {
    const form = document.getElementById('form_kirim_bupot');
    if (form) {
        const confirmButton = document.getElementById('kirimBupotConfirmButton');
        confirmButton.disabled = true;
        confirmButton.innerText = 'Mengirim...';
        form.submit();
    }
}

// Tutup modal jika klik di luar
window.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        // [FIX] Perlu cek ID modal yang aktif
        if (event.target.id === 'kirimBupotModal' && !document.getElementById('kirimBupotModal').classList.contains('hidden')) {
             closeModal('kirimBupotModal');
        }
    }
});
// --- [AKHIR SCRIPT BARU] ---
</script>
<?php
// 8. Panggil footer
require '../../includes/footer.php';
?>