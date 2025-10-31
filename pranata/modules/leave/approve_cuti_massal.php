<?php
// pranata/modules/leave/approve_cuti_massal.php

// 1. Panggil db.php dan lakukan Pengecekan Hak Akses
require '../../includes/db.php';

// Pengecekan Hak Akses
$app_akses = $_SESSION['app_akses'] ?? 'Karyawan';
$is_admin = ($app_akses == 'Admin');
$is_top_management = ($app_akses == 'Top Management');
$user_id = $_SESSION['user_id']; // ID Direktur yang sedang login

if (!$is_admin && !$is_top_management) {
    $_SESSION['flash_message'] = "Anda tidak memiliki hak akses untuk halaman persetujuan ini.";
    header("Location: ". BASE_URL. "/dashboard.php");
    exit;
}

// 2. Logika POST (Approve / Reject)
$errors = [];
$success_message = '';
$filter_tahun_post = $_GET['tahun'] ?? date('Y'); // Ambil tahun untuk redirect

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $cuti_id = $_POST['cuti_id'] ?? 0;
    $new_status = '';
    $action_text = '';
    
    if (isset($_POST['action_name']) && $_POST['action_name'] == 'approve_cuti') {
        $new_status = 'Approved';
        $action_text = 'disetujui';
    } elseif (isset($_POST['action_name']) && $_POST['action_name'] == 'reject_cuti') {
        $new_status = 'Rejected';
        $action_text = 'ditolak';
    }

    if (!empty($new_status) && $cuti_id > 0) {
        $stmt_update = $conn->prepare(
            "UPDATE collective_leave 
             SET status = ?, processed_by_id = ? 
             WHERE id = ? AND status = 'Pending'"
        );
        $stmt_update->bind_param("sii", $new_status, $user_id, $cuti_id);
        
        if ($stmt_update->execute()) {
            if ($stmt_update->affected_rows > 0) {
                // [PERUBAHAN] Simpan pesan sukses ke session dan redirect
                $_SESSION['flash_message'] = "Usulan cuti massal berhasil ". $action_text. ".";
            } else {
                $_SESSION['flash_error'] = "Gagal memproses: Usulan tidak ditemukan atau sudah diproses.";
            }
        } else {
             $_SESSION['flash_error'] = "Error saat update data: ". $stmt_update->error;
        }
        $stmt_update->close();
        
        // Redirect kembali ke halaman ini dengan filter tahun yang sama
        header("Location: approve_cuti_massal.php?tahun=" . $filter_tahun_post);
        exit;
    }
}

// Ambil flash messages dari session (setelah redirect)
if (isset($_SESSION['flash_message'])) {
    $success_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}
if (isset($_SESSION['flash_error'])) {
    $errors[] = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}


// 3. Set variabel halaman
$page_title = "Persetujuan Cuti Massal";
$page_active = "approve_cuti_massal";

// 4. Panggil header.php
require '../../includes/header.php';

// 5. Logika GET (Ambil data 'Pending' dan Riwayat)
$filter_tahun = $_GET['tahun'] ?? date('Y'); // Default tahun ini

// Kueri 1: Pending
$daftar_usulan = [];
$sql_pending = "SELECT 
                cl.id, cl.tanggal_cuti, cl.keterangan,
                u_propose.nama_lengkap AS nama_pengusul
            FROM collective_leave cl
            JOIN users u_propose ON cl.proposed_by_id = u_propose.id
            WHERE cl.status = 'Pending' AND cl.tahun = ?
            ORDER BY cl.tanggal_cuti ASC";
$stmt_pending = $conn->prepare($sql_pending);
$stmt_pending->bind_param("s", $filter_tahun);
$stmt_pending->execute();
$result_pending = $stmt_pending->get_result();
while ($row = $result_pending->fetch_assoc()) {
    $daftar_usulan[] = $row;
}
$stmt_pending->close();

