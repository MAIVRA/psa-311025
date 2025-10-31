<?php
// modules/presensi/index.php

// 1. Set variabel halaman
$page_title = "Riwayat & Laporan Presensi";
$page_active = "presensi"; // Untuk highlight sidebar

// 2. Mulai session, panggil header
require '../../includes/header.php';
// $user_id sudah ada dari header.php

// Ambil $tier dan $app_akses dari Session
$tier = $_SESSION['tier'] ?? 'Staf';
$app_akses = $_SESSION['app_akses'] ?? 'Karyawan';

// Set timezone ke WIB (UTC+7)
date_default_timezone_set('Asia/Jakarta');

// === [PERUBAHAN] Logika Filter & Pagination ===
$errors = [];
$riwayat_presensi = [];
$limit = 10; // Jumlah entri per halaman
$page = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
$offset = ($page - 1) * $limit;

// Ambil nilai filter dari URL
$filter_start_date = $_GET['tanggal_mulai'] ?? '';
$filter_end_date = $_GET['tanggal_selesai'] ?? '';
$filter_status = $_GET['status_kerja'] ?? '';
$filter_lokasi = $_GET['lokasi_kerja'] ?? ''; // [BARU] Ambil filter lokasi

// Daftar statis untuk filter
$list_status_kerja = ['WFO', 'WFH', 'Sakit', 'Dinas'];
$list_lokasi_wfo = [ // [BARU] Daftar lokasi dari dashboard.php
    'PRANATA/Rutan Office (Ikado Surabaya)',
    'TRD Bambe',
    'TRD/BMS Shipyard Lamongan',
    'TRD/Rutan Wringinanom',
    'Terra Office'
];


// Buat query string untuk link pagination agar filter tidak hilang
$filter_query_string = http_build_query([
    'tanggal_mulai' => $filter_start_date,
    'tanggal_selesai' => $filter_end_date,
    'status_kerja' => $filter_status,
    'lokasi_kerja' => $filter_lokasi // [BARU] Tambahkan ke query string
]);

// Bangun klausa WHERE secara dinamis
$sql_where = "WHERE user_id = ?";
$params = [$user_id];
$types = "i";

if (!empty($filter_start_date)) {
    $sql_where .= " AND tanggal_presensi >= ?";
    $params[] = $filter_start_date;
    $types .= "s";
}
if (!empty($filter_end_date)) {
    $sql_where .= " AND tanggal_presensi <= ?";
    $params[] = $filter_end_date;
    $types .= "s";
}
if (!empty($filter_status)) {
    $sql_where .= " AND status_kerja = ?";
    $params[] = $filter_status;
    $types .= "s";
}
// [BARU] Tambahkan filter lokasi
if (!empty($filter_lokasi)) {
    // Filter ini akan mencari di kolom lokasi_kerja (untuk WFO)
    // atau di kolom lokasi_wfh (jika user mencari WFH)
    // Jika status juga dipilih, kita optimalkan
    if ($filter_status == 'WFO') {
         $sql_where .= " AND lokasi_kerja = ?";
         $params[] = $filter_lokasi;
         $types .= "s";
    } elseif ($filter_status == 'WFH') {
         $sql_where .= " AND lokasi_wfh LIKE ?"; // Gunakan LIKE untuk WFH
         $params[] = "%" . $filter_lokasi . "%";
         $types .= "s";
    } else {
        // Jika status tidak spesifik, cari di kedua kolom
         $sql_where .= " AND (lokasi_kerja = ? OR lokasi_wfh LIKE ?)";
         $params[] = $filter_lokasi;
         $params[] = "%" . $filter_lokasi . "%";
         $types .= "ss";
    }
}


// Ambil total data untuk pagination (dengan filter)
$stmt_total = $conn->prepare("SELECT COUNT(*) FROM presensi $sql_where");
$stmt_total->bind_param($types, ...$params);
$stmt_total->execute();
$total_results = $stmt_total->get_result()->fetch_row()[0];
$total_pages = ceil($total_results / $limit);
$stmt_total->close();

