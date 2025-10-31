<?php
// modules/hr/hr_daftar_karyawan.php
// File untuk menu HR "Daftar Karyawan" (Menyembunyikan tombol Edit untuk BOD/BOC)

// 1. Set variabel khusus untuk halaman ini
$page_title = "Daftar Karyawan";
$page_active = "hr_daftar_karyawan"; // Untuk menandai menu di sidebar

// 2. Panggil file header
require '../../includes/header.php'; // $conn, $user_id, $_SESSION tersedia

// 3. Keamanan Halaman (PENTING!)
// Ambil data user yang login
$logged_in_app_akses = $_SESSION['app_akses'];
$is_hr = ($logged_in_app_akses == 'HR');
$is_admin = ($logged_in_app_akses == 'Admin');

// Hanya 'HR' atau 'Admin' yang boleh mengakses halaman ini
if (!$is_hr && !$is_admin) {
    // Panggil sidebar
    require '../../includes/sidebar.php';
    
    // Tampilkan pesan error di konten
    echo '<main class="overflow-x-hidden overflow-y-auto bg-gray-100 p-6">';
    echo '  <div class="card">';
    echo '    <div class="card-content">';
    echo '      <h2 class="text-xl font-bold text-red-600">Akses Ditolak</h2>';
    echo '      <p class="text-gray-600 mt-2">Anda tidak memiliki izin untuk mengakses halaman ini.</p>';
    echo '    </div>';
    echo '  </div>';
    echo '</main>';
    
    // Panggil footer
    require '../../includes/footer.php';
    
    // Hentikan eksekusi sisa script
    exit;
}

// 4. Logika Pengambilan Data Karyawan & Filter
$errors = [];
$karyawan_list = [];
$list_departemen = [];

// === Logika Pagination ===
$limit = 25; // 25 baris per halaman
$page = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;
$total_results = 0;
$total_pages = 0;
// =============================

