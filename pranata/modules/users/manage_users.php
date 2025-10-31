<?php
// manage_users.php

// 1. Set variabel khusus untuk halaman ini
$page_title = "Manage Users";
$page_active = "manage_users";

// 2. Include template header
// === [PATH DIPERBAIKI] ===
// Keluar 2 level (dari modules/users/ ke pranata/) lalu masuk ke includes/
require '../../includes/header.php';
// === [AKHIR PERUBAHAN] ===

// 3. Proteksi Halaman Khusus (Hanya Admin)
if ($tier != 'Admin') {
    // === [PERBAIKAN PATH: MENGGUNAKAN BASE_URL] ===
    // Gunakan BASE_URL untuk JS redirect
    echo "<script>alert('Anda tidak memiliki hak akses untuk halaman ini.'); window.location.href = '" . BASE_URL . "/dashboard.php';</script>";
    // === [AKHIR PERUBAHAN] ===
    exit;
}

// 4. Inisialisasi variabel dan ambil data user
$users_list = [];
$error_message_onload = ''; // Initialize error message
$conn_get = new mysqli($servername, $username, $password, $dbname);
if ($conn_get->connect_error) {
    // Tampilkan error jika koneksi gagal, tapi jangan die() agar halaman tetap render
    $error_message_onload = "Koneksi Gagal: " . $conn_get->connect_error;
} else {
    $conn_get->set_charset("utf8mb4");

    // Query untuk mengambil data user
    $sql = " SELECT u.id, u.nik, u.nama_lengkap, u.email, u.nama_jabatan, u.tier, u.status_karyawan, dir.nama_direktorat, dv.nama_divisi, dep.nama_departemen FROM users u LEFT JOIN direktorat dir ON u.id_direktorat = dir.id LEFT JOIN divisi dv ON u.id_divisi = dv.id LEFT JOIN departemen dep ON u.id_departemen = dep.id ORDER BY u.nama_lengkap ASC ";
    $result = $conn_get->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users_list[] = $row;
        }
    } else {
        $error_message_onload = "Error query: " . $conn_get->error;
    }
    $conn_get->close();
}

// 5. Include template sidebar
// === [PATH DIPERBAIKI] ===
require '../../includes/sidebar.php';
// === [AKHIR PERUBAHAN] ===

// === [PERUBAHAN DI SINI] Flash message dipindahkan ke atas ===
// 6. Cek Flash Message dari Session
$flash_message = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']); // Hapus setelah dibaca
}
?>

<div id="flashModal" class="modal-overlay <?php echo empty($flash_message) ? 'hidden' : ''; // Tampilkan jika ada pesan ?>">
    <div class="bg-white p-6 rounded-lg shadow-xl max-w-sm w-full mx-4">
        <div class="flex items-center">
            <div class="flex-shrink-0 bg-green-100 rounded-full p-2">
                 <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            </div>
            <div class="ml-4">
                <h3 class="text-lg font-semibold text-gray-800">Sukses!</h3>
                <p id="flashModalMessage" class="mt-1 text-gray-600"><?php echo htmlspecialchars($flash_message); // Langsung isi pesannya ?></p>
            </div>
        </div>
        <div class="mt-6 flex justify-end">
            <button
                type="button"
                onclick="closeFlashModal()"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition duration-200">
                OK
            </button>
        </div>
    </div>
</div>

<script>
function closeFlashModal() {
    const modal = document.getElementById('flashModal');
    if(modal) modal.classList.add('hidden');
}
</script>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">

    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800"><?php echo $page_title; ?></h1>
        
        <a href="<?php echo BASE_URL; ?>/modules/users/add_user.php" class="btn-primary-sm flex items-center shadow-md px-4 py-2 text-sm font-semibold no-underline">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
           Tambah User Baru
        </a>
    </div>

    <?php if (!empty($error_message_onload)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4" role="alert">
            <?php echo htmlspecialchars($error_message_onload); ?>
        </div>
    <?php endif; ?>


    <div class="card">
        <div class="card-content overflow-x-auto">
            <table class="w-full min-w-max">
                <thead>
                    <tr class="bg-gray-100 text-left text-sm font-semibold text-gray-600 uppercase">
                        <th class="py-3 px-4">NIK</th>
                        <th class="py-3 px-4">Nama Lengkap</th>
                        <th class="py-3 px-4">Email</th>
                        <th class="py-3 px-4">Jabatan</th>
                        <th class="py-3 px-4">Struktur</th>
                        <th class="py-3 px-4">Tier</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700 text-sm">
                    <?php if (empty($users_list)): ?>
                        <tr> <td colspan="8" class="py-4 px-4 text-center text-gray-500">Belum ada data user.</td> </tr>
                    <?php else: ?>
                        <?php foreach ($users_list as $user): ?>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-3 px-4"><?php echo htmlspecialchars($user['nik']); ?></td>
                                <td class="py-3 px-4 font-medium"><?php echo htmlspecialchars($user['nama_lengkap']); ?></td>
                                <td class="py-3 px-4"><?php echo htmlspecialchars($user['email']); ?></td>
                                <td class="py-3 px-4"><?php echo htmlspecialchars($user['nama_jabatan'] ?? 'N/A'); ?></td>
                                <td class="py-3 px-4 text-xs">
                                    <span class="block"><?php echo htmlspecialchars($user['nama_direktorat'] ?? '-'); ?></span>
                                    <span class="block"><?php echo htmlspecialchars($user['nama_divisi'] ?? '-'); ?></span>
                                    <span class="block"><?php echo htmlspecialchars($user['nama_departemen'] ?? '-'); ?></span>
                                </td>
                                <td class="py-3 px-4"><?php echo htmlspecialchars($user['tier']); ?></td>
                                <td class="py-3 px-4">
                                    <?php if ($user['status_karyawan'] == 'PKWT'): ?>
                                        <span class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-2.5 py-0.5 rounded-full">PKWT</span>
                                    <?php elseif ($user['status_karyawan'] == 'PKWTT'): ?>
                                        <span class="bg-green-100 text-green-800 text-xs font-semibold px-2.5 py-0.5 rounded-full">PKWTT</span>
                                    <?php else: ?>
                                         <span class="bg-gray-100 text-gray-800 text-xs font-semibold px-2.5 py-0.5 rounded-full"><?php echo htmlspecialchars($user['status_karyawan']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4">
                                    <a href="<?php echo BASE_URL; ?>/modules/users/edit_user.php?id=<?php echo $user['id']; ?>" class="text-blue-600 hover:text-blue-800 font-medium">Edit</a>
                                    </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<?php
// 7. Include template footer
// === [PATH DIPERBAIKI] ===
require '../../includes/footer.php';
// === [AKHIR PERUBAHAN] ===
?>