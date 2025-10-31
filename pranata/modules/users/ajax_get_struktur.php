<?php
// modules/users/ajax_get_struktur.php
require '../../includes/db.php';

$level = $_GET['level'] ?? 0;
$parent_id = $_GET['parent_id'] ?? 0;

$response = ['success' => false, 'data' => []];

if ($level == 2 && $parent_id > 0) { // Get Divisi by Direktorat ID
    $table = 'divisi';
    $column = 'id_direktorat';
    $nama_column = 'nama_divisi';
} elseif ($level == 3 && $parent_id > 0) { // Get Departemen by Divisi ID
    $table = 'departemen';
    $column = 'id_divisi';
    $nama_column = 'nama_departemen';
} elseif ($level == 4 && $parent_id > 0) { // Get Section by Departemen ID
    $table = 'section';
    $column = 'id_departemen';
    $nama_column = 'nama_section';
} else {
    // Jika parameter tidak valid, kirim response (jangan 'exit' tiba-tiba)
    echo json_encode($response);
    exit;
}

$stmt = $conn->prepare("SELECT id, $nama_column as nama FROM $table WHERE $column = ? ORDER BY nama ASC");
if ($stmt) {
    $stmt->bind_param("i", $parent_id);
    
    // === [PERBAIKAN LOGIKA] ===
    // Laporkan 'success: true' jika query berhasil dijalankan,
    // bahkan jika hasilnya 0 (datanya memang kosong)
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        $response['success'] = true; // Query berhasil
        $response['data'] = $data; // Kirim datanya (bisa jadi array kosong)
        
    } else {
        // Query gagal (error SQL)
        $response['message'] = "Gagal mengeksekusi query: " . $stmt->error;
    }
    // === [AKHIR PERBAIKAN] ===
    
    $stmt->close();
} else {
    // Gagal mempersiapkan statement
     $response['message'] = "Gagal mempersiapkan statement: " . $conn->error;
}

$conn->close();
echo json_encode($response);
?>