// Ambil nilai filter dari URL (GET)
$filter_nama_nik = $_GET['nama_nik'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_dept = $_GET['id_departemen'] ?? '';

// Buat query string untuk link pagination agar filter tidak hilang
$filter_query_string = http_build_query([
    'nama_nik' => $filter_nama_nik,
    'status' => $filter_status,
    'id_departemen' => $filter_dept
]);


// Ambil daftar departemen untuk dropdown filter
$result_dept = $conn->query("SELECT id, nama_departemen FROM departemen ORDER BY nama_departemen ASC");
if ($result_dept) {
    while ($row_dept = $result_dept->fetch_assoc()) {
        $list_departemen[] = $row_dept;
    }
}

// --- Persiapan Query ---
$sql_base = "FROM users u
             LEFT JOIN departemen d ON u.id_departemen = d.id
             LEFT JOIN divisi dv ON u.id_divisi = dv.id
             LEFT JOIN users atasan ON u.atasan_id = atasan.id";

// Bangun klausa WHERE secara dinamis
$where_clauses = [];
$params = [];
$types = "";

// Filter default: hanya karyawan aktif dan bukan Admin
$where_clauses[] = "u.status_karyawan IN ('PKWT', 'PKWTT', 'BOD', 'BOC')";
$where_clauses[] = "u.app_akses != 'Admin'";

// Tambahkan filter jika ada
if (!empty($filter_nama_nik)) {
    $where_clauses[] = "(u.nama_lengkap LIKE ? OR u.nik LIKE ?)";
    $search_term = "%" . $filter_nama_nik . "%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}
if (!empty($filter_status)) {
    $where_clauses[] = "u.status_karyawan = ?";
    $params[] = $filter_status;
    $types .= "s";
}
if (!empty($filter_dept)) {
    $where_clauses[] = "u.id_departemen = ?";
    $params[] = $filter_dept;
    $types .= "i";
}

// Gabungkan klausa WHERE
$sql_where = "";
if (!empty($where_clauses)) {
    $sql_where = " WHERE " . implode(" AND ", $where_clauses);
}

// --- Query 1: Menghitung TOTAL DATA untuk pagination ---
$sql_count = "SELECT COUNT(u.id) " . $sql_base . $sql_where;
$stmt_count = $conn->prepare($sql_count);
if ($stmt_count === false) {
    $errors[] = "Gagal mempersiapkan query count: " . $conn->error;
} else {
    if (!empty($params)) {
        $stmt_count->bind_param($types, ...$params);
    }
    $stmt_count->execute();
    $total_results = $stmt_count->get_result()->fetch_row()[0];
    $total_pages = ceil($total_results / $limit);
    $stmt_count->close();
}


// --- Query 2: Mengambil DATA AKTUAL dengan LIMIT & OFFSET ---
$sql_data = "SELECT 
                u.id, u.nik, u.nama_lengkap, u.nama_jabatan, u.email, u.telepon, u.status_karyawan, 
                d.nama_departemen, 
                dv.nama_divisi,
                atasan.nama_lengkap as nama_atasan
            " . $sql_base . $sql_where . "
            ORDER BY u.nama_lengkap ASC
            LIMIT ? OFFSET ?"; // Tambahkan LIMIT dan OFFSET

// Tambahkan tipe data integer untuk LIMIT dan OFFSET
$types .= "ii"; 
$params_data = $params; // Salin params filter
$params_data[] = $limit;
$params_data[] = $offset;

$stmt = $conn->prepare($sql_data);
if ($stmt === false) {
    $errors[] = "Gagal mempersiapkan query karyawan: " . $conn->error;
} else {
    // Gunakan params_data yang sudah lengkap (filter + pagination)
    if (!empty($params_data)) {
        $stmt->bind_param($types, ...$params_data);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $karyawan_list[] = $row;
        }
    }
    $stmt->close();
}


// Helper function
if (!function_exists('showData')) {
    function showData($data) {
        return !empty($data) ? htmlspecialchars($data) : '-';
    }
}
?>

<?php require '../../includes/sidebar.php'; ?>

<main class="overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Daftar Karyawan</h1>
        
        <a href="../users/add_user.php" class="btn-primary-sm bg-green-600 hover:bg-green-700 flex items-center shadow-md px-4 py-2 text-sm font-semibold no-underline">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Tambah Karyawan
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-6" role="alert">
            <strong class="font-bold">Error!</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card mb-6">
        <div class="card-content">
            <h2 class="text-lg font-semibold text-gray-700 mb-4">Filter Karyawan</h2>
            <form action="hr_daftar_karyawan.php" method="GET">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="nama_nik" class="form-label">Nama / NIK</label>
                        <input type="text" id="nama_nik" name="nama_nik" class="form-input" placeholder="Cari..." value="<?php echo htmlspecialchars($filter_nama_nik); ?>">
                    </div>
                    
                    <div>
                        <label for="id_departemen" class="form-label">Departemen</label>
                        <select id="id_departemen" name="id_departemen" class="form-input">
                            <option value="">-- Semua Departemen --</option>
                            <?php foreach ($list_departemen as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>" <?php echo ($filter_dept == $dept['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['nama_departemen']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="status" class="form-label">Status Karyawan</label>
                        <select id="status" name="status" class="form-input">
                            <option value="">-- Semua Status --</option>
                            <option value="PKWTT" <?php echo ($filter_status == 'PKWTT') ? 'selected' : ''; ?>>PKWTT</option>
                            <option value="PKWT" <?php echo ($filter_status == 'PKWT') ? 'selected' : ''; ?>>PKWT</option>
                            <option value="BOD" <?php echo ($filter_status == 'BOD') ? 'selected' : ''; ?>>BOD</option>
                            <option value="BOC" <?php echo ($filter_status == 'BOC') ? 'selected' : ''; ?>>BOC</option>
                        </select>
                    </div>
                    
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="btn-primary-sm bg-blue-600 hover:bg-blue-700 px-4 py-2 font-semibold">
                            Filter
                        </button>
                        <a href="hr_daftar_karyawan.php" class="btn-secondary px-4 py-2 font-semibold no-underline">
                            Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-content">
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NIK</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Lengkap</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jabatan</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Departemen</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($karyawan_list)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">Tidak ada data karyawan yang cocok dengan filter.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($karyawan_list as $k): ?>
                                <tr>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900"><?php echo showData($k['nik']); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-800"><?php echo htmlspecialchars($k['nama_lengkap']); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-600"><?php echo showData($k['nama_jabatan']); ?></td>
                                    <td class="px-6 py-4 text-sm text-gray-600"><?php echo showData($k['nama_departemen']); ?></td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <?php
                                        $status = $k['status_karyawan'];
                                        $color_class = 'bg-gray-100 text-gray-800'; // Default
                                        switch ($status) {
                                            case 'PKWTT':
                                                $color_class = 'bg-green-100 text-green-800';
                                                break;
                                            case 'PKWT':
                                                $color_class = 'bg-yellow-100 text-yellow-800';
                                                break;
                                            case 'BOD':
                                            case 'BOC':
                                                $color_class = 'bg-blue-100 text-blue-800';
                                                break;
                                        }
                                        echo "<span class='px-2 inline-flex text-xs leading-5 font-semibold rounded-full $color_class'>" . htmlspecialchars($status) . "</span>";
                                        ?>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button 
                                            type="button" 
                                            class="text-indigo-600 hover:text-indigo-900 open-detail-modal" 
                                            data-id="<?php echo $k['id']; ?>">
                                            Detail
                                        </button>
                                        
                                        <?php
                                        // Cek status user di baris ini
                                        $is_bod_boc = in_array($k['status_karyawan'], ['BOD', 'BOC']);
                                        
                                        // Tampilkan tombol Edit HANYA jika:
                                        // 1. Yang login adalah Admin
                                        // 2. ATAU (Yang login adalah HR DAN yang diedit BUKAN BOD/BOC)
                                        if ($is_admin || ($is_hr && !$is_bod_boc)):
                                        ?>
                                            <a href="../users/edit_user.php?id=<?php echo $k['id']; ?>" class="text-blue-600 hover:text-blue-900 ml-4">Edit</a>
                                        <?php endif; ?>
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
                            <a href="?halaman=<?php echo max(1, $page - 1); ?>&<?php echo $filter_query_string; ?>" 
                               class="px-3 py-1 rounded-md text-sm font-medium <?php echo ($page <= 1) ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'bg-white text-gray-600 hover:bg-gray-50'; ?>">
                                Sebelumnya
                            </a>
                            <a href="?halaman=<?php echo min($total_pages, $page + 1); ?>&<?php echo $filter_query_string; ?>" 
                               class="px-3 py-1 rounded-md text-sm font-medium <?php echo ($page >= $total_pages) ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'bg-white text-gray-600 hover:bg-gray-50'; ?>">
                                Berikutnya
                            </a>
                        </div>
                    </nav>
                </div>
            <?php endif; ?>

        </div>
    </div>
</main>

<div id="detailKaryawanModal" class="modal-overlay hidden">
    <div class="bg-white p-6 rounded-lg shadow-xl max-w-2xl w-full mx-4 z-20 overflow-y-auto" style="max-height: 90vh;">
        <div class="flex justify-between items-center mb-4 border-b pb-3 sticky top-0 bg-white">
            <h3 class="text-xl font-semibold text-gray-800">Detail Karyawan</h3>
            <button id="btnTutupModalDetail" class="text-gray-400 hover:text-gray-600 text-3xl">&times;</button>
        </div>
        
        <div id="detailKaryawanContent" class="text-gray-700">
            <p class="text-center text-gray-500">Memuat data...</p>
        </div>

        <div class="mt-6 flex justify-end border-t pt-4 sticky bottom-0 bg-white">
            <button
                type="button"
                id="btnTutupModalDetailBawah"
                class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg text-sm font-semibold transition duration-200">
                Tutup
            </button>
        </div>
    </div>
    <div class="modal-backdrop" id="backdropModalDetail"></div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('detailKaryawanModal');
    const modalContent = document.getElementById('detailKaryawanContent');
    const btnTutup = document.getElementById('btnTutupModalDetail');
    const btnTutupBawah = document.getElementById('btnTutupModalDetailBawah');
    const backdrop = document.getElementById('backdropModalDetail');
    const tombolBuka = document.querySelectorAll('.open-detail-modal');

    function bukaModal(event) {
        if (!modal) return;
        
        // Ambil ID dari tombol yang diklik
        const userId = event.currentTarget.getAttribute('data-id');
        
        // Tampilkan modal dengan pesan "Memuat data..."
        modalContent.innerHTML = '<p class="text-center text-gray-500">Memuat data...</p>';
        modal.classList.remove('hidden');
        
        // Panggil file AJAX
        fetch(`ajax_get_karyawan_detail.php?id=${userId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Gagal mengambil data. Status: ' + response.status);
                }
                return response.text();
            })
            .then(html => {
                // Masukkan HTML yang didapat ke dalam modal
                modalContent.innerHTML = html;
            })
            .catch(error => {
                // Tampilkan pesan error di modal
                modalContent.innerHTML = `<p class="text-red-500">Terjadi kesalahan: ${error.message}</p>`;
                console.error('Error fetching detail:', error);
            });
    }

    function tutupModal() {
        if (!modal) return;
        modal.classList.add('hidden');
    }

    // Tambahkan event listener ke semua tombol "Detail"
    tombolBuka.forEach(button => {
        button.addEventListener('click', bukaModal);
    });

    // Event listener untuk tombol tutup
    if (btnTutup) btnTutup.addEventListener('click', tutupModal);
    if (btnTutupBawah) btnTutupBawah.addEventListener('click', tutupModal);
    if (backdrop) backdrop.addEventListener('click', tutupModal);
});
</script>

<?php require '../../includes/footer.php'; ?>