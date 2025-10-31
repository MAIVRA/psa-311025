<?php
// pranata/modules/hr/hr_daftar_cuti.php

// 1. Set variabel khusus untuk halaman ini
$page_title = "Daftar Cuti Karyawan";
$page_active = "hr_daftar_cuti"; // Sesuai dengan sidebar.php

// 2. Panggil header
require '../../includes/header.php';

// 3. Pengecekan Hak Akses
// Hanya HR dan Admin yang boleh mengakses halaman ini
$app_akses = $_SESSION['app_akses'] ?? 'Karyawan';
$is_admin = ($app_akses == 'Admin');
$is_hr = ($app_akses == 'HR');

if (!$is_admin && !$is_hr) {
    $_SESSION['flash_message'] = "Anda tidak memiliki hak akses untuk melihat halaman ini.";
    header("Location: ". BASE_URL. "/dashboard.php");
    exit;
}

// 4. Inisialisasi Filter dan Query
date_default_timezone_set('Asia/Jakarta');
$errors = [];

// Ambil nilai filter dari URL (GET)
$filter_tanggal_mulai = $_GET['tanggal_mulai'] ?? '';
$filter_tanggal_selesai = $_GET['tanggal_selesai'] ?? '';
$filter_user_id = $_GET['user_id'] ?? '';
$filter_departemen_id = $_GET['departemen_id'] ?? '';
$filter_status = $_GET['status'] ?? '';

// Ambil daftar karyawan untuk dropdown filter
$users_list = [];
$sql_users = "SELECT id, nama_lengkap FROM users 
              WHERE status_karyawan IN ('PKWT', 'PKWTT', 'BOD', 'BOC') 
              ORDER BY nama_lengkap ASC";
$result_users = $conn->query($sql_users);
if ($result_users) {
    while ($row = $result_users->fetch_assoc()) {
        $users_list[] = $row;
    }
}

// Ambil daftar departemen untuk dropdown filter
$departemen_list = [];
$sql_dept = "SELECT id, nama_departemen FROM departemen ORDER BY nama_departemen ASC";
$result_dept = $conn->query($sql_dept);
if ($result_dept) {
    while ($row = $result_dept->fetch_assoc()) {
        $departemen_list[] = $row;
    }
}

// Daftar statis untuk status
$list_status_filter = ['Pending', 'Approved', 'Rejected'];

// 5. Bangun Query Utama berdasarkan Filter
$sql_data = "SELECT
                lr.id, lr.created_at AS tanggal_pengajuan, lr.status,
                lr.jenis_cuti, lr.tanggal_mulai, lr.tanggal_selesai, lr.jumlah_hari, lr.keterangan,
                u.nama_lengkap AS nama_karyawan,
                d.nama_departemen,
                a.nama_lengkap AS nama_atasan,
                app.nama_lengkap AS nama_penyetuju
            FROM leave_requests lr
            JOIN users u ON lr.user_id = u.id
            LEFT JOIN departemen d ON u.id_departemen = d.id
            LEFT JOIN users a ON u.atasan_id = a.id
            LEFT JOIN users app ON lr.approved_by_id = app.id
            WHERE 1=1";

$params = [];
$types = "";

