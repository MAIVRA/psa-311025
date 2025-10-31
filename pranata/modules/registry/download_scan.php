<?php
// download_scan.php

// 1. Include file koneksi database & fungsi helper
// === [PATH DIPERBAIKI] ===
require '../../includes/db.php'; // db.php sudah punya koneksi $conn dan fungsi toRoman()
// === [AKHIR PERUBAHAN] ===

// 2. Ambil ID Registrasi & ID User dari session
$registry_id = $_GET['registry_id'] ?? 0;
$user_id_login = $_SESSION['user_id'] ?? 0;
$user_tier_login = $_SESSION['tier'] ?? '';

// 3. Validasi ID Registrasi
if (empty($registry_id)) {
    die("Error: ID Registrasi Surat tidak valid.");
}

// 4. Buka koneksi baru
$conn_download = new mysqli($servername, $username, $password, $dbname);
if ($conn_download->connect_error) {
    die("Koneksi Gagal: " . $conn_download->connect_error);
}
$conn_download->set_charset("utf8mb4");

// 5. Ambil data path file & aturan akses
try {
    $stmt_get = $conn_download->prepare("
        SELECT file_path, nomor_lengkap, tipe_dokumen, akses_dokumen, akses_terbatas_level, akses_karyawan_ids, created_by_id
        FROM document_registry
        WHERE id = ?
    ");
    if (!$stmt_get) throw new Exception("Gagal prepare query: " . $conn_download->error);

    $stmt_get->bind_param("i", $registry_id);
    $stmt_get->execute();
    $result = $stmt_get->get_result();
    $registry_data = $result->fetch_assoc();
    $stmt_get->close();

    if (!$registry_data) {
        throw new Exception("Registrasi surat tidak ditemukan.");
    }
    if (empty($registry_data['file_path'])) {
         throw new Exception("File scan belum diupload untuk registrasi ini.");
    }

    // 6. Cek Hak Akses (Logika Anda sudah sempurna)
    $can_access_file = false;
    $is_direksi = ($user_tier_login == 'Direksi');
    $is_uploader = ($registry_data['created_by_id'] == $user_id_login);

    if ($registry_data['akses_dokumen'] == 'Semua') {
        // Tipe Rahasia hanya bisa oleh uploader & direksi
        if ($registry_data['tipe_dokumen'] == 'Rahasia' && !$is_uploader && !$is_direksi) {
            $can_access_file = false;
        } else {
            $can_access_file = true;
        }
    } elseif ($registry_data['akses_dokumen'] == 'Dilarang') {
        if ($is_uploader || $is_direksi) {
            $can_access_file = true;
        }
    } elseif ($registry_data['akses_dokumen'] == 'Terbatas') {
        if ($is_uploader || $is_direksi) {
            $can_access_file = true;
        } else {
            $level = $registry_data['akses_terbatas_level'];
            if ($level == 'Manager' && $user_tier_login == 'Manager') {
                $can_access_file = true;
            } elseif ($level == 'Karyawan' && !empty($registry_data['akses_karyawan_ids'])) {
                $allowed_ids = json_decode($registry_data['akses_karyawan_ids'], true);
                if (is_array($allowed_ids) && in_array($user_id_login, $allowed_ids)) {
                    $can_access_file = true;
                }
            }
        }
    }

    if (!$can_access_file) {
        throw new Exception("Anda tidak memiliki hak akses untuk mengunduh file ini.");
    }

    // 7. Siapkan Path File Fisik
    $file_path_db = $registry_data['file_path']; // Path relatif dari root (misal: uploads/scans/scan_1_time.pdf)
    
    // === [PERBAIKAN PATH: MENGGUNAKAN BASE_PATH] ===
    // Mengganti "../../" dengan konstanta BASE_PATH
    $file_physical_path = BASE_PATH . "/" . $file_path_db; 
    // === [AKHIR PERBAIKAN] ===

    // 8. Cek apakah file ada
    if (!file_exists($file_physical_path)) {
        // Tampilkan path relatif, jangan path fisik
        throw new Exception("File scan tidak ditemukan di server (Path: " . htmlspecialchars($file_path_db) . ").");
    }

    // 9. Set Header untuk Download
    header('Content-Description: File Transfer');
    header('Content-Type: application/pdf'); // Tipe file selalu PDF
    // Buat nama file download yg lebih deskriptif
    $download_filename = "Scan_" . preg_replace('/[^A-Za-z0-9\-]/', '_', $registry_data['nomor_lengkap']) . ".pdf";
    header('Content-Disposition: attachment; filename="' . basename($download_filename) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_physical_path));

    // 10. Baca dan kirim file
    ob_clean(); // Hapus output buffer sebelumnya
    flush(); // Kirim header
    readfile($file_physical_path); // Baca file dan kirim isinya

    // Tutup koneksi setelah selesai
    $conn_download->close();
    exit; // Pastikan script berhenti setelah download

} catch (Exception $e) {
    // Jika ada error, tutup koneksi dan tampilkan pesan
    if ($conn_download->ping()) {
        $conn_download->close();
    }
    // Tampilkan pesan error sederhana
    die("Error: " . htmlspecialchars($e->getMessage()));
}
?>