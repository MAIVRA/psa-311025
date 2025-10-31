<?php
// pranata/modules/hr/ajax_get_karyawan_gaji.php

require '../../includes/db.php';

// Cek hak akses (keamanan)
$app_akses = $_SESSION['app_akses'] ?? 'Karyawan';
if ($app_akses != 'Admin' && $app_akses != 'HR') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
    exit;
}

$user_id = $_GET['user_id'] ?? 0;
$response = ['success' => false, 'gaji' => null];

if ($user_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM payroll_master_gaji WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $response['success'] = true;
            $response['gaji'] = $result->fetch_assoc();
        } else {
            // Sukses, tapi data gaji belum ada (masih kosong)
            $response['success'] = true; 
            $response['gaji'] = null; // Kirim null agar form diisi default 0
        }
    } else {
        $response['message'] = 'Query error: ' . $stmt->error;
    }
    $stmt->close();
} else {
    $response['message'] = 'User ID tidak valid.';
}

$conn->close();
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>