// Tambahkan filter Tanggal Pengajuan
if (!empty($filter_tanggal_mulai)) {
    $sql_data .= " AND DATE(lr.created_at) >= ?";
    $params[] = $filter_tanggal_mulai;
    $types .= "s";
}
if (!empty($filter_tanggal_selesai)) {
    $sql_data .= " AND DATE(lr.created_at) <= ?";
    $params[] = $filter_tanggal_selesai;
    $types .= "s";
}
// Tambahkan filter User ID
if (!empty($filter_user_id)) {
    $sql_data .= " AND lr.user_id = ?";
    $params[] = $filter_user_id;
    $types .= "i";
}
// Tambahkan filter Departemen
if (!empty($filter_departemen_id)) {
    $sql_data .= " AND u.id_departemen = ?";
    $params[] = $filter_departemen_id;
    $types .= "i";
}
// Tambahkan filter Status
if (!empty($filter_status)) {
    $sql_data .= " AND lr.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

$sql_data .= " ORDER BY lr.created_at DESC";

// 6. Eksekusi Query Data
$data_cuti = [];
$stmt_data = $conn->prepare($sql_data);

if ($stmt_data === false) {
    $errors[] = "Gagal mempersiapkan statement data: ". $conn->error;
} else {
    if (!empty($types)) {
        $stmt_data->bind_param($types, ...$params);
    }
    
    if ($stmt_data->execute()) {
        $result_data = $stmt_data->get_result();
        while ($row = $result_data->fetch_assoc()) {
            $data_cuti[] = $row;
        }
    } else {
        $errors[] = "Gagal mengeksekusi query data: ". $stmt_data->error;
    }
    $stmt_data->close();
}

// 7. Panggil Sidebar
require '../../includes/sidebar.php';
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Daftar Pengajuan Cuti Karyawan</h1>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4 max-w-full" role="alert">
            <strong class="font-bold">Error!</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card mb-6 max-w-full">
        <form action="hr_daftar_cuti.php" method="GET" class="card-content">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Filter Laporan Cuti</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                
                <div>
                    <label for="tanggal_mulai" class="form-label">Tgl Pengajuan (Dari)</label>
                    <input type="date" id="tanggal_mulai" name="tanggal_mulai"
                           class="form-input"
                           value="<?php echo htmlspecialchars($filter_tanggal_mulai); ?>">
                </div>
                
                <div>
                    <label for="tanggal_selesai" class="form-label">Tgl Pengajuan (Sampai)</label>
                    <input type="date" id="tanggal_selesai" name="tanggal_selesai"
                           class="form-input"
                           value="<?php echo htmlspecialchars($filter_tanggal_selesai); ?>">
                </div>

                <div>
                    <label for="user_id" class="form-label">Karyawan</label>
                    <select id="user_id" name="user_id" class="form-input">
                        <option value="">-- Semua Karyawan --</option>
                        <?php foreach ($users_list as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo ($filter_user_id == $user['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['nama_lengkap']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="departemen_id" class="form-label">Departemen</label>
                    <select id="departemen_id" name="departemen_id" class="form-input">
                        <option value="">-- Semua Departemen --</option>
                        <?php foreach ($departemen_list as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>" <?php echo ($filter_departemen_id == $dept['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['nama_departemen']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-input">
                        <option value="">-- Semua Status --</option>
                        <?php foreach ($list_status_filter as $status): ?>
                            <option value="<?php echo $status; ?>" <?php echo ($filter_status == $status) ? 'selected' : ''; ?>>
                                <?php echo $status; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="flex justify-end items-center mt-5 space-x-3">
                <a href="hr_daftar_cuti.php" class="btn-primary-sm btn-secondary no-underline">Reset Filter</a>
                <button type="submit" class="btn-primary-sm bg-blue-600 hover:bg-blue-700">
                    Terapkan Filter
                </button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-content">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Hasil Daftar Cuti</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Karyawan</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Departemen</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Pengajuan</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Atasan Langsung</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($data_cuti)): ?>
                            <tr>
                                <td colspan="6" class="px-4 py-4 text-center text-sm text-gray-500">
                                    Tidak ada data cuti yang ditemukan sesuai filter.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($data_cuti as $data): ?>
                                <tr>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($data['nama_karyawan']); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($data['nama_departemen'] ?? '-'); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo date('d M Y H:i', strtotime($data['tanggal_pengajuan'])); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm">
                                        <?php 
                                        $status = htmlspecialchars($data['status']);
                                        $color = 'bg-gray-100 text-gray-800'; // Default
                                        if ($status == 'Approved') $color = 'bg-green-100 text-green-800';
                                        if ($status == 'Rejected') $color = 'bg-red-100 text-red-800';
                                        if ($status == 'Pending') $color = 'bg-yellow-100 text-yellow-800';
                                        echo "<span class='px-2 inline-flex text-xs leading-5 font-semibold rounded-full $color'>$status</span>";
                                        ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($data['nama_atasan'] ?? 'N/A'); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm">
                                        <button
                                            type="button"
                                            onclick="openDetailModal(this)"
                                            class="btn-primary-sm bg-blue-500 hover:bg-blue-600"
                                            data-nama="<?php echo htmlspecialchars($data['nama_karyawan']); ?>"
                                            data-dept="<?php echo htmlspecialchars($data['nama_departemen'] ?? '-'); ?>"
                                            data-atasan="<?php echo htmlspecialchars($data['nama_atasan'] ?? 'N/A'); ?>"
                                            data-tgl-aju="<?php echo date('d M Y H:i', strtotime($data['tanggal_pengajuan'])); ?>"
                                            data-jenis-cuti="<?php echo htmlspecialchars($data['jenis_cuti']); ?>"
                                            data-tgl-mulai="<?php echo date('d M Y', strtotime($data['tanggal_mulai'])); ?>"
                                            data-tgl-selesai="<?php echo date('d M Y', strtotime($data['tanggal_selesai'])); ?>"
                                            data-jumlah-hari="<?php echo $data['jumlah_hari']; ?>"
                                            data-status="<?php echo $data['status']; ?>"
                                            data-penyetuju="<?php echo htmlspecialchars($data['nama_penyetuju'] ?? '-'); ?>"
                                            data-keterangan="<?php echo htmlspecialchars($data['keterangan']); ?>"
                                        >
                                            Detail
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-4 text-sm text-gray-600">
                Menampilkan <strong><?php echo count($data_cuti); ?></strong> hasil.
            </div>
        </div>
    </div>
</main>

<div id="detailModal" class="modal-overlay hidden">
    <div class="bg-white p-6 rounded-lg shadow-xl max-w-lg w-full mx-4">
        <div class="flex justify-between items-center border-b pb-3 mb-4">
            <h3 class="text-xl font-semibold text-gray-800">Detail Pengajuan Cuti</h3>
            <button onclick="closeDetailModal()" class="text-gray-500 hover:text-gray-800">&times;</button>
        </div>
        
        <div class="space-y-4">
            <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-3">
                <div class="sm:col-span-1">
                    <dt class="profile-label">Nama Karyawan</dt>
                    <dd class="profile-data" id="modalNama">N/A</dd>
                </div>
                <div class="sm:col-span-1">
                    <dt class="profile-label">Departemen</dt>
                    <dd class="profile-data" id="modalDept">N/A</dd>
                </div>
                <div class="sm:col-span-1">
                    <dt class="profile-label">Atasan Langsung</dt>
                    <dd class="profile-data" id="modalAtasan">N/A</dd>
                </div>

                <div class="sm:col-span-1">
                    <dt class="profile-label">Tanggal Pengajuan</dt>
                    <dd class="profile-data" id="modalTglAju">N/A</dd>
                </div>
                <div class="sm:col-span-1">
                    <dt class="profile-label">Status</dt>
                    <dd class="profile-data" id="modalStatus">N/A</dd>
                </div>
                <div class="sm:col-span-1">
                    <dt class="profile-label">Diproses Oleh</dt>
                    <dd class="profile-data" id="modalPenyetuju">N/A</dd>
                </div>
                
                <hr class="sm:col-span-3">

                <div class="sm:col-span-1">
                    <dt class="profile-label">Jenis Cuti</dt>
                    <dd class="profile-data" id="modalJenisCuti">N/A</dd>
                </div>
                <div class="sm:col-span-1">
                    <dt class="profile-label">Tanggal Mulai</dt>
                    <dd class="profile-data" id="modalTglMulai">N/A</dd>
                </div>
                <div class="sm:col-span-1">
                    <dt class="profile-label">Tanggal Selesai</dt>
                    <dd class="profile-data" id="modalTglSelesai">N/A</dd>
                </div>
                <div class="sm:col-span-1">
                    <dt class="profile-label">Jumlah Hari</dt>
                    <dd class="profile-data" id="modalJumlahHari">N/A</dd>
                </div>
                
                <div class="sm:col-span-3">
                    <dt class="profile-label">Keterangan Pengajuan</dt>
                    <dd class="profile-data bg-gray-50 p-2 border rounded" id="modalKeterangan" style="min-height: 50px;"></dd>
                </div>
            </dl>
        </div>

        <div class="mt-6 flex justify-end">
            <button
                type="button"
                onclick="closeDetailModal()"
                class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-semibold transition duration-200">
                Tutup
            </button>
        </div>
    </div>
</div>

<script>
    const modal = document.getElementById('detailModal');

    function openDetailModal(button) {
        if (!modal) return;

        // Ambil data dari data-attributes
        const data = button.dataset;

        // Isi data ke modal
        document.getElementById('modalNama').innerText = data.nama || 'N/A';
        document.getElementById('modalDept').innerText = data.dept || 'N/A';
        document.getElementById('modalAtasan').innerText = data.atasan || 'N/A';
        document.getElementById('modalTglAju').innerText = data.tglAju || 'N/A';
        document.getElementById('modalStatus').innerText = data.status || 'N/A';
        document.getElementById('modalPenyetuju').innerText = data.penyetuju || '-';
        document.getElementById('modalJenisCuti').innerText = data.jenisCuti || 'N/A';
        document.getElementById('modalTglMulai').innerText = data.tglMulai || 'N/A';
        document.getElementById('modalTglSelesai').innerText = data.tglSelesai || 'N/A';
        document.getElementById('modalJumlahHari').innerText = data.jumlahHari + ' Hari' || 'N/A';
        document.getElementById('modalKeterangan').innerText = data.keterangan || 'Tidak ada keterangan.';

        // Tampilkan modal
        modal.classList.remove('hidden');
    }

    function closeDetailModal() {
        if (modal) {
            modal.classList.add('hidden');
        }
    }
    
    // Tutup modal jika klik di luar area modal
    modal.addEventListener('click', function(event) {
        if (event.target === modal) {
            closeDetailModal();
        }
    });
</script>


<?php
// 8. Panggil footer
require '../../includes/footer.php';
?>