// [PENAMBAHAN BARU] Kueri 2: Approved
$daftar_approved = [];
$sql_approved = "SELECT 
                cl.id, cl.tanggal_cuti, cl.keterangan,
                u_propose.nama_lengkap AS nama_pengusul,
                u_process.nama_lengkap AS nama_pemroses
            FROM collective_leave cl
            JOIN users u_propose ON cl.proposed_by_id = u_propose.id
            LEFT JOIN users u_process ON cl.processed_by_id = u_process.id
            WHERE cl.status = 'Approved' AND cl.tahun = ?
            ORDER BY cl.tanggal_cuti ASC";
$stmt_approved = $conn->prepare($sql_approved);
$stmt_approved->bind_param("s", $filter_tahun);
$stmt_approved->execute();
$result_approved = $stmt_approved->get_result();
while ($row = $result_approved->fetch_assoc()) {
    $daftar_approved[] = $row;
}
$stmt_approved->close();

// [PENAMBAHAN BARU] Kueri 3: Rejected
$daftar_rejected = [];
$sql_rejected = "SELECT 
                cl.id, cl.tanggal_cuti, cl.keterangan,
                u_propose.nama_lengkap AS nama_pengusul,
                u_process.nama_lengkap AS nama_pemroses
            FROM collective_leave cl
            JOIN users u_propose ON cl.proposed_by_id = u_propose.id
            LEFT JOIN users u_process ON cl.processed_by_id = u_process.id
            WHERE cl.status = 'Rejected' AND cl.tahun = ?
            ORDER BY cl.tanggal_cuti ASC";
$stmt_rejected = $conn->prepare($sql_rejected);
$stmt_rejected->bind_param("s", $filter_tahun);
$stmt_rejected->execute();
$result_rejected = $stmt_rejected->get_result();
while ($row = $result_rejected->fetch_assoc()) {
    $daftar_rejected[] = $row;
}
$stmt_rejected->close();


