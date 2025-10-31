<?php
// pranata/modules/hr/ajax_get_payroll_detail.php
// Endpoint ini HANYA mengembalikan HTML snippet untuk modal

// 1. Panggil koneksi DB dan mulai session
require '../../includes/db.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Helper Functions (Karena file ini tidak me-load header.php)
if (!function_exists('formatAngka')) {
    function formatAngka($angka, $prefix = 'Rp ') {
        return $prefix . number_format($angka, 0, ',', '.');
    }
}

// 3. Keamanan Endpoint
$app_akses = $_SESSION['app_akses'] ?? 'Karyawan';
if ($app_akses != 'HR' && $app_akses != 'Admin') {
    echo '<p class="text-red-500 p-4">Akses ditolak. Anda tidak memiliki izin untuk melihat data ini.</p>';
    exit;
}

// 4. Validasi Input
if (!isset($_GET['history_id']) || !filter_var($_GET['history_id'], FILTER_VALIDATE_INT)) {
    echo '<p class="text-red-500 p-4">ID Riwayat Gaji tidak valid.</p>';
    exit;
}
$history_id = (int)$_GET['history_id'];

// 5. Query data detail
$header_data = null;
$pendapatan_items = [];
$potongan_items = [];

try {
    // 5.1. Ambil data header (total) dari payroll_history
    $sql_header = "SELECT 
                        ph.*, 
                        u.nama_lengkap, 
                        u.nik,
                        DATE_FORMAT(ph.tanggal_mulai_periode, '%d %b %Y') AS tgl_mulai_fmt,
                        DATE_FORMAT(ph.tanggal_selesai_periode, '%d %b %Y') AS tgl_selesai_fmt
                   FROM payroll_history ph
                   JOIN users u ON ph.user_id = u.id
                   WHERE ph.id = ?";
    $stmt_header = $conn->prepare($sql_header);
    $stmt_header->bind_param("i", $history_id);
    $stmt_header->execute();
    $result_header = $stmt_header->get_result();
    
    if ($result_header->num_rows == 0) {
        throw new Exception("Data riwayat payroll tidak ditemukan.");
    }
    $header_data = $result_header->fetch_assoc();
    $stmt_header->close();

    // 5.2. Ambil data rincian dari payroll_history_detail
    $sql_detail = "SELECT * FROM payroll_history_detail 
                   WHERE payroll_history_id = ? 
                   ORDER BY tipe ASC, id ASC";
    $stmt_detail = $conn->prepare($sql_detail);
    $stmt_detail->bind_param("i", $history_id);
    $stmt_detail->execute();
    $result_detail = $stmt_detail->get_result();
    
    while ($row = $result_detail->fetch_assoc()) {
        if ($row['tipe'] == 'Pendapatan') {
            $pendapatan_items[] = $row;
        } else {
            $potongan_items[] = $row;
        }
    }
    $stmt_detail->close();
    
} catch (Exception $e) {
    $conn->close();
    echo '<p class="text-red-500 p-4">Gagal mengambil data: ' . $e->getMessage() . '</p>';
    exit;
}

$conn->close();

// 6. Generate HTML Snippet
// (Menggunakan style class 'profile-label' dan 'profile-data' dari view_profile.php agar konsisten)
?>
<style>
    .profile-label {
        font-size: 0.875rem; /* text-sm */
        font-weight: 500; /* font-medium */
        color: #4b5563; /* gray-600 */
    }
    .profile-data {
        margin-top: 0.25rem; /* mt-1 */
        font-size: 1rem; /* text-base */
        color: #1f2937; /* gray-900 */
        font-weight: 600; /* font-semibold */
    }
    .detail-item {
        display: flex;
        justify-content: space-between;
        font-size: 0.875rem;
    }
    .detail-item-deskripsi {
        font-size: 0.75rem;
        color: #6b7280;
        padding-left: 1rem;
    }
</style>

<div class="space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-4 p-4 bg-gray-50 rounded-lg border">
        <div>
            <span class="profile-label">Nama Karyawan</span>
            <p class="profile-data"><?php echo htmlspecialchars($header_data['nama_lengkap']); ?></p>
        </div>
        <div>
            <span class="profile-label">NIK</span>
            <p class="profile-data"><?php echo htmlspecialchars($header_data['nik']); ?></p>
        </div>
        <div>
            <span class="profile-label">Periode Gaji</span>
            <p class="profile-data"><?php echo htmlspecialchars($header_data['tgl_mulai_fmt']) . ' - ' . htmlspecialchars($header_data['tgl_selesai_fmt']); ?></p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
        
        <div class="space-y-3 p-4 border rounded-md">
            <h4 class="text-lg font-semibold text-gray-800 border-b pb-2">Pendapatan</h4>
            <div class="space-y-2">
                <?php foreach ($pendapatan_items as $item): ?>
                    <div class="detail-item">
                        <span class="text-gray-700"><?php echo htmlspecialchars($item['komponen']); ?></span>
                        <span class="font-medium"><?php echo formatAngka($item['jumlah']); ?></span>
                    </div>
                    <?php if (!empty($item['deskripsi'])): ?>
                        <p class="detail-item-deskripsi italic"><?php echo htmlspecialchars($item['deskripsi']); ?></p>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
            <hr>
            
            <div class="detail-item pt-2">
                <strong class="text-base text-gray-900">Total Gross Income (A)</strong>
                <strong class="text-base text-gray-900"><?php echo formatAngka($header_data['total_gross_income']); ?></strong>
            </div>
        </div>
        
        <div class="space-y-3 p-4 border rounded-md">
            <h4 class="text-lg font-semibold text-gray-800 border-b pb-2">Potongan</h4>
            <div class="space-y-2">
                <?php foreach ($potongan_items as $item): ?>
                    <div class="detail-item">
                        <span class="text-gray-700"><?php echo htmlspecialchars($item['komponen']); ?></span>
                        <span class="font-medium text-red-600"><?php echo formatAngka($item['jumlah'], '(Rp '); ?>)</span>
                    </div>
                    <?php if (!empty($item['deskripsi'])): ?>
                        <p class="detail-item-deskripsi italic"><?php echo htmlspecialchars($item['deskripsi']); ?></p>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
            <hr>
            
            <?php 
                $total_potongan_all = $header_data['total_potongan_bpjs'] + 
                                      $header_data['total_potongan_pph21'] + 
                                      $header_data['total_potongan_lainnya'];
            ?>
            <div class="detail-item pt-2">
                <strong class="text-base text-gray-900">Total Potongan (B)</strong>
                <strong class="text-base text-red-600"><?php echo formatAngka($total_potongan_all, '(Rp '); ?>)</strong>
            </div>
        </div>
        
    </div>
    
    <div class="p-4 bg-green-50 rounded-lg border border-green-200">
        <div class="flex justify-between items-center">
            <span class="text-xl font-bold text-green-800">Take Home Pay (A - B)</span>
            <span class="text-2xl font-bold text-green-800"><?php echo formatAngka($header_data['take_home_pay']); ?></span>
        </div>
    </div>
</div>