// Ambil data riwayat presensi (dengan filter dan pagination)
$sql_data = "SELECT * FROM presensi $sql_where ORDER BY tanggal_presensi DESC LIMIT ? OFFSET ?";
$params_data = $params;
$params_data[] = $limit;
$params_data[] = $offset;
$types_data = $types . "ii";

$stmt = $conn->prepare($sql_data);
if ($stmt === false) {
    $errors[] = "Error preparing statement: " . $conn->error;
} else {
    $stmt->bind_param($types_data, ...$params_data);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $riwayat_presensi[] = $row;
        }
    }
    $stmt->close();
}
// === [AKHIR PERUBAHAN] ===

// Helper functions
if (!function_exists('showData')) {
    function showData($data) {
        return !empty($data) ? htmlspecialchars($data) : '-';
    }
}
if (!function_exists('formatIndonesianDate')) {
    function formatIndonesianDate($dateStr) {
        if (empty($dateStr)) return '-';
        if (strtotime($dateStr) === false) return '-';
        $currentLocale = setlocale(LC_TIME, 0);
        setlocale(LC_TIME, 'id_ID.UTF-8', 'Indonesian_Indonesia.1252', 'Indonesian');
        $formattedDate = strftime('%A, %d %B %Y', strtotime($dateStr));
        setlocale(LC_TIME, $currentLocale);
        return $formattedDate;
    }
}
?>

