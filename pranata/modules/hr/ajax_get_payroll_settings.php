<?php
// pranata/modules/hr/ajax_get_payroll_settings.php

require '../../includes/db.php';

// Keamanan: Pastikan hanya HR atau Admin yang bisa akses
$app_akses = $_SESSION['app_akses'] ?? 'Karyawan';
if ($app_akses != 'Admin' && $app_akses != 'HR') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
    exit;
}

date_default_timezone_set('Asia/Jakarta');
$response = ['success' => false];

// Fungsi helper untuk menghitung hari kerja (Senin-Jumat)
function getWorkingDays($startDate, $endDate) {
    $begin = new DateTime($startDate);
    $end = new DateTime($endDate);
    $end->modify('+1 day'); // Include end date

    $interval = new DateInterval('P1D');
    $daterange = new DatePeriod($begin, $interval, $end);
    
    $workingDays = 0;
    foreach($daterange as $date){
        $dayOfWeek = $date->format('N'); // 1 (Mon) - 7 (Sun)
        if($dayOfWeek < 6){ // Jika bukan Sabtu (6) atau Minggu (7)
            $workingDays++;
        }
    }
    return $workingDays;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // --- Mode POST: Simpan Pengaturan Baru ---
    $periode_mulai = $_POST['periode_mulai'] ?? null;
    $periode_akhir = $_POST['periode_akhir'] ?? null;

    if (!is_numeric($periode_mulai) || !is_numeric($periode_akhir) || $periode_mulai < 1 || $periode_mulai > 31 || $periode_akhir < 1 || $periode_akhir > 31) {
        $response['message'] = "Tanggal periode harus berupa angka antara 1 dan 31.";
    } else {
        // Logika perhitungan hari kerja
        $today = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        // Gunakan tahun 2025 sesuai data aplikasi
        $today->setDate(2025, (int)$today->format('m'), (int)$today->format('d')); 
        
        $current_day = (int)$today->format('d');
        $periode_mulai_int = (int)$periode_mulai;
        $periode_akhir_int = (int)$periode_akhir;
        
        $start_date_str = '';
        $end_date_str = '';

        // Tentukan rentang tanggal periode saat ini
        if ($current_day >= $periode_mulai_int) {
            // Periode dimulai bulan ini dan berakhir bulan depan
            $start_date = clone $today;
            $start_date->setDate((int)$start_date->format('Y'), (int)$start_date->format('m'), $periode_mulai_int);
            
            $end_date = clone $start_date;
            $end_date->modify('+1 month');
            $end_date->setDate((int)$end_date->format('Y'), (int)$end_date->format('m'), $periode_akhir_int);
        } else {
            // Periode dimulai bulan lalu dan berakhir bulan ini
            $end_date = clone $today;
            $end_date->setDate((int)$end_date->format('Y'), (int)$end_date->format('m'), $periode_akhir_int);
            
            $start_date = clone $end_date;
            $start_date->modify('-1 month');
            $start_date->setDate((int)$start_date->format('Y'), (int)$start_date->format('m'), $periode_mulai_int);
        }
        
        $start_date_str = $start_date->format('Y-m-d');
        $end_date_str = $end_date->format('Y-m-d');

        // Hitung hari kerja (Senin-Jumat)
        $jumlah_hari_kerja = getWorkingDays($start_date_str, $end_date_str);

        // Simpan ke database
        $conn->begin_transaction();
        try {
            // Simpan tanggal mulai
            $stmt_mulai = $conn->prepare("UPDATE payroll_settings SET setting_value = ? WHERE setting_key = 'periode_mulai'");
            $stmt_mulai->bind_param("s", $periode_mulai);
            $stmt_mulai->execute();
            $stmt_mulai->close();
            
            // Simpan tanggal akhir
            $stmt_akhir = $conn->prepare("UPDATE payroll_settings SET setting_value = ? WHERE setting_key = 'periode_akhir'");
            $stmt_akhir->bind_param("s", $periode_akhir);
            $stmt_akhir->execute();
            $stmt_akhir->close();
            
            // Simpan jumlah hari kerja
            $stmt_hari = $conn->prepare("UPDATE payroll_settings SET setting_value = ? WHERE setting_key = 'jumlah_hari_kerja'");
            $stmt_hari->bind_param("s", $jumlah_hari_kerja);
            $stmt_hari->execute();
            $stmt_hari->close();
            
            $conn->commit();
            $response['success'] = true;
            $response['message'] = "Periode Payroll berhasil di-update. Jumlah hari kerja untuk periode ini adalah $jumlah_hari_kerja hari.";
            $response['data'] = [
                'periode_mulai' => $periode_mulai,
                'periode_akhir' => $periode_akhir,
                'jumlah_hari_kerja' => $jumlah_hari_kerja,
                'rentang_tanggal' => date('d M Y', strtotime($start_date_str)) . ' - ' . date('d M Y', strtotime($end_date_str))
            ];
            
        } catch (Exception $e) {
            $conn->rollback();
            $response['message'] = "Gagal menyimpan data: " . $e->getMessage();
        }
    }

} else {
    // --- Mode GET: Ambil Pengaturan Saat Ini ---
    $settings = [];
    $sql_get = "SELECT setting_key, setting_value FROM payroll_settings 
                WHERE setting_key IN ('periode_mulai', 'periode_akhir', 'jumlah_hari_kerja')";
    $result = $conn->query($sql_get);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        $response['success'] = true;
        $response['settings'] = $settings;
    } else {
        $response['message'] = "Gagal mengambil data pengaturan.";
    }
}

$conn->close();
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>