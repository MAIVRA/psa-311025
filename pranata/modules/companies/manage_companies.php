<?php
// modules/companies/manage_companies.php

// 1. Set variabel khusus untuk halaman ini
$page_title = "Manage Companies";
$page_active = "manage_companies"; // Untuk menandai menu di sidebar

// === [PERBAIKAN PATH: MENGGUNAKAN BASE_PATH] ===
require dirname(dirname(__DIR__)) . '/includes/header.php'; // BASE_PATH.'/includes/header.php'
// === [AKHIR PERUBAHAN] ===

// 3. Proteksi Halaman Khusus (Hanya Admin)
if ($tier != 'Admin') {
    // === [PERBAIKAN PATH: MENGGUNAKAN BASE_URL] ===
    echo "<script>alert('Anda tidak memiliki hak akses untuk halaman ini.'); window.location.href = '" . BASE_URL . "/dashboard.php';</script>";
    // === [AKHIR PERUBAHAN] ===
    exit;
}

// 4. Inisialisasi variabel dan ambil data perusahaan
$companies_list = [];
$error_message_onload = ''; // Untuk error saat load data
$conn_get = new mysqli($servername, $username, $password, $dbname);
if ($conn_get->connect_error) {
    $error_message_onload = "Koneksi Gagal: " . $conn_get->connect_error;
} else {
    $conn_get->set_charset("utf8mb4");
    
    // Query untuk mengambil data perusahaan, di-JOIN dengan akta terakhir
    $sql = "
        SELECT 
            c.id, c.nama_perusahaan, c.tanggal_pendirian, c.tempat_kedudukan, c.modal_disetor,
            d.nomor_akta AS akta_terakhir
        FROM 
            companies c
        LEFT JOIN 
            deeds d ON c.id_akta_terakhir = d.id
        ORDER BY 
            c.nama_perusahaan ASC
    ";
    
    $result = $conn_get->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $companies_list[] = $row;
        }
    } else {
         $error_message_onload = "Error query: " . $conn_get->error;
    }
    $conn_get->close();
}

// 5. Include template sidebar
// === [PERBAIKAN PATH: MENGGUNAKAN BASE_PATH] ===
require dirname(dirname(__DIR__)) . '/includes/sidebar.php'; // BASE_PATH.'/includes/sidebar.php'
// === [AKHIR PERUBAHAN] ===

// 6. Cek Flash Message dari Session (Untuk notifikasi)
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
        <a href="<?php echo BASE_URL; ?>/modules/companies/add_company.php" 
           class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition duration-200 flex items-center shadow-md no-underline">
           <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
           Tambah Perusahaan Baru
        </a>
        </div>
    
    <?php if (!empty($error_message_onload)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4" role="alert">
            <?php echo htmlspecialchars($error_message_onload); ?>
        </div>
    <?php endif; ?>

    <div class="card border border-gray-200">
        <div class="card-content overflow-x-auto">
            <table class="w-full min-w-max">
                <thead>
                    <tr class="bg-gray-100 text-left text-sm font-semibold text-gray-600 uppercase">
                        <th class="py-3 px-4">Nama Perusahaan</th>
                        <th class="py-3 px-4">Tanggal Pendirian</th>
                        <th class="py-3 px-4">Tempat Kedudukan</th>
                        <th class="py-3 px-4">Modal Disetor</th>
                        <th class="py-3 px-4">Akta Terakhir</th>
                        <th class="py-3 px-4">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700 text-sm">
                    <?php if (empty($companies_list)): ?>
                        <tr>
                            <td colspan="6" class="py-4 px-4 text-center text-gray-500">Belum ada data perusahaan.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($companies_list as $company): ?>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-3 px-4 font-medium"><?php echo htmlspecialchars($company['nama_perusahaan']); ?></td>
                                <td class="py-3 px-4"><?php echo !empty($company['tanggal_pendirian']) ? date('d M Y', strtotime($company['tanggal_pendirian'])) : 'N/A'; ?></td>
                                <td class="py-3 px-4"><?php echo htmlspecialchars($company['tempat_kedudukan'] ?? 'N/A'); ?></td>
                                <td class="py-3 px-4">Rp <?php echo number_format($company['modal_disetor'], 0, ',', '.'); ?></td>
                                <td class="py-3 px-4"><?php echo htmlspecialchars($company['akta_terakhir'] ?? 'N/A'); ?></td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <a href="<?php echo BASE_URL; ?>/modules/companies/view_company.php?id=<?php echo $company['id']; ?>" class="text-blue-600 hover:text-blue-800 font-medium mr-3 no-underline">Detail</a>
                                    <a href="<?php echo BASE_URL; ?>/modules/companies/edit_company.php?id=<?php echo $company['id']; ?>" class="text-green-600 hover:text-green-800 font-medium no-underline">Edit</a>
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
// === [PERBAIKAN PATH: MENGGUNAKAN BASE_PATH] ===
require dirname(dirname(__DIR__)) . '/includes/footer.php'; // BASE_PATH.'/includes/footer.php'
// === [AKHIR PERUBAHAN] ===
?>