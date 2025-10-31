<?php
// ajax_generate_doc_number.php

// 1. Include file koneksi database & fungsi helper
// === [PATH DIPERBAIKI] ===
require '../../includes/db.php'; // db.php sudah punya koneksi $conn dan fungsi toRoman()
// === [AKHIR PERUBAHAN] ===

// 2. Set header sebagai JSON
header('Content-Type: application/json');

// 3. Ambil parameter dari request GET
$doc_code_id = $_GET['doc_code_id'] ?? null;
$tanggal_surat = $_GET['tanggal_surat'] ?? null;

// 4. Validasi input
if (empty($doc_code_id) || $doc_code_id == '') {
    echo json_encode(['error' => 'Harap pilih Jenis Surat terlebih dahulu.']);
    exit;
}
if (empty($tanggal_surat)) {
    echo json_encode(['error' => 'Harap isi Tanggal Surat terlebih dahulu.']);
    exit;
}

// 5. Ekstrak tahun dan bulan dari tanggal surat
try {
    $tanggal_dt = new DateTime($tanggal_surat);
    $tahun = (int)$tanggal_dt->format('Y'); // Tahun YYYY, mis: 2025
    $bulan_angka = (int)$tanggal_dt->format('n'); // Bulan 1-12
    $bulan_romawi = toRoman($bulan_angka); // Panggil fungsi helper dari db.php
} catch (Exception $e) {
    echo json_encode(['error' => 'Format Tanggal Surat tidak valid.']);
    exit;
}

// 6. Query untuk mendapatkan nomor urut terakhir & kode surat
$nomor_urut_selanjutnya = 1; // Default jika belum ada nomor tahun ini
$kode_surat_string = '';

// Buka koneksi baru jika perlu (db.php mungkin sudah menutupnya)
if (!$conn->ping()) {
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->set_charset("utf8mb4");
}

try {
    // a. Cari nomor urut terakhir untuk kode & tahun ini
    // Gunakan LOCK IN SHARE MODE untuk mencegah race condition ringan saat membaca
    $stmt_last_num = $conn->prepare("
        SELECT MAX(nomor_urut) AS last_num
        FROM document_registry
        WHERE document_code_id = ? AND tahun = ?
        LOCK IN SHARE MODE
    ");
    if (!$stmt_last_num) throw new Exception("Gagal prepare last num query: " . $conn->error);
    $stmt_last_num->bind_param("is", $doc_code_id, $tahun);
    $stmt_last_num->execute();
    $result_last_num = $stmt_last_num->get_result();
    if ($row_last = $result_last_num->fetch_assoc()) {
        if ($row_last['last_num'] !== null) {
            $nomor_urut_selanjutnya = (int)$row_last['last_num'] + 1;
        }
    }
    $stmt_last_num->close();

    // b. Ambil kode surat string (misal: "EXT")
    $stmt_code = $conn->prepare("SELECT kode_surat FROM document_codes WHERE id = ?");
     if (!$stmt_code) throw new Exception("Gagal prepare code query: " . $conn->error);
    $stmt_code->bind_param("i", $doc_code_id);
    $stmt_code->execute();
    $result_code = $stmt_code->get_result();
    if ($row_code = $result_code->fetch_assoc()) {
        $kode_surat_string = $row_code['kode_surat'];
    } else {
        throw new Exception("Kode Surat tidak ditemukan.");
    }
    $stmt_code->close();

} catch (Exception $e) {
    $conn->close();
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

$conn->close();

// 7. Format nomor urut (misal: 001, 015, 123)
$nomor_urut_formatted = str_pad($nomor_urut_selanjutnya, 2, '0', STR_PAD_LEFT); // Hanya 2 digit? Sesuai contoh LPNU-04/EXT/X/2025
// Jika Anda ingin 3 digit, ganti 2 menjadi 3:
// $nomor_urut_formatted = str_pad($nomor_urut_selanjutnya, 3, '0', STR_PAD_LEFT);


// 8. Gabungkan menjadi nomor lengkap
// Format: LPNU-nomor/kode-surat/bulan-romawi/tahun
$nomor_lengkap = "LPNU-" . $nomor_urut_formatted . "/" . $kode_surat_string . "/" . $bulan_romawi . "/" . $tahun;

// 9. Kirim hasil sebagai JSON
echo json_encode([
    'success' => true,
    'nomor_lengkap' => $nomor_lengkap,
    'nomor_urut_selanjutnya' => $nomor_urut_selanjutnya // Kirim nomor urut asli (angka)
]);

?>