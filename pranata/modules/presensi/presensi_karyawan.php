<?php
// pranata/modules/presensi/presensi_karyawan.php

// 1. Set variabel khusus untuk halaman ini
$page_title = "Laporan Presensi Karyawan";
$page_active = "presensi_report"; // Sesuai dengan sidebar.php

// 2. Panggil header
require '../../includes/header.php';

// 3. Pengecekan Hak Akses (Sangat Penting)
// Hanya HR dan Admin yang boleh mengakses halaman ini
$app_akses = $_SESSION['app_akses'] ?? 'Karyawan';
$is_admin = ($app_akses == 'Admin');
$is_hr = ($app_akses == 'HR');

if (!$is_admin && !$is_hr) {
    // Jika tidak punya hak, tendang ke dashboard dengan pesan error
    $_SESSION['flash_message'] = "Anda tidak memiliki hak akses untuk melihat halaman ini.";
    header("Location: " . BASE_URL . "/dashboard.php");
    exit;
}

// 4. Inisialisasi Filter dan Query
date_default_timezone_set('Asia/Jakarta');
$errors = [];

// Ambil nilai filter dari URL (GET)
// Default rentang tanggal adalah hari ini
$filter_tanggal_mulai = $_GET['tanggal_mulai'] ?? date('Y-m-d');
$filter_tanggal_selesai = $_GET['tanggal_selesai'] ?? date('Y-m-d');
$filter_user_id = $_GET['user_id'] ?? '';
$filter_status_kerja = $_GET['status_kerja'] ?? '';

// --- [PENAMBAHAN] Logika Pagination ---
$limit = 25; // Jumlah entri per halaman
$page = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;
$total_results = 0;
$total_pages = 0;

// [BARU] Buat query string untuk link pagination agar filter tidak hilang
$filter_query_string = http_build_query([
    'tanggal_mulai' => $filter_tanggal_mulai,
    'tanggal_selesai' => $filter_tanggal_selesai,
    'user_id' => $filter_user_id,
    'status_kerja' => $filter_status_kerja
]);
// --- [AKHIR PENAMBAHAN] ---


// Ambil daftar karyawan untuk dropdown filter
$users_list = [];
$sql_users = "SELECT id, nama_lengkap, nik FROM users 
              WHERE status_karyawan IN ('PKWT', 'PKWTT', 'BOD', 'BOC') 
              ORDER BY nama_lengkap ASC";
$result_users = $conn->query($sql_users);
if ($result_users) {
    while ($row = $result_users->fetch_assoc()) {
        $users_list[] = $row;
    }
} else {
    $errors[] = "Gagal mengambil daftar karyawan: " . $conn->error;
}

// Daftar statis untuk status kerja
$list_status_kerja_filter = ['WFO', 'Sakit', 'WFH', 'Dinas'];

// 5. Bangun Query Utama berdasarkan Filter
// [PERUBAHAN] Kita bangun klausa FROM dan WHERE dulu
$sql_base_from = "FROM presensi p JOIN users u ON p.user_id = u.id WHERE 1=1";
$params = []; // Untuk prepared statement
$types = ""; // Tipe data untuk bind_param

// Tambahkan filter Tanggal Mulai
if (!empty($filter_tanggal_mulai)) {
    $sql_base_from .= " AND p.tanggal_presensi >= ?";
    $params[] = $filter_tanggal_mulai;
    $types .= "s";
}

// Tambahkan filter Tanggal Selesai
if (!empty($filter_tanggal_selesai)) {
    $sql_base_from .= " AND p.tanggal_presensi <= ?";
    $params[] = $filter_tanggal_selesai;
    $types .= "s";
}

// Tambahkan filter User ID
if (!empty($filter_user_id)) {
    $sql_base_from .= " AND p.user_id = ?";
    $params[] = $filter_user_id;
    $types .= "i";
}

