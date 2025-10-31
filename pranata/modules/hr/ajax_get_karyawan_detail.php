<?php
// modules/hr/ajax_get_karyawan_detail.php
// Endpoint ini HANYA mengembalikan HTML snippet untuk modal

// 1. Panggil koneksi DB dan mulai session
require '../../includes/db.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Helper Functions (Karena file ini tidak me-load header.php)
if (!function_exists('showData')) {
    function showData($data, $default = '-') {
        return !empty($data) ? htmlspecialchars($data) : $default;
    }
}
if (!function_exists('formatIndonesianDate')) {
    function formatIndonesianDate($dateStr) {
        if (empty($dateStr) || strtotime($dateStr) === false) return '-';
        $currentLocale = setlocale(LC_TIME, 0);
        setlocale(LC_TIME, 'id_ID.UTF-8', 'Indonesian_Indonesia.1252', 'Indonesian');
        $formattedDate = strftime('%d %B %Y', strtotime($dateStr));
        setlocale(LC_TIME, $currentLocale);
        return $formattedDate;
    }
}

// 3. Keamanan Endpoint
$app_akses = $_SESSION['app_akses'] ?? 'Karyawan';
if ($app_akses != 'HR' && $app_akses != 'Admin') {
    echo '<p class="text-red-500">Akses ditolak.</p>';
    exit;
}

// 4. Validasi Input
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    echo '<p class="text-red-500">ID karyawan tidak valid.</p>';
    exit;
}
$user_id = (int)$_GET['id'];

// 5. Query data detail karyawan
$sql = "SELECT 
            u.*, 
            d.nama_departemen, 
            dv.nama_divisi,
            dir.nama_direktorat,
            atasan.nama_lengkap as nama_atasan
        FROM users u
        LEFT JOIN departemen d ON u.id_departemen = d.id
        LEFT JOIN divisi dv ON u.id_divisi = dv.id
        LEFT JOIN direktorat dir ON u.id_direktorat = dir.id
        LEFT JOIN users atasan ON u.atasan_id = atasan.id
        WHERE u.id = ? AND u.app_akses != 'Admin'";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    echo '<p class="text-red-500">Gagal mempersiapkan query: ' . $conn->error . '</p>';
    exit;
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo '<p class="text-gray-500">Data karyawan tidak ditemukan.</p>';
    exit;
}

$k = $result->fetch_assoc();
$stmt->close();
$conn->close();

// 6. Generate HTML Snippet
?>

<style>
    /* Definisikan style profile-label & profile-data di sini agar ter-load di modal */
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
    /* Style untuk header bagian */
    .section-header {
        font-size: 1.125rem; /* text-lg */
        font-weight: 600; /* font-semibold */
        color: #1d4ed8; /* blue-700 */
        border-bottom: 2px solid #e5e7eb; /* border-gray-200 */
        padding-bottom: 0.5rem; /* pb-2 */
        margin-top: 1.25rem; /* mt-5 */
        margin-bottom: 0.75rem; /* mb-3 */
    }
    .section-header:first-of-type {
        margin-top: 0; /* Hapus margin atas untuk header pertama */
    }
</style>

<div class="space-y-4">
    
    <h4 class="section-header">Informasi Pekerjaan</h4>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-4">
        <div>
            <span class="profile-label">Nama Lengkap</span>
            <p class="profile-data"><?php echo showData($k['nama_lengkap']); ?></p>
        </div>
        <div>
            <span class="profile-label">NIK</span>
            <p class="profile-data"><?php echo showData($k['nik']); ?></p>
        </div>
        <div>
            <span class="profile-label">Status Karyawan</span>
            <p class="profile-data"><?php echo showData($k['status_karyawan']); ?></p>
        </div>
        <div>
            <span class="profile-label">Jabatan</span>
            <p class="profile-data"><?php echo showData($k['nama_jabatan']); ?></p>
        </div>
        <div>
            <span class="profile-label">Departemen</span>
            <p class="profile-data"><?php echo showData($k['nama_departemen']); ?></p>
        </div>
        <div>
            <span class="profile-label">Divisi</span>
            <p class="profile-data"><?php echo showData($k['nama_divisi']); ?></p>
        </div>
        <div>
            <span class="profile-label">Atasan Langsung</span>
            <p class="profile-data"><?php echo showData($k['nama_atasan']); ?></p>
        </div>
        <div>
            <span class="profile-label">Tanggal Masuk</span>
            <p class="profile-data"><?php echo formatIndonesianDate($k['tanggal_masuk']); ?></p>
        </div>
        <?php if ($k['status_karyawan'] == 'PKWT'): ?>
        <div>
            <span class="profile-label">Tanggal Habis Kontrak</span>
            <p class="profile-data font-bold text-red-600"><?php echo formatIndonesianDate($k['expired_pkwt']); ?></p>
        </div>
        <?php endif; ?>
    </div>

    <h4 class="section-header">Informasi Pribadi & Kontak</h4>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-4">
        <div>
            <span class="profile-label">Email</span>
            <p class="profile-data"><?php echo showData($k['email']); ?></p>
        </div>
        <div>
            <span class="profile-label">Telepon</span>
            <p class="profile-data"><?php echo showData($k['telepon']); ?></p>
        </div>
        <div>
            <span class="profile-label">No. KTP</span>
            <p class="profile-data"><?php echo showData($k['no_ktp']); ?></p>
        </div>
        <div>
            <span class="profile-label">NPWP</span>
            <p class="profile-data"><?php echo showData($k['npwp']); ?></p>
        </div>
        <div class="md:col-span-2">
            <span class="profile-label">Alamat</span>
            <p class="profile-data"><?php echo showData($k['alamat']); ?></p>
        </div>
    </div>

    <h4 class="section-header">Informasi Bank</h4>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-4">
        <div>
            <span class="profile-label">Nama Bank</span>
            <p class="profile-data"><?php echo showData($k['bank_nama']); ?></p>
        </div>
        <div>
            <span class="profile-label">No. Rekening</span>
            <p class="profile-data"><?php echo showData($k['bank_rekening']); ?></p>
        </div>
        <div>
            <span class="profile-label">Atas Nama Rekening</span>
            <p class="profile-data"><?php echo showData($k['bank_atas_nama']); ?></p>
        </div>
    </div>
    
    <h4 class="section-header">Kontak Darurat</h4>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-4">
        <div>
            <span class="profile-label">Nama Kontak Darurat</span>
            <p class="profile-data"><?php echo showData($k['kontak_darurat_nama']); ?></p>
        </div>
        <div>
            <span class="profile-label">Hubungan</span>
            <p class="profile-data"><?php echo showData($k['kontak_darurat_hubungan']); ?></p>
        </div>
        <div>
            <span class="profile-label">Telepon Kontak Darurat</span>
            <p class="profile-data"><?php echo showData($k['kontak_darurat_telepon']); ?></p>
        </div>
    </div>
</div>