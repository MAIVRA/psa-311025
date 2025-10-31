<?php
// ajax_generate_nik.php

// 1. Include file koneksi database
// === [PERBAIKAN PATH: MENGGUNAKAN BASE_PATH] ===
// Memuat db.php menggunakan path absolut dari BASE_PATH
require dirname(dirname(__DIR__)) . '/includes/db.php'; // BASE_PATH.'/includes/db.php'
// === [AKHIR PERBAIKAN] ===

// 2. Set header sebagai JSON
header('Content-Type: application/json');

// 3. Ambil parameter dari request
$id_departemen = $_GET['id_departemen'] ?? null;
$tanggal_masuk = $_GET['tanggal_masuk'] ?? null;

// 4. Validasi input
if (empty($id_departemen) || $id_departemen == '') {
    echo json_encode(['error' => 'Harap pilih Departemen terlebih dahulu.']);
    exit;
}
if (empty($tanggal_masuk)) {
    echo json_encode(['error' => 'Harap isi Tanggal Masuk terlebih dahulu.']);
    exit;
}

// Buka koneksi baru jika perlu (db.php mungkin menutup koneksi sebelumnya)
if (!$conn->ping()) {
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->set_charset("utf8mb4");
}


// 5. Query ke database untuk mendapatkan info departemen
$stmt = $conn->prepare("SELECT kode_departemen, nomor_urut_terakhir FROM departemen WHERE id = ?");
if (!$stmt) {
    echo json_encode(['error' => 'Gagal mempersiapkan query: ' . $conn->error]);
    $conn->close(); // Tutup koneksi jika prepare gagal
    exit;
}

$stmt->bind_param("i", $id_departemen);
$stmt->execute();
$result = $stmt->get_result();
$departemen = $result->fetch_assoc();
$stmt->close();
// Jangan tutup koneksi dulu jika masih dipakai

// 6. Cek apakah departemen valid
if (!$departemen) {
    echo json_encode(['error' => 'Departemen tidak ditemukan.']);
    $conn->close();
    exit;
}

if (empty($departemen['kode_departemen'])) {
    echo json_encode(['error' => 'Departemen ini belum memiliki Kode NIK (2 digit). Harap set di menu Manage Struktur.']);
    $conn->close();
    exit;
}

// 7. Proses pembuatan NIK
try {
    // a. Proses Tanggal (YYMM)
    $tanggal = new DateTime($tanggal_masuk);
    $yy = $tanggal->format('y'); // 2 digit tahun, mis: "25"
    $mm = $tanggal->format('m'); // 2 digit bulan, mis: "10"

    // b. Proses Kode Departemen (KD)
    $kd = str_pad($departemen['kode_departemen'], 2, '0', STR_PAD_LEFT); // 2 digit kode, mis: "03"

    // c. Proses Nomor Urut (UUU)
    // Kita ambil nomor terakhir (mis: 7) dan tambah 1
    $nomor_urut_baru = (int)$departemen['nomor_urut_terakhir'] + 1;
    $uuu = str_pad($nomor_urut_baru, 3, '0', STR_PAD_LEFT); // 3 digit urut, mis: "008"

    // d. Gabungkan
    $nik_generated = $yy . $mm . $kd . $uuu;

    // 8. Kirim NIK sebagai JSON
    echo json_encode([
        'success' => true,
        'nik_preview' => $nik_generated,
        'nomor_urut_selanjutnya' => $nomor_urut_baru
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => 'Format Tanggal Masuk tidak valid.']);
} finally {
     // Selalu tutup koneksi di akhir
     if ($conn->ping()) {
        $conn->close();
     }
}
?>