// Tambahkan filter Status Kerja
if (!empty($filter_status_kerja)) {
    $sql_base_from .= " AND p.status_kerja = ?";
    $params[] = $filter_status_kerja;
    $types .= "s";
}

// --- [PENAMBAHAN] Query 1: Menghitung TOTAL DATA (untuk pagination) ---
$sql_count = "SELECT COUNT(p.id) " . $sql_base_from;
$stmt_count = $conn->prepare($sql_count);
if ($stmt_count === false) {
    $errors[] = "Gagal mempersiapkan query count: " . $conn->error;
} else {
    if (!empty($types)) {
        $stmt_count->bind_param($types, ...$params);
    }
    $stmt_count->execute();
    $total_results = $stmt_count->get_result()->fetch_row()[0];
    $total_pages = ceil($total_results / $limit);
    $stmt_count->close();
}
// --- [AKHIR PENAMBAHAN] ---


// 6. Eksekusi Query Data (Query 2: Ambil data dengan LIMIT)
// [PERUBAHAN] Modifikasi query utama untuk pakai LIMIT
$presensi_data = [];
$sql_data = "SELECT p.*, u.nama_lengkap, u.nik 
             $sql_base_from
             ORDER BY p.tanggal_presensi DESC, u.nama_lengkap ASC, p.waktu_presensi DESC
             LIMIT ? OFFSET ?";

$stmt_data = $conn->prepare($sql_data);

if ($stmt_data === false) {
    $errors[] = "Gagal mempersiapkan statement data: " . $conn->error;
} else {
    // [PERUBAHAN] Tambahkan limit dan offset ke params
    $params_data = $params;
    $params_data[] = $limit;
    $params_data[] = $offset;
    $types_data = $types . "ii"; // Tambah 2 integer (limit, offset)
    
    if (!empty($types_data)) {
        $stmt_data->bind_param($types_data, ...$params_data);
    }
    
    if ($stmt_data->execute()) {
        $result_data = $stmt_data->get_result();
        while ($row = $result_data->fetch_assoc()) {
            $presensi_data[] = $row;
        }
    } else {
        $errors[] = "Gagal mengeksekusi query data: " . $stmt_data->error;
    }
    $stmt_data->close();
}


