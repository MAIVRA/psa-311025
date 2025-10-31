<?php
// pranata/modules/presensi/generate_report.php

// 1. Mulai Session dan Panggil Koneksi DB (Manual)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require '../../includes/db.php'; // $conn, BASE_PATH

// 2. Panggil autoloader PhpSpreadsheet
require_once BASE_PATH . '/vendor/autoload.php';

// 3. Namespace untuk PhpSpreadsheet
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

// 4. Pengecekan Hak Akses (Wajib)
$app_akses = $_SESSION['app_akses'] ?? 'Karyawan';
$is_admin = ($app_akses == 'Admin');
$is_hr = ($app_akses == 'HR');

if (!isset($_SESSION['user_id']) || (!$is_admin && !$is_hr)) {
    die("Akses ditolak. Anda tidak memiliki izin untuk mengunduh laporan ini.");
}

// 5. Inisialisasi Filter dari GET
date_default_timezone_set('Asia/Jakarta');
$filter_tanggal_mulai = $_GET['tanggal_mulai'] ?? date('Y-m-d');
$filter_tanggal_selesai = $_GET['tanggal_selesai'] ?? date('Y-m-d');
$filter_user_id = $_GET['user_id'] ?? '';

// Ambil info pembuat laporan dari session
$generator_nama = $_SESSION['nama_lengkap'] ?? 'Sistem';
$generation_date = date('d M Y H:i');
$company_name = "PT PUTRA NATUR UTAMA";

// --- [PERUBAHAN LANGKAH 5] ---
// Ambil semua tanggal cuti massal yang disetujui dalam rentang periode
$cuti_massal_dates = [];
$stmt_cuti_all = $conn->prepare("SELECT tanggal_cuti FROM collective_leave WHERE status = 'Approved' AND tanggal_cuti BETWEEN ? AND ?");
if ($stmt_cuti_all) {
    $stmt_cuti_all->bind_param("ss", $filter_tanggal_mulai, $filter_tanggal_selesai);
    $stmt_cuti_all->execute();
    $result_cuti_all = $stmt_cuti_all->get_result();
    while ($row = $result_cuti_all->fetch_assoc()) {
        $cuti_massal_dates[$row['tanggal_cuti']] = true; // Simpan sebagai map untuk lookup cepat
    }
    $stmt_cuti_all->close();
}

// Fungsi helper untuk menghitung hari kerja (Senin-Jumat)
if (!function_exists('getWorkingDays')) {
    function getWorkingDays($startDate, $endDate) {
        $begin = new DateTime($startDate);
        $end = new DateTime($endDate);
        $end->modify('+1 day');
        $interval = new DateInterval('P1D');
        $daterange = new DatePeriod($begin, $interval, $end);
        $workingDays = 0;
        foreach($daterange as $date){
            $dayOfWeek = $date->format('N');
            if($dayOfWeek < 6){ $workingDays++; }
        }
        return $workingDays;
    }
}
// --- [AKHIR PERUBAHAN LANGKAH 5] ---


// 6. Buat Objek Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Laporan Presensi');

\PhpOffice\PhpSpreadsheet\Settings::setLocale('id');

