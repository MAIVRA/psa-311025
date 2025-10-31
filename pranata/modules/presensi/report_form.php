<?php
// modules/presensi/report_form.php
// FILE INI HANYA UNTUK DILOAD KE DALAM MODAL.
// JANGAN MEMUAT header.php, sidebar.php, atau footer.php

// 1. Mulai session (jika belum) dan panggil koneksi DB
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Panggil koneksi DB secara langsung
require_once '../../includes/db.php'; 

// 2. Cek Hak Akses (PENTING untuk endpoint AJAX)
$tier = $_SESSION['tier'] ?? 'Staf'; 
$app_akses = $_SESSION['app_akses'] ?? 'Karyawan'; 

if (!($app_akses == 'HR' || $tier == 'Admin')) {
    // Jika tidak punya akses, tampilkan pesan error di dalam modal
    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg" role="alert">
            <strong class="font-bold">Akses Ditolak!</strong>
            <span class="block sm:inline">Anda tidak memiliki hak akses untuk memuat form ini.</span>
          </div>';
    exit; // Hentikan eksekusi file
}

$errors = [];
$karyawan_list = [];

// 3. Ambil daftar karyawan aktif untuk dropdown
$stmt_karyawan = $conn->prepare("
    SELECT id, nama_lengkap, nik 
    FROM users 
    WHERE status_karyawan IN ('PKWT', 'PKWTT', 'BOD', 'BOC') 
    ORDER BY nama_lengkap ASC
");
if ($stmt_karyawan) {
    $stmt_karyawan->execute();
    $result_karyawan = $stmt_karyawan->get_result();
    while ($row = $result_karyawan->fetch_assoc()) {
        $karyawan_list[] = $row;
    }
    $stmt_karyawan->close();
} else {
    $errors[] = "Gagal mengambil daftar karyawan: " . $conn->error;
}
$conn->close();

// Set default tanggal (periode bulan lalu)
$default_end_date = date('Y-m-d');
$default_start_date = date('Y-m-d', strtotime('-1 month'));

// BARIS 'require ../../includes/sidebar.php'; TELAH DIHAPUS
?>

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

<div class="card p-0 m-0 shadow-none"> <form action="generate_report.php" method="POST" target="_blank" class="card-content space-y-5">
        
        <div>
            <label for="karyawan_id" class="form-label">Pilih Karyawan <span class="text-red-500">*</span></label>
            <select id="karyawan_id" name="karyawan_id" class="form-input" required>
                <option value="">-- Pilih Karyawan --</option>
                <?php if (empty($karyawan_list)): ?>
                    <option disabled>Tidak ada data karyawan</option>
                <?php else: ?>
                    <?php foreach ($karyawan_list as $karyawan): ?>
                        <option value="<?php echo $karyawan['id']; ?>">
                            <?php echo htmlspecialchars($karyawan['nama_lengkap']); ?> (<?php echo htmlspecialchars($karyawan['nik']); ?>)
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="tanggal_mulai" class="form-label">Periode Mulai <span class="text-red-500">*</span></label>
                <input type="date" id="tanggal_mulai" name="tanggal_mulai" class="form-input" value="<?php echo $default_start_date; ?>" required>
            </div>
             <div>
                <label for="tanggal_akhir" class="form-label">Periode Akhir <span class="text-red-500">*</span></label>
                <input type="date" id="tanggal_akhir" name="tanggal_akhir" class="form-input" value="<?php echo $default_end_date; ?>" required>
            </div>
        </div>

        <div class="pt-4 border-t flex justify-end">
            <button type="submit" class="btn-primary-sm bg-purple-600 hover:bg-purple-700 flex items-center shadow-md px-5 py-2.5 text-sm font-semibold no-underline">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Generate Laporan Excel
            </button>
        </div>

    </form>
</div>
        
<?php
// BARIS 'require ../../includes/footer.php'; TELAH DIHAPUS
?>