// 7. Panggil Sidebar
require '../../includes/sidebar.php';
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Laporan Presensi Karyawan</h1>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4 max-w-4xl" role="alert">
            <strong class="font-bold">Error!</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card mb-6 max-w-4xl">
        <form action="presensi_karyawan.php" method="GET" class="card-content" id="filterForm">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Filter Laporan</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                
                <div>
                    <label for="tanggal_mulai" class="form-label">Dari Tanggal</label>
                    <input type="date" id="tanggal_mulai" name="tanggal_mulai"
                           class="form-input"
                           value="<?php echo htmlspecialchars($filter_tanggal_mulai); ?>" required>
                </div>
                
                <div>
                    <label for="tanggal_selesai" class="form-label">Sampai Tanggal</label>
                    <input type="date" id="tanggal_selesai" name="tanggal_selesai"
                           class="form-input"
                           value="<?php echo htmlspecialchars($filter_tanggal_selesai); ?>" required>
                </div>

                <div>
                    <label for="user_id" class="form-label">Karyawan</label>
                    <select id="user_id" name="user_id" class="form-input">
                        <option value="">-- Semua Karyawan --</option>
                        <?php foreach ($users_list as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo ($filter_user_id == $user['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['nama_lengkap'] . ' (' . $user['nik'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="status_kerja" class="form-label">Status Kerja</label>
                    <select id="status_kerja" name="status_kerja" class="form-input">
                        <option value="">-- Semua Status --</option>
                        <?php foreach ($list_status_kerja_filter as $status): ?>
                            <option value="<?php echo $status; ?>" <?php echo ($filter_status_kerja == $status) ? 'selected' : ''; ?>>
                                <?php echo $status; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="flex justify-end items-center mt-5 space-x-3">
                <a href="presensi_karyawan.php" class="btn-primary-sm btn-secondary no-underline">Reset Filter</a>
                
                <button type="submit" class="btn-primary-sm bg-blue-600 hover:bg-blue-700">
                    Terapkan Filter
                </button>

                <button type="submit" 
                        formaction="generate_report.php" 
                        formmethod="GET"
                        class="btn-primary-sm bg-green-600 hover:bg-green-700 flex items-center no-underline">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Generate Report
                </button>
                </div>
        </form>
    </div>

    <div class="card">
        <div class="card-content">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Hasil Laporan</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waktu</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NIK</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Karyawan</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-m" text-gray-500 uppercase tracking-wider">Status Kerja</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lokasi / Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($presensi_data)): ?>
                            <tr>
                                <td colspan="6" class="px-4 py-4 text-center text-sm text-gray-500">
                                    Tidak ada data presensi yang ditemukan sesuai filter.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($presensi_data as $data): ?>
                                <tr>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo date('d M Y', strtotime($data['tanggal_presensi'])); ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo date('H:i:s', strtotime($data['waktu_presensi'])); ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($data['nik']); ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($data['nama_lengkap']); ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm">
                                        <?php 
                                        $status = htmlspecialchars($data['status_kerja']);
                                        $color = 'bg-gray-100 text-gray-800'; // Default
                                        if ($status == 'WFO') $color = 'bg-blue-100 text-blue-800';
                                        if ($status == 'WFH') $color = 'bg-green-100 text-green-800';
                                        if ($status == 'Sakit') $color = 'bg-red-100 text-red-800';
                                        if ($status == 'Dinas') $color = 'bg-yellow-100 text-yellow-800';
                                        
                                        echo "<span class='px-2 inline-flex text-xs leading-5 font-semibold rounded-full $color'>$status</span>";
                                        ?>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-700 max-w-xs truncate">
                                        <?php
                                        if ($data['status_kerja'] == 'WFO' && !empty($data['lokasi_kerja'])) {
                                            echo htmlspecialchars($data['lokasi_kerja']);
                                        } elseif ($data['status_kerja'] == 'WFH' && !empty($data['lokasi_wfh'])) {
                                            echo htmlspecialchars($data['lokasi_wfh']);
                                        } elseif ($data['status_kerja'] == 'Sakit') {
                                            // Nanti bisa ditambahkan link ke surat sakit jika ada
                                            echo 'Surat Sakit (Menunggu)';
                                        } elseif ($data['status_kerja'] == 'Dinas') {
                                            echo 'Perjalanan Dinas';
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
                <div class="px-4 py-4 border-t border-gray-200">
                    <nav class="flex justify-between items-center">
                        <div>
                            <p class="text-sm text-gray-700">
                                Menampilkan
                                <span class="font-medium"><?php echo $offset + 1; ?></span>
                                -
                                <span class="font-medium"><?php echo min($offset + $limit, $total_results); ?></span>
                                dari
                                <span class="font-medium"><?php echo $total_results; ?></span>
                                hasil
                            </p>
                        </div>
                        <div class="flex space-x-1">
                            <a href="?halaman=<?php echo max(1, $page - 1); ?>&<?php echo $filter_query_string; ?>" 
                               class="px-3 py-1 rounded-md text-sm font-medium <?php echo ($page <= 1) ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'bg-white text-gray-600 hover:bg-gray-50 border'; ?> no-underline">
                                Sebelumnya
                            </a>
                            <a href="?halaman=<?php echo min($total_pages, $page + 1); ?>&<?php echo $filter_query_string; ?>" 
                               class="px-3 py-1 rounded-md text-sm font-medium <?php echo ($page >= $total_pages) ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'bg-white text-gray-600 hover:bg-gray-50 border'; ?> no-underline">
                                Berikutnya
                            </a>
                        </div>
                    </nav>
                </div>
            <?php endif; ?>
            </div>
    </div>

</main>

<?php
// 8. Panggil footer
require '../../includes/footer.php';
?>