// 7. Styling Dasar
$style_header = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
];
$style_summary_label = [
    'font' => ['bold' => true],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
];
$style_all_borders = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]]
];
$style_libur = ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']]];
$style_absen = ['font' => ['color' => ['rgb' => 'DC2626']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEE2E2']]];
$style_hadir = ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D1FAE5']]];
$style_sakit = ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']]];
$style_footer = [
    'font' => ['italic' => true, 'size' => 9],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true]
];

// Inisialisasi baris (dimulai dari 1)
$row_index = 1;

// =========================================================================
// 8. LOGIKA UTAMA: SINGLE USER (Laporan Kalender) vs GLOBAL (Laporan Rangkuman)
// =========================================================================

if (!empty($filter_user_id)) {
    
    // ====================================
    // --- CABANG SINGLE USER (Ada Perubahan) ---
    // ====================================
    
    $user_info = null;
    $stmt_user = $conn->prepare(
        "SELECT u.nama_lengkap, u.nik, u.nama_jabatan, d.nama_departemen, a.nama_lengkap as nama_atasan 
         FROM users u 
         LEFT JOIN departemen d ON u.id_departemen = d.id 
         LEFT JOIN users a ON u.atasan_id = a.id 
         WHERE u.id = ?"
    );
    if ($stmt_user) {
        $stmt_user->bind_param("i", $filter_user_id); $stmt_user->execute();
        $result_user = $stmt_user->get_result();
        if ($result_user->num_rows > 0) { $user_info = $result_user->fetch_assoc(); }
        $stmt_user->close();
    }

    $sheet->mergeCells('A1:D1');
    $sheet->setCellValue('A1', 'Rangkuman Presensi Karyawan - ' . $company_name);
    $sheet->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 16]]);
    $row_index++;
    $sheet->setCellValue('A'.$row_index, 'Nama Karyawan');
    $sheet->setCellValue('B'.$row_index, $user_info ? $user_info['nama_lengkap'] : 'N/A');
    $sheet->getStyle('A'.$row_index)->applyFromArray($style_summary_label);
    $sheet->mergeCells('B'.$row_index.':D'.$row_index);
    $row_index++;
    $sheet->setCellValue('A'.$row_index, 'NIK');
    $sheet->getCell('B'.$row_index)->setValueExplicit($user_info ? $user_info['nik'] : 'N/A', DataType::TYPE_STRING);
    $sheet->getStyle('A'.$row_index)->applyFromArray($style_summary_label);
    $sheet->mergeCells('B'.$row_index.':D'.$row_index);
    $row_index++;
    $sheet->setCellValue('A'.$row_index, 'Jabatan');
    $sheet->setCellValue('B'.$row_index, $user_info ? ($user_info['nama_jabatan'] ?? '-') : 'N/A');
    $sheet->getStyle('A'.$row_index)->applyFromArray($style_summary_label);
    $sheet->mergeCells('B'.$row_index.':D'.$row_index);
    $row_index++;
    $sheet->setCellValue('A'.$row_index, 'Departemen');
    $sheet->setCellValue('B'.$row_index, $user_info ? ($user_info['nama_departemen'] ?? '-') : 'N/A');
    $sheet->getStyle('A'.$row_index)->applyFromArray($style_summary_label);
    $sheet->mergeCells('B'.$row_index.':D'.$row_index);
    $row_index++;
    $sheet->setCellValue('A'.$row_index, 'Atasan Langsung');
    $sheet->setCellValue('B'.$row_index, $user_info ? ($user_info['nama_atasan'] ?? '-') : 'N/A');
    $sheet->getStyle('A'.$row_index)->applyFromArray($style_summary_label);
    $sheet->mergeCells('B'.$row_index.':D'.$row_index);
    $row_index++;
    $sheet->setCellValue('A'.$row_index, 'Periode');
    $sheet->setCellValue('B'.$row_index, date('d M Y', strtotime($filter_tanggal_mulai)) . ' s/d ' . date('d M Y', strtotime($filter_tanggal_selesai)));
    $sheet->getStyle('A'.$row_index)->applyFromArray($style_summary_label);
    $sheet->mergeCells('B'.$row_index.':D'.$row_index);
    $row_index++;
    $row_index++;
    $sheet->setCellValue('A'.$row_index, 'Total Hari Kerja (Senin-Jumat)');
    $sheet->getStyle('A'.$row_index)->applyFromArray($style_summary_label);
    $summary_row_hari_kerja = $row_index;
    $row_index++;
    $sheet->setCellValue('A'.$row_index, 'Total Hadir (WFO/WFH/Dinas)');
    $sheet->getStyle('A'.$row_index)->applyFromArray($style_summary_label);
    $summary_row_hadir = $row_index;
    $row_index++;
    $sheet->setCellValue('A'.$row_index, 'Total Sakit');
    $sheet->getStyle('A'.$row_index)->applyFromArray($style_summary_label);
    $summary_row_sakit = $row_index;
    $row_index++;
    $sheet->setCellValue('A'.$row_index, 'Total Absen');
    $sheet->getStyle('A'.$row_index)->applyFromArray($style_summary_label);
    $summary_row_absen = $row_index;
    $row_index++;
    $row_index++;
    $start_table_row = $row_index;

    $presensi_map = [];
    $stmt_data = $conn->prepare("SELECT tanggal_presensi, waktu_presensi, status_kerja, lokasi_kerja, lokasi_wfh FROM presensi WHERE user_id = ? AND tanggal_presensi BETWEEN ? AND ?");
    if ($stmt_data) {
        $stmt_data->bind_param("iss", $filter_user_id, $filter_tanggal_mulai, $filter_tanggal_selesai);
        $stmt_data->execute();
        $result_data = $stmt_data->get_result();
        while ($row = $result_data->fetch_assoc()) { $presensi_map[$row['tanggal_presensi']] = $row; }
        $stmt_data->close();
    }

    $headers = ['Tanggal', 'Hari', 'Status', 'Waktu Presensi', 'Lokasi / Keterangan'];
    $sheet->fromArray($headers, NULL, 'A'.$row_index);
    $sheet->getStyle('A'.$row_index.':E'.$row_index)->applyFromArray($style_header);
    $row_index++;

    $begin = new DateTime($filter_tanggal_mulai);
    $end = new DateTime($filter_tanggal_selesai);
    $end->modify('+1 day');
    $interval = new DateInterval('P1D');
    $daterange = new DatePeriod($begin, $interval, $end);
    
    // --- [PERUBAHAN LANGKAH 5] ---
    // Hitung Hari Kerja Bersih
    $total_hari_kerja_periode = getWorkingDays($filter_tanggal_mulai, $filter_tanggal_selesai);
    $jumlah_cuti_massal_hari_kerja = 0;
    foreach ($cuti_massal_dates as $tgl_cuti_str => $val) {
        $day_of_week_cuti = date('N', strtotime($tgl_cuti_str));
        if ($day_of_week_cuti < 6) { $jumlah_cuti_massal_hari_kerja++; }
    }
    $total_hari_kerja_bersih = $total_hari_kerja_periode - $jumlah_cuti_massal_hari_kerja;

    // Inisialisasi counter baru
    $total_hadir = 0; $total_sakit = 0; $total_absen = 0;
    // --- [AKHIR PERUBAHAN] ---
    
    setlocale(LC_TIME, 'id_ID.UTF-8', 'Indonesian_Indonesia.1252', 'Indonesian');

    foreach($daterange as $day) {
        $date_string = $day->format('Y-m-d');
        $day_name = strftime('%A', $day->getTimestamp());
        $day_of_week = $day->format('N');
        $tanggal_cell = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($date_string);
        $status = ''; $waktu = '-'; $keterangan = '-'; $style = null;
        
        if (isset($presensi_map[$date_string])) {
            $data = $presensi_map[$date_string];
            $status = $data['status_kerja'];
            $waktu = date('H:i:s', strtotime($data['waktu_presensi']));
            if ($data['status_kerja'] == 'WFO') $keterangan = $data['lokasi_kerja'];
            elseif ($data['status_kerja'] == 'WFH') $keterangan = $data['lokasi_wfh'];
            else $keterangan = $data['status_kerja'];
            if (in_array($status, ['WFO', 'WFH', 'Dinas'])) { $total_hadir++; $style = $style_hadir; } 
            elseif ($status == 'Sakit') { $total_sakit++; $style = $style_sakit; }
        
        // --- [PERUBAHAN LANGKAH 5] ---
        } elseif (isset($cuti_massal_dates[$date_string])) {
            // Ini adalah hari Cuti Massal (Poin 7)
            $status = 'CUTI MASSAL';
            $keterangan = 'Cuti Massal (Disetujui)';
            $style = $style_libur; // Pakai style abu-abu
        // --- [AKHIR PERUBAHAN] ---
        
        } elseif ($day_of_week >= 6) {
            $status = 'LIBUR'; $keterangan = 'Weekend'; $style = $style_libur;
        } else {
            // Ini sekarang hanya ter-trigger jika BUKAN presensi, BUKAN cuti massal, dan BUKAN weekend
            $status = 'ABSEN'; $keterangan = 'Tidak ada data presensi'; $style = $style_absen;
            $total_absen++; // Hitung absen
        }
        
        $sheet->fromArray([$tanggal_cell, $day_name, $status, $waktu, $keterangan], NULL, 'A'.$row_index);
        $sheet->getStyle('A'.$row_index)->getNumberFormat()->setFormatCode('d MMMM YYYY');
        if ($style) { $sheet->getStyle('A'.$row_index.':E'.$row_index)->applyFromArray($style); }
        $row_index++;
    }
    
    $end_table_row = $row_index - 1;
    $sheet->getStyle('A'.$start_table_row.':E'.$end_table_row)->applyFromArray($style_all_borders);
    
    // --- [PERUBAHAN LANGKAH 5] ---
    // Isi nilai rangkuman dengan data yang sudah dihitung
    $sheet->setCellValue('B'.$summary_row_hari_kerja, $total_hari_kerja_bersih);
    $sheet->setCellValue('B'.$summary_row_hadir, $total_hadir);
    $sheet->setCellValue('B'.$summary_row_sakit, $total_sakit);
    $sheet->setCellValue('B'.$summary_row_absen, $total_absen);
    // --- [AKHIR PERUBAHAN] ---
    
    $sheet->getColumnDimension('A')->setWidth(30);
    $sheet->getColumnDimension('B')->setWidth(30);
    $sheet->getColumnDimension('C')->setWidth(15);
    $sheet->getColumnDimension('D')->setWidth(15);
    $sheet->getColumnDimension('E')->setWidth(40);
    $last_column = 'E';
    
} else {

    // ====================================
    // --- CABANG GLOBAL (Ada Perubahan) ---
    // ====================================
    
    // --- [PERUBAHAN LANGKAH 5] ---
    // Hitung total hari kerja (semua weekday)
    $total_hari_kerja_periode = getWorkingDays($filter_tanggal_mulai, $filter_tanggal_selesai);
    
    // Hitung berapa hari cuti massal yang JATUH PADA HARI KERJA
    // Kita gunakan $cuti_massal_dates yang sudah diambil di atas
    $jumlah_cuti_massal_hari_kerja = 0;
    foreach ($cuti_massal_dates as $tgl_cuti_str => $val) {
        $day_of_week_cuti = date('N', strtotime($tgl_cuti_str));
        if ($day_of_week_cuti < 6) { // Jika Senin-Jumat
            $jumlah_cuti_massal_hari_kerja++;
        }
    }
    
    // Ini adalah total hari kerja bersih
    $total_hari_kerja_bersih = $total_hari_kerja_periode - $jumlah_cuti_massal_hari_kerja;
    // --- [AKHIR PERUBAHAN] ---

    // Tulis Judul
    $sheet->mergeCells('A1:I1');
    $sheet->setCellValue('A1', 'Rangkuman Presensi Karyawan (Global) - ' . $company_name);
    $sheet->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 16]]);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $row_index++;
    
    // Tulis Periode
    $sheet->mergeCells('A2:I2');
    $sheet->setCellValue('A2', 'Periode Pelaporan: ' . date('d M Y', strtotime($filter_tanggal_mulai)) . ' s/d ' . date('d M Y', strtotime($filter_tanggal_selesai)));
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $row_index++;
    $row_index++;
    
    $start_table_row = $row_index;

    // Tulis Header Tabel Rangkuman
    $headers = [
        'No.', 'Nama', 'Jabatan', 'Departemen', 'Atasan', 
        'Jumlah Hari Kerja', 'Jumlah Hadir (WFO/WFH/Dinas)', 'Jumlah Sakit', 'Jumlah Absen'
    ];
    $sheet->fromArray($headers, NULL, 'A'.$row_index);
    $sheet->getStyle('A'.$row_index.':I'.$row_index)->applyFromArray($style_header);
    $row_index++;

    // --- Strategi Pengambilan Data (Efisien) ---

    // 1. Ambil semua data presensi dalam rentang (GROUP BY user_id dan status)
    $presensi_counts = [];
    $sql_counts = "SELECT user_id, status_kerja, COUNT(DISTINCT tanggal_presensi) as total 
                   FROM presensi 
                   WHERE tanggal_presensi BETWEEN ? AND ?
                   GROUP BY user_id, status_kerja";
    $stmt_counts = $conn->prepare($sql_counts);
    if ($stmt_counts) {
        $stmt_counts->bind_param("ss", $filter_tanggal_mulai, $filter_tanggal_selesai);
        $stmt_counts->execute();
        $result_counts = $stmt_counts->get_result();
        while ($row = $result_counts->fetch_assoc()) {
            $presensi_counts[$row['user_id']][$row['status_kerja']] = $row['total'];
        }
        $stmt_counts->close();
    }

    // 2. Ambil semua data karyawan aktif
    $karyawan_list = [];
    $sql_users = "SELECT u.id, u.nama_lengkap, u.nama_jabatan, d.nama_departemen, a.nama_lengkap as nama_atasan 
                  FROM users u 
                  LEFT JOIN departemen d ON u.id_departemen = d.id 
                  LEFT JOIN users a ON u.atasan_id = a.id 
                  WHERE u.status_karyawan IN ('PKWT', 'PKWTT', 'BOD', 'BOC')
                  ORDER BY u.nama_lengkap ASC";
    $result_users = $conn->query($sql_users);
    if ($result_users) {
        $karyawan_list = $result_users->fetch_all(MYSQLI_ASSOC);
    }

    // 3. Loop data KARYAWAN
    $nomor = 1;
    foreach ($karyawan_list as $karyawan) {
        $user_id = $karyawan['id'];
        
        $counts = $presensi_counts[$user_id] ?? [];
        
        $hadir_wfo = $counts['WFO'] ?? 0;
        $hadir_wfh = $counts['WFH'] ?? 0;
        $hadir_dinas = $counts['Dinas'] ?? 0;
        $total_sakit = $counts['Sakit'] ?? 0;
        
        $total_hadir = $hadir_wfo + $hadir_wfh + $hadir_dinas;
        
        // --- [PERUBAHAN LANGKAH 5] ---
        // Hitung absen berdasarkan hari kerja BERSIH
        $total_absen = $total_hari_kerja_bersih - $total_hadir - $total_sakit;
        if ($total_absen < 0) $total_absen = 0; // Tidak boleh negatif
        // --- [AKHIR PERUBAHAN] ---

        // Tulis baris ke Excel (manual setValue)
        $sheet->getCell('A'.$row_index)->setValue($nomor++);
        $sheet->getCell('B'.$row_index)->setValue($karyawan['nama_lengkap']);
        $sheet->getCell('C'.$row_index)->setValue($karyawan['nama_jabatan'] ?? '-');
        $sheet->getCell('D'.$row_index)->setValue($karyawan['nama_departemen'] ?? '-');
        $sheet->getCell('E'.$row_index)->setValue($karyawan['nama_atasan'] ?? '-');
        $sheet->getCell('F'.$row_index)->setValue($total_hari_kerja_bersih); // Tampilkan hari kerja bersih
        $sheet->getCell('G'.$row_index)->setValue($total_hadir);
        $sheet->getCell('H'.$row_index)->setValue($total_sakit);
        $sheet->getCell('I'.$row_index)->setValue($total_absen);
        
        $row_index++;
    }
    
    // Terapkan border
    $end_table_row = $row_index - 1;
    if ($end_table_row >= $start_table_row) {
        $sheet->getStyle('A'.$start_table_row.':I'.$end_table_row)->applyFromArray($style_all_borders);
    }
    
    // Set lebar kolom manual
    $sheet->getColumnDimension('A')->setWidth(5);   // No
    $sheet->getColumnDimension('B')->setWidth(30);  // Nama
    $sheet->getColumnDimension('C')->setWidth(30);  // Jabatan
    $sheet->getColumnDimension('D')->setWidth(30);  // Departemen
    $sheet->getColumnDimension('E')->setWidth(30);  // Atasan
    $sheet->getColumnDimension('F')->setWidth(18);  // Hari Kerja
    $sheet->getColumnDimension('G')->setWidth(18);  // Hadir
    $sheet->getColumnDimension('H')->setWidth(18);  // Sakit
    $sheet->getColumnDimension('I')->setWidth(18);  // Absen
    
    $last_column = 'I';
}

$conn->close();

// =========================================================================
// 10. Tulis Footer Branding
// =========================================================================
$row_index++;
$footer_text = "Laporan ini dibuat menggunakan PRANATA SUPER APPS oleh $generator_nama pada tanggal $generation_date, secara online sehingga tidak memerlukan ada tanda tangan";
$sheet->mergeCells('A'.$row_index.':'.$last_column.$row_index);
$sheet->setCellValue('A'.$row_index, $footer_text);
$sheet->getStyle('A'.$row_index)->applyFromArray($style_footer);
$sheet->getRowDimension($row_index)->setRowHeight(30);


// =========================================================================
// 11. Output File ke Browser
// =========================================================================
$filename = 'Laporan_Presensi_' . ($user_info ? $user_info['nama_lengkap'] : 'Global_Rangkuman') . '_' . date('Ymd') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>