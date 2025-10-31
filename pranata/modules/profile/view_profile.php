<?php
// modules/profile/view_profile.php

// 1. Set variabel halaman
$page_title = "Lihat Profile";
$page_active = "profile"; // $page_active 'profile' akan di-highlight oleh header/sidebar nanti

// 2. Mulai session dan panggil header
require '../../includes/header.php';
// $user_id, $nama_lengkap, $tier, $app_akses sudah ada dari header.php

// === [PENAMBAHAN BARU: Logika Flash Message] ===
$flash_message = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']); // Hapus setelah dibaca
}
// === [AKHIR PENAMBAHAN] ===

$user_data = []; // Untuk menampung data user

// 3. LOGIKA GET (Load Data)
// === [MODIFIKASI SQL: Mengganti alias 'div' dan 'dep'] ===
$stmt_get = $conn->prepare("
    SELECT 
        u.*, 
        dir.nama_direktorat, 
        dv.nama_divisi, 
        dp.nama_departemen,
        atasan.nama_lengkap as nama_atasan
    FROM users u
    LEFT JOIN direktorat dir ON u.id_direktorat = dir.id
    LEFT JOIN divisi dv ON u.id_divisi = dv.id
    LEFT JOIN departemen dp ON u.id_departemen = dp.id
    LEFT JOIN users atasan ON u.atasan_id = atasan.id
    WHERE u.id = ?
");
// === [AKHIR MODIFIKASI SQL] ===

if ($stmt_get === false) {
    die("Error: Gagal mempersiapkan statement select: " . $conn->error);
}
$stmt_get->bind_param("i", $user_id);
$stmt_get->execute();
$result = $stmt_get->get_result();
if ($result->num_rows == 0) {
    die("Error: User tidak ditemukan.");
}
$user_data = $result->fetch_assoc();
$stmt_get->close();

// Tentukan path foto profil
$foto_display = BASE_URL . '/logo.png'; // Default
if (!empty($user_data['foto_profile_path']) && file_exists(BASE_PATH . '/' . $user_data['foto_profile_path'])) {
    $foto_display = BASE_URL . '/' . htmlspecialchars($user_data['foto_profile_path']) . '?v=' . time(); // Cache buster
}

// Helper function untuk menampilkan data atau '-'
function showData($data) {
    return !empty($data) ? htmlspecialchars($data) : '-';
}
function formatDate($dateStr) {
    if (empty($dateStr)) return '-';
    // Cek format tanggal valid sebelum konversi
    if (strtotime($dateStr) === false) return '-';
    return date('d M Y', strtotime($dateStr));
}

// 4. Panggil Sidebar
require '../../includes/sidebar.php';
?>

<style>
    .profile-label {
        font-size: 0.875rem; /* text-sm */
        font-weight: 500; /* font-medium */
        color: #6b7280; /* gray-500 */
    }
    .profile-data {
        font-size: 1rem; /* text-base */
        font-weight: 500; /* font-medium */
        color: #1f2937; /* gray-800 */
        word-wrap: break-word; /* Agar alamat panjang tidak merusak layout */
    }
    .profile-section {
        padding-bottom: 1.25rem; /* pb-5 */
        margin-bottom: 1.25rem; /* mb-5 */
        border-bottom: 1px solid #e5e7eb; /* border-b border-gray-200 */
    }
    .profile-section:last-child {
        border-bottom: 0;
        margin-bottom: 0;
        padding-bottom: 0;
    }
</style>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    <div class="container mx-auto max-w-4xl">
        
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">Profile Saya</h1>
            
            <div class="flex space-x-2">
                <a href="<?php echo BASE_URL; ?>/dashboard.php" class="btn-primary-sm btn-secondary flex items-center shadow-md px-4 py-2 text-sm font-semibold no-underline">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Kembali ke Dasbor
                </a>
                <a href="edit_profile.php" class="btn-primary-sm flex items-center shadow-md px-4 py-2 text-sm font-semibold no-underline">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                    Edit Profile
                </a>
            </div>
            </div>

        <?php if (!empty($flash_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($flash_message); ?></span>
            </div>
        <?php endif; ?>
        <div class="card">
            <div class="card-content">
                
                <div class="profile-section flex flex-col md:flex-row items-center space-y-4 md:space-y-0 md:space-x-6">
                    <img src="<?php echo $foto_display; ?>" alt="Foto Profil" class="w-24 h-24 rounded-full object-cover border-4 border-gray-200">
                    <div class="text-center md:text-left">
                        <h2 class="text-2xl font-bold text-gray-900"><?php echo showData($user_data['nama_lengkap']); ?></h2>
                        <p class="profile-data text-gray-600"><?php echo showData($user_data['nama_jabatan']); ?></p>
                        <p class="profile-data text-gray-600"><?php echo showData($user_data['email']); ?></p>
                    </div>
                </div>

                <div class="profile-section">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800">Informasi Kepegawaian</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-y-4 gap-x-6">
                        <div>
                            <dt class="profile-label">NIK</dt>
                            <dd class="profile-data"><?php echo showData($user_data['nik']); ?></dd>
                        </div>
                        <div>
                            <dt class="profile-label">Status Karyawan</dt>
                            <dd class="profile-data"><?php echo showData($user_data['status_karyawan']); ?></dd>
                        </div>
                        <div>
                            <dt class="profile-label">Tanggal Masuk</dt>
                            <dd class="profile-data"><?php echo formatDate($user_data['tanggal_masuk']); ?></dd>
                        </div>
                        <?php if ($user_data['status_karyawan'] == 'PKWT'): ?>
                        <div>
                            <dt class="profile-label">Akhir PKWT</dt>
                            <dd class="profile-data text-red-600 font-bold"><?php echo formatDate($user_data['expired_pkwt']); ?></dd>
                        </div>
                        <?php endif; ?>
                        <div>
                            <dt class="profile-label">Penempatan</dt>
                            <dd class="profile-data"><?php echo showData($user_data['penempatan_kerja']); ?></dd>
                        </div>
                        <div>
                            <dt class="profile-label">Level Akun</dt>
                            <dd class="profile-data"><?php echo showData($user_data['tier']); ?> / <?php echo showData($user_data['app_akses']); ?></dd>
                        </div>
                    </div>
                </div>

                <div class="profile-section">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800">Struktur Organisasi</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-y-4 gap-x-6">
                        <div>
                            <dt class="profile-label">Atasan Langsung</dt>
                            <dd class="profile-data"><?php echo showData($user_data['nama_atasan']); ?></dd>
                        </div>
                        <div>
                            <dt class="profile-label">Direktorat</dt>
                            <dd class="profile-data"><?php echo showData($user_data['nama_direktorat']); ?></dd>
                        </div>
                        <div>
                            <dt class="profile-label">Divisi</dt>
                            <dd class="profile-data"><?php echo showData($user_data['nama_divisi']); ?></dd>
                        </div>
                        <div>
                            <dt class="profile-label">Departemen</dt>
                             <dd class="profile-data"><?php echo showData($user_data['nama_departemen']); ?></dd>
                        </div>
                    </div>
                </div>
                
                <div class="profile-section">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800">Data Pribadi & Kontak</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-6">
                        <div>
                            <dt class="profile-label">Nomor Telepon</dt>
                            <dd class="profile-data"><?php echo showData($user_data['telepon']); ?></dd>
                        </div>
                         <div>
                            <dt class="profile-label">No. KTP</dt>
                            <dd class="profile-data"><?php echo showData($user_data['no_ktp']); ?></dd>
                        </div>
                        <div class="md:col-span-2">
                            <dt class="profile-label">Alamat</dt>
                            <dd class="profile-data"><?php echo nl2br(showData($user_data['alamat'])); // Pakai nl2br agar baris baru tampil ?></dd>
                        </div>
                        <div>
                            <dt class="profile-label">NPWP</dt>
                            <dd class="profile-data"><?php echo showData($user_data['npwp']); ?></dd>
                        </div>
                    </div>
                </div>
                
                <div class="profile-section">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800">Informasi Bank (Payroll)</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-y-4 gap-x-6">
                        <div>
                            <dt class="profile-label">Nama Bank</dt>
                            <dd class="profile-data"><?php echo showData($user_data['bank_nama']); ?></dd>
                        </div>
                        <div>
                            <dt class="profile-label">Nomor Rekening</dt>
                            <dd class="profile-data"><?php echo showData($user_data['bank_rekening']); ?></dd>
                        </div>
                        <div>
                            <dt class="profile-label">Atas Nama</dt>
                            <dd class="profile-data"><?php echo showData($user_data['bank_atas_nama']); ?></dd>
                        </div>
                    </div>
                </div>

                <div class="profile-section">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800">Kontak Darurat</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-y-4 gap-x-6">
                        <div>
                            <dt class="profile-label">Nama</dt>
                            <dd class="profile-data"><?php echo showData($user_data['kontak_darurat_nama']); ?></dd>
                        </div>
                        <div>
                            <dt class="profile-label">Hubungan</dt>
                            <dd class="profile-data"><?php echo showData($user_data['kontak_darurat_hubungan']); ?></dd>
                        </div>
                        <div>
                            <dt class="profile-label">Nomor Telepon</dt>
                            <dd class="profile-data"><?php echo showData($user_data['kontak_darurat_telepon']); ?></dd>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</main>

<?php
// 5. Panggil Footer
require '../../includes/footer.php';
?>