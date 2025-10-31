<?php
// pranata/modules/hr/ajax_save_potongan.php
// Endpoint ini menangani (POST) penambahan, update, atau penghapusan potongan lain.

// 1. Panggil koneksi DB dan mulai session
require '../../includes/db.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Keamanan Endpoint
header('Content-Type: application/json');
$app_akses = $_SESSION['app_akses'] ?? 'Karyawan';
$admin_id = $_SESSION['user_id'] ?? 0; // ID HR/Admin yg login

if (($app_akses != 'HR' && $app_akses != 'Admin') || $admin_id == 0) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

// 3. Pastikan ini adalah request POST
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    echo json_encode(['success' => false, 'message' => 'Metode request tidak valid.']);
    exit;
}

// 4. Inisialisasi
$response = ['success' => false];

try {
    $action = $_POST['action'] ?? '';

    // --- AKSI 1: TAMBAH / UPDATE POTONGAN ---
    if ($action == 'add_potongan') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $tahun = (int)($_POST['tahun'] ?? 0);
        $bulan = (int)($_POST['bulan'] ?? 0);
        $jenis_potongan = trim($_POST['jenis_potongan'] ?? '');
        // Bersihkan format rupiah
        $jumlah = (int)str_replace('.', '', $_POST['jumlah'] ?? 0);

        if (empty($user_id) || empty($tahun) || empty($bulan) || empty($jenis_potongan) || $jumlah <= 0) {
            throw new Exception("Data tidak lengkap. Pastikan Karyawan, Periode, Jenis Potongan, dan Jumlah (lebih dari 0) terisi.");
        }

        // Gunakan INSERT ... ON DUPLICATE KEY UPDATE
        // Ini akan meng-update 'jumlah' jika 'jenis_potongan' yang sama di-input lagi untuk user/periode yg sama
        $sql = "INSERT INTO payroll_potongan_lain 
                    (user_id, periode_tahun, periode_bulan, jenis_potongan, jumlah, created_by_id)
                VALUES 
                    (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    jumlah = VALUES(jumlah),
                    keterangan = VALUES(keterangan),
                    created_by_id = VALUES(created_by_id)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiisis", $user_id, $tahun, $bulan, $jenis_potongan, $jumlah, $admin_id);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = "Potongan '" . htmlspecialchars($jenis_potongan) . "' berhasil disimpan.";
        } else {
            throw new Exception("Gagal menyimpan ke database: " . $stmt->error);
        }
        $stmt->close();

    // --- AKSI 2: HAPUS POTONGAN ---
    } elseif ($action == 'delete_potongan') {
        $potongan_id = (int)($_POST['potongan_id'] ?? 0);
        
        if ($potongan_id <= 0) {
            throw new Exception("ID Potongan tidak valid.");
        }

        $stmt = $conn->prepare("DELETE FROM payroll_potongan_lain WHERE id = ?");
        $stmt->bind_param("i", $potongan_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $response['success'] = true;
                $response['message'] = "Potongan berhasil dihapus.";
            } else {
                throw new Exception("Potongan tidak ditemukan atau sudah dihapus.");
            }
        } else {
            throw new Exception("Gagal menghapus: " . $stmt->error);
        }
        $stmt->close();
        
    } else {
        throw new Exception("Aksi tidak dikenal.");
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

$conn->close();

// 5. Kembalikan hasil sebagai JSON
echo json_encode($response);
exit;
?>