<?php
// pranata/modules/hr/hr_cuti_massal.php

// 1. Set variabel khusus untuk halaman ini
$page_title = "Kelola Cuti Massal";
$page_active = "hr_cuti_massal"; // Akan kita tambahkan di sidebar nanti

// 2. Panggil db.php dan lakukan Pengecekan Hak Akses
// Panggil db.php dulu untuk $conn dan $_SESSION
require '../../includes/db.php';

// Pengecekan Hak Akses (harus sebelum header.php)
$app_akses = $_SESSION['app_akses'] ?? 'Karyawan';
$is_admin = ($app_akses == 'Admin');
$is_hr = ($app_akses == 'HR');

if (!$is_admin && !$is_hr) {
    // Jika tidak punya hak, tendang ke dashboard
    $_SESSION['flash_message'] = "Anda tidak memiliki hak akses untuk melihat halaman ini.";
    header("Location: ". BASE_URL. "/dashboard.php");
    exit;
}

// 3. Logika POST (Tambah / Hapus)
$errors = [];
$success_message = '';
$user_id = $_SESSION['user_id']; // ID HR yang sedang login

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- Logika Tambah Cuti Massal ---
    if (isset($_POST['tambah_cuti'])) {
        $tanggal_cuti = $_POST['tanggal_cuti'] ?? null;
        $keterangan = trim($_POST['keterangan'] ?? '');
        
        if (empty($tanggal_cuti) || empty($keterangan)) {
            $errors[] = "Tanggal dan Keterangan wajib diisi.";
        } else {
            $tahun = date('Y', strtotime($tanggal_cuti));
            
            // Cek duplikat
            $stmt_check = $conn->prepare("SELECT id FROM collective_leave WHERE tanggal_cuti = ?");
            $stmt_check->bind_param("s", $tanggal_cuti);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                $errors[] = "Tanggal ". htmlspecialchars($tanggal_cuti). " sudah pernah diajukan.";
            } else {
                // Insert data baru
                $stmt_insert = $conn->prepare(
                    "INSERT INTO collective_leave (tanggal_cuti, tahun, keterangan, proposed_by_id, status) 
                     VALUES (?, ?, ?, ?, 'Pending')"
                );
                $stmt_insert->bind_param("sssi", $tanggal_cuti, $tahun, $keterangan, $user_id);
                
                if ($stmt_insert->execute()) {
                    $success_message = "Tanggal cuti massal ". htmlspecialchars($tanggal_cuti). " berhasil diajukan dan menunggu persetujuan.";
                } else {
                    $errors[] = "Gagal menyimpan data: ". $stmt_insert->error;
                }
                $stmt_insert->close();
            }
            $stmt_check->close();
        }
    }
    
    // --- Logika Hapus Cuti Massal ---
    if (isset($_POST['hapus_cuti'])) {
        $cuti_id = $_POST['cuti_id'] ?? 0;
        
        // Hanya boleh hapus yang statusnya 'Pending'
        $stmt_delete = $conn->prepare("DELETE FROM collective_leave WHERE id = ? AND status = 'Pending'");
        $stmt_delete->bind_param("i", $cuti_id);
        
        if ($stmt_delete->execute()) {
            if ($stmt_delete->affected_rows > 0) {
                $success_message = "Usulan cuti massal berhasil dihapus.";
            } else {
                $errors[] = "Gagal menghapus: Usulan tidak ditemukan atau statusnya bukan 'Pending'.";
            }
        } else {
            $errors[] = "Error saat menghapus: ". $stmt_delete->error;
        }
        $stmt_delete->close();
    }
}


// 4. Logika GET (Ambil data untuk tabel)
$filter_tahun = $_GET['tahun'] ?? date('Y'); // Default tahun ini
$daftar_cuti_massal = [];

$sql_get = "SELECT 
                cl.id, cl.tanggal_cuti, cl.keterangan, cl.status,
                u_propose.nama_lengkap AS nama_pengusul,
                u_process.nama_lengkap AS nama_pemroses
            FROM collective_leave cl
            JOIN users u_propose ON cl.proposed_by_id = u_propose.id
            LEFT JOIN users u_process ON cl.processed_by_id = u_process.id
            WHERE cl.tahun = ?
            ORDER BY cl.tanggal_cuti ASC";
            