<?php require '../../includes/sidebar.php'; ?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Riwayat Presensi Saya</h1>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4" role="alert">
            <strong class="font-bold">Error!</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card mb-6">
        <form action="index.php" method="GET" class="card-content">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Filter Riwayat</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
                
                <div>
                    <label for="tanggal_mulai" class="form-label">Dari Tanggal</label>
                    <input type="date" id="tanggal_mulai" name="tanggal_mulai"
                           class="form-input"
                           value="<?php echo htmlspecialchars($filter_start_date); ?>">
                </div>
                
                <div>
                    <label for="tanggal_selesai" class="form-label">Sampai Tanggal</label>
                    <input type="date" id="tanggal_selesai" name="tanggal_selesai"
                           class="form-input"
                           value="<?php echo htmlspecialchars($filter_end_date); ?>">
                </div>

                <div>
                    <label for="status_kerja" class="form-label">Status Kerja</label>
                    <select id="status_kerja" name="status_kerja" class="form-input">
                        <option value="">-- Semua Status --</option>
                        <?php foreach ($list_status_kerja as $status): ?>
                            <option value="<?php echo $status; ?>" <?php echo ($filter_status == $status) ? 'selected' : ''; ?>>
                                <?php echo $status; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="lokasi_kerja" class="form-label">Lokasi Kerja (WFO)</label>
                    <select id="lokasi_kerja" name="lokasi_kerja" class="form-input">
                        <option value="">-- Semua Lokasi WFO --</option>
                        <?php foreach ($list_lokasi_wfo as $lokasi): ?>
                            <option value="<?php echo htmlspecialchars($lokasi); ?>" <?php echo ($filter_lokasi == $lokasi) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($lokasi); ?>
                            </option>
                        <?php endforeach; ?>
                         <option value="Lainnya" <?php echo ($filter_lokasi == 'Lainnya') ? 'selected' : ''; ?>>Lainnya (WFH/Dinas)</option>
                    </select>
                </div>
                <div class="flex items-end space-x-2">
                    <button type="submit" class="btn-primary-sm bg-blue-600 hover:bg-blue-700 h-10">
                        Filter
                    </button>
                    <a href="index.php" class="btn-secondary px-4 py-2 h-10 flex items-center no-underline">
                        Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
    <div class="card">
        <div class="card-content">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waktu</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lokasi / Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($riwayat_presensi)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                <?php if (empty($filter_start_date) && empty($filter_end_date) && empty($filter_status) && empty($filter_lokasi)): ?>
                                    Belum ada riwayat presensi.
                                <?php else: ?>
                                    Tidak ada riwayat presensi yang cocok dengan filter Anda.
                                <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($riwayat_presensi as $presensi): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?php echo formatIndonesianDate($presensi['tanggal_presensi']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo date('H:i:s', strtotime($presensi['waktu_presensi'])); ?> WIB</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $status = htmlspecialchars($presensi['status_kerja']);
                                        $color = 'bg-gray-100 text-gray-800'; // Default
                                        if ($status == 'WFO') $color = 'bg-blue-100 text-blue-800';
                                        if ($status == 'WFH') $color = 'bg-green-100 text-green-800';
                                        if ($status == 'Sakit') $color = 'bg-red-100 text-red-800';
                                        if ($status == 'Dinas') $color = 'bg-yellow-100 text-yellow-800';
                                        ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $color; ?>">
                                            <?php echo $status; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        <?php
                                        if ($presensi['status_kerja'] == 'WFO') echo showData($presensi['lokasi_kerja']);
                                        elseif ($presensi['status_kerja'] == 'WFH') echo showData($presensi['lokasi_wfh']);
                                        else echo '-';
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="px-6 py-4 border-t border-gray-200">
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
                            <a href="?halaman=<?php echo max(1, $page - 1); ?>&<?php echo $filter_query_string; ?>" class="px-3 py-1 rounded-md text-sm font-medium <?php echo ($page <= 1) ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'bg-white text-gray-600 hover:bg-gray-50'; ?> no-underline">
                                Sebelumnya
                            </a>
                            <a href="?halaman=<?php echo min($total_pages, $page + 1); ?>&<?php echo $filter_query_string; ?>" class="px-3 py-1 rounded-md text-sm font-medium <?php echo ($page >= $total_pages) ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'bg-white text-gray-600 hover:bg-gray-50'; ?> no-underline">
                                Berikutnya
                            </a>
                        </div>
                    </nav>
                </div>
            <?php endif; ?>

        </div>
    </div>
</main>

<div id="laporanModal" class="modal-overlay hidden">
    <div class="modal-backdrop" id="modalBackdrop"></div>
    
    <div class="bg-white p-6 rounded-lg shadow-xl max-w-lg w-full mx-4 z-20">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-semibold text-gray-800">Generate Laporan Presensi</h3>
            <button id="tombolTutupModal" class="text-gray-400 hover:text-gray-600">&times;</button>
        </div>
        
        <div id="modalContent">
            <p class="text-sm text-gray-500">Memuat form...</p>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('laporanModal');
        const contentModal = document.getElementById('modalContent');
        const tombolBuka = document.getElementById('tombolBukaLaporan'); // Tombol ini tidak ada di HTML Anda
        const tombolTutup = document.getElementById('tombolTutupModal');
        const backdrop = document.getElementById('modalBackdrop');

        function bukaModal() {
            if (!modal) return;
            modal.classList.remove('hidden');
            // Load konten
            fetch('report_form.php') // Path ini benar
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Gagal memuat form. Status: ' + response.status);
                    }
                    return response.text();
                })
                .then(html => {
                    contentModal.innerHTML = html;
                })
                .catch(error => {
                    contentModal.innerHTML = '<p class="text-red-500">Maaf, terjadi kesalahan saat memuat form laporan. Silakan coba lagi.</p>';
                    console.error('Error fetching report_form.php:', error);
                });
        }

        function tutupModal() {
            if (!modal) return;
            modal.classList.add('hidden');
            contentModal.innerHTML = '<p class="text-sm text-gray-500">Memuat form...</p>';
        }

        if (tombolBuka) {
            tombolBuka.addEventListener('click', bukaModal);
        }
        if (tombolTutup) {
            tombolTutup.addEventListener('click', tutupModal);
        }
        if (backdrop) {
            backdrop.addEventListener('click', tutupModal);
        }
    });
</script>
<?php require '../../includes/footer.php'; ?>