// 6. Panggil sidebar.php
require '../../includes/sidebar.php';
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Persetujuan Cuti Massal</h1>

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
        <form action="approve_cuti_massal.php" method="GET" class="card-content">
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

    <div class="card mb-6">
        <div class="card-content">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Daftar Usulan Menunggu Persetujuan (Tahun <?php echo htmlspecialchars($filter_tahun); ?>)</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Cuti</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Keterangan</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Diajukan Oleh</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($daftar_usulan)): ?>
                            <tr>
                                <td colspan="4" class="px-4 py-4 text-center text-sm text-gray-500">
                                    Tidak ada usulan cuti massal yang menunggu persetujuan untuk tahun ini.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($daftar_usulan as $data): ?>
                                <tr>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo date('d M Y', strtotime($data['tanggal_cuti'])); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($data['keterangan']); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($data['nama_pengusul']); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm space-x-2">
                                        <button type="button" 
                                                onclick="openConfirmationModal('approve_cuti', <?php echo $data['id']; ?>, '<?php echo date('d M Y', strtotime($data['tanggal_cuti'])); ?>', '<?php echo htmlspecialchars(addslashes($data['keterangan'])); ?>', 'Menyetujui', 'bg-green-600', 'hover:bg-green-700')"
                                                class="btn-primary-sm bg-green-600 hover:bg-green-700">
                                            Approve
                                        </button>
                                        <button type="button" 
                                                onclick="openConfirmationModal('reject_cuti', <?php echo $data['id']; ?>, '<?php echo date('d M Y', strtotime($data['tanggal_cuti'])); ?>', '<?php echo htmlspecialchars(addslashes($data['keterangan'])); ?>', 'Menolak', 'bg-red-600', 'hover:bg-red-700')"
                                                class="btn-primary-sm bg-red-600 hover:bg-red-700">
                                            Reject
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="card mb-6">
        <div class="card-content">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Daftar Cuti Massal Disetujui (Tahun <?php echo htmlspecialchars($filter_tahun); ?>)</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Cuti</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Keterangan</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Diajukan Oleh</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Disetujui Oleh</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($daftar_approved)): ?>
                            <tr>
                                <td colspan="4" class="px-4 py-4 text-center text-sm text-gray-500">
                                    Belum ada data cuti massal yang disetujui tahun ini.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($daftar_approved as $data): ?>
                                <tr>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo date('d M Y', strtotime($data['tanggal_cuti'])); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($data['keterangan']); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($data['nama_pengusul']); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($data['nama_pemroses'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-content">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Daftar Cuti Massal Ditolak (Tahun <?php echo htmlspecialchars($filter_tahun); ?>)</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Cuti</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Keterangan</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Diajukan Oleh</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ditolak Oleh</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($daftar_rejected)): ?>
                            <tr>
                                <td colspan="4" class="px-4 py-4 text-center text-sm text-gray-500">
                                    Tidak ada data cuti massal yang ditolak tahun ini.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($daftar_rejected as $data): ?>
                                <tr>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo date('d M Y', strtotime($data['tanggal_cuti'])); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($data['keterangan']); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($data['nama_pengusul']); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($data['nama_pemroses'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<div id="confirmationModal" class="modal-overlay hidden">
    <div class="bg-white p-6 rounded-lg shadow-xl max-w-lg w-full mx-4">
        <h3 class="text-xl font-semibold text-gray-800" id="modalTitle">Konfirmasi Tindakan</h3>
        <div class="my-4 text-gray-700">
            <p>Anda akan <strong id="modalActionText" class="font-bold"></strong> usulan cuti massal:</p>
            <div class="bg-gray-50 p-3 rounded mt-2 border">
                <dl class="grid grid-cols-3 gap-x-4">
                    <dt class="text-sm font-medium text-gray-500 col-span-1">Tanggal</dt>
                    <dd class="text-sm text-gray-900 col-span-2 font-semibold" id="modalTanggal"></dd>
                    
                    <dt class="text-sm font-medium text-gray-500 col-span-1">Keterangan</dt>
                    <dd class="text-sm text-gray-900 col-span-2 font-semibold" id="modalKeterangan"></dd>
                </dl>
            </div>
            <p class="mt-4">Apakah Anda yakin ingin melanjutkan?</p>
        </div>
        
        <form id="modalForm" action="approve_cuti_massal.php?tahun=<?php echo $filter_tahun; ?>" method="POST">
            <input type="hidden" name="cuti_id" id="modalCutiId">
            <input type="hidden" name="action_name" id="modalActionName">
            
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" onclick="closeModal('confirmationModal')" class="btn-primary-sm btn-secondary">
                    Batal
                </button>
                <button type="submit" id="modalConfirmButton" class="btn-primary-sm">
                    Ya, Lanjutkan
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// 8. Panggil footer
require '../../includes/footer.php';
?>

<script>
    const modal = document.getElementById('confirmationModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalActionText = document.getElementById('modalActionText');
    const modalTanggal = document.getElementById('modalTanggal');
    const modalKeterangan = document.getElementById('modalKeterangan');
    const modalCutiId = document.getElementById('modalCutiId');
    const modalActionName = document.getElementById('modalActionName');
    const modalConfirmButton = document.getElementById('modalConfirmButton');

    function openConfirmationModal(action, cutiId, tanggal, keterangan, actionText, btnClass, btnHoverClass) {
        if (!modal) return;
        modalTitle.innerText = 'Konfirmasi ' + actionText;
        modalActionText.innerText = actionText;
        modalTanggal.innerText = tanggal;
        modalKeterangan.innerText = keterangan;
        modalCutiId.value = cutiId;
        modalActionName.value = action;
        modalConfirmButton.classList.remove('bg-green-600', 'hover:bg-green-700', 'bg-red-600', 'hover:bg-red-700');
        modalConfirmButton.classList.add(btnClass, btnHoverClass);
        modal.classList.remove('hidden');
    }

    function closeModal(modalId) {
        const modalToClose = document.getElementById(modalId);
        if (modalToClose) {
            modalToClose.classList.add('hidden');
        }
    }
    
    if (modal) {
        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeModal('confirmationModal');
            }
        });
    }
</script>