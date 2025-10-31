<?php
// pranata/modules/hr/ajax_get_potongan.php
// Endpoint ini HANYA mengambil (GET) daftar potongan yang sudah ada.

// 1. Panggil koneksi DB dan mulai session
require '../../includes/db.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Keamanan Endpoint
header('Content-Type: application/json');
$app_akses = $_SESSION['app_akses'] ?? 'Karyawan';
if ($app_akses != 'HR' && $app_akses != 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

// 3. Validasi Input
$user_id = (int)($_GET['user_id'] ?? 0);
$tahun = (int)($_GET['tahun'] ?? 0);
$bulan = (int)($_GET['bulan'] ?? 0);

if (empty($user_id) || empty($tahun) || empty($bulan)) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap (user_id, tahun, bulan).']);
    exit;
}

// 4. Query data potongan
$potongan_list = [];
$response = ['success' => false];

try {
    $stmt = $conn->prepare("SELECT id, jenis_potongan, jumlah, keterangan 
                            FROM payroll_potongan_lain 
                            WHERE user_id = ? AND periode_tahun = ? AND periode_bulan = ?
                            ORDER BY id ASC");
    $stmt->bind_param("iii", $user_id, $tahun, $bulan);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $potongan_list[] = $row;
        }
        $response['success'] = true;
        $response['potongan'] = $potongan_list; // Kirim array (bisa jadi kosong)
    } else {
        $response['message'] = 'Query error: ' . $stmt->error;
    }
    $stmt->close();
    
} catch (Exception $e) {
    $response['message'] = 'Exception: ' . $e->getMessage();
}

$conn->close();

// 5. Kembalikan hasil sebagai JSON
echo json_encode($response);
exit;
?>