$stmt_get = $conn->prepare($sql_get);
$stmt_get->bind_param("s", $filter_tahun);
$stmt_get->execute();
$result_get = $stmt_get->get_result();
while ($row = $result_get->fetch_assoc()) {
    $daftar_cuti_massal[] = $row;
}
$stmt_get->close();


// 5. Panggil header.php (Setelah semua logika POST selesai)
require '../../includes/header.php';

// 6. Panggil sidebar.php
require '../../includes/sidebar.php';
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Kelola Cuti Massal</h1>
        <button
            type="button"
            onclick="openModal('tambahModal')"
            class="btn-primary-sm bg-blue-600 hover:bg-blue-700 flex items-center">
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Ajukan Tanggal Baru
        </button>
    </div>

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

    <?php if (!empty($success_message)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative mb-4 max-w-full" role="alert">
            <strong class="font-bold">Sukses!</strong>
            <span class="block sm:inline"><?php echo htmlspecialchars($success_message); ?></span>
        </div>
    <?php endif; ?>

    <div class="card mb-6 max-w-xs">
        <form action="hr_cuti_massal.php" method="GET" class="card-content">
            <label for="tahun" class="form-label">Tampilkan Tahun</label>
            <div class="flex space-x-2">
                <input type="number" id="tahun" name="tahun"
                       class="form-input"
                       min="2020" max="<?php echo date('Y') + 5; ?>"
                       value="<?php echo htmlspecialchars($filter_tahun); ?>" required>
                <button type="submit" class="btn-primary-sm bg-blue-600 hover:bg-blue-700">
                    Filter
                </button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-content">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Daftar Usulan Cuti Massal Tahun <?php echo htmlspecialchars($filter_tahun); ?></h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Cuti</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Keterangan</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Diajukan Oleh</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Diproses Oleh</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($daftar_cuti_massal)): ?>
                            <tr>
                                <td colspan="6" class="px-4 py-4 text-center text-sm text-gray-500">
                                    Belum ada data cuti massal untuk tahun <?php echo htmlspecialchars($filter_tahun); ?>.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($daftar_cuti_massal as $data): ?>
                                <tr>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo date('d M Y', strtotime($data['tanggal_cuti'])); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($data['keterangan']); ?></td>
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
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($data['nama_pengusul']); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($data['nama_pemroses'] ?? '-'); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm">
                                        <?php if ($data['status'] == 'Pending'): ?>
                                            <form action="hr_cuti_massal.php?tahun=<?php echo $filter_tahun; ?>" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus usulan ini?');" class="inline">
                                                <input type="hidden" name="cuti_id" value="<?php echo $data['id']; ?>">
                                                <button type="submit" name="hapus_cuti" class="text-red-600 hover:text-red-900 text-xs">
                                                    Hapus
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<div id="tambahModal" class="modal-overlay hidden">
    <div class="bg-white p-6 rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="flex justify-between items-center border-b pb-3 mb-4">
            <h3 class="text-xl font-semibold text-gray-800">Ajukan Tanggal Cuti Massal</h3>
            <button onclick="closeModal('tambahModal')" class="text-gray-500 hover:text-gray-800">&times;</button>
        </div>
        
        <form action="hr_cuti_massal.php?tahun=<?php echo $filter_tahun; ?>" method="POST" class="space-y-4">
            <div>
                <label for="tanggal_cuti" class="form-label">Tanggal Cuti <span class="text-red-500">*</span></label>
                <input type="date" id="tanggal_cuti" name="tanggal_cuti" class="form-input" required>
            </div>
            
            <div>
                <label for="keterangan" class="form-label">Keterangan <span class="text-red-500">*</span></label>
                <input type="text" id="keterangan" name="keterangan" class="form-input" placeholder="Contoh: Cuti Bersama Idul Fitri" required>
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <button
                    type="button"
                    onclick="closeModal('tambahModal')"
                    class="btn-primary-sm btn-secondary">
                    Batal
                </button>
                <button
                    type="submit"
                    name="tambah_cuti"
                    class="btn-primary-sm bg-blue-600 hover:bg-blue-700">
                    Ajukan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('hidden');
        }
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('hidden');
        }
    }
    
    // Tutup modal jika klik di luar area modal
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeModal(modal.id);
            }
        });
    });
</script>


<?php
// 8. Panggil footer
require '../../includes/footer.php';
?>