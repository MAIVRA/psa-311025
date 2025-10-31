<?php
// modules/companies/edit_company.php

// 1. Set variabel khusus untuk halaman ini
$page_active = "manage_companies"; // Tetap tandai manage_companies sebagai menu aktif

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

// 4. Inisialisasi variabel dan ambil ID dari URL
$error_message = '';
$company_id_to_edit = $_GET['id'] ?? 0;
$company_data = null;
$logo_path_to_save = NULL; // Variabel untuk menyimpan path logo BARU (path DB)
$old_logo_path_db = NULL; // Variabel untuk menyimpan path logo LAMA (path DB)
$target_path_physical = null; // Path fisik file baru yg diupload
$new_logo_uploaded = false; // Flag


// 5. Validasi ID Perusahaan
if (empty($company_id_to_edit)) {
    $_SESSION['flash_message'] = "Error: ID Perusahaan tidak valid.";
    // === [PERBAIKAN PATH: MENGGUNAKAN BASE_URL] ===
    header("Location: " . BASE_URL . "/modules/companies/manage_companies.php");
    // === [AKHIR PERUBAHAN] ===
    exit;
}

// 6. Buka koneksi untuk GET dan POST
$conn_db = new mysqli($servername, $username, $password, $dbname);
if ($conn_db->connect_error) {
    die("Koneksi Gagal: " . $conn_db->connect_error);
}
$conn_db->set_charset("utf8mb4");

// 7. Logika saat form di-submit (method POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Mulai Transaksi
    $conn_db->begin_transaction();
    try {
        // Ambil path logo lama DARI DATABASE (untuk dihapus jika ada logo baru)
        $stmt_old_logo = $conn_db->prepare("SELECT logo_path FROM companies WHERE id = ?");
        if (!$stmt_old_logo) throw new Exception("Gagal prepare get old logo: " . $conn_db->error);
        $stmt_old_logo->bind_param("i", $company_id_to_edit);
        $stmt_old_logo->execute();
        $result_old_logo = $stmt_old_logo->get_result();
        $old_logo_data = $result_old_logo->fetch_assoc();
        $old_logo_path_db = $old_logo_data['logo_path'] ?? NULL; // Path relatif DB (misal: uploads/logos/...)
        $stmt_old_logo->close();

        // === [LOGIKA UPLOAD LOGO BARU] ===
        $logo_path_to_save = $old_logo_path_db; // Defaultnya pakai logo lama

        if (isset($_FILES['logo']) && $_FILES['logo']['error'] == UPLOAD_ERR_OK) {
            $new_logo_uploaded = true; // Tandai ada logo baru
            $file_info = $_FILES['logo'];
            $file_tmp_name = $file_info['tmp_name'];
            $file_size = $file_info['size'];

            $max_size = 2 * 1024 * 1024; // 2 MB
            if ($file_size > $max_size) throw new Exception("Ukuran file logo baru terlalu besar. Maksimal 2MB.");

            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $file_type = mime_content_type($file_tmp_name);
            if (!in_array($file_type, $allowed_types)) throw new Exception("Tipe file logo baru tidak valid. Hanya JPG, PNG, atau GIF.");

            $file_extension = pathinfo($file_info['name'], PATHINFO_EXTENSION);
            $unique_name = uniqid('logo_', true) . '.' . strtolower($file_extension);

            // === [PERBAIKAN PATH: MENGGUNAKAN BASE_PATH] ===
            // Path RELATIF untuk disimpan ke DB (dari root pranata/)
            $logo_path_to_save = "uploads/logos/" . $unique_name;
            // Path FISIK untuk memindahkan file
            $target_dir = BASE_PATH . "/uploads/logos/";
             // Pastikan folder fisik ada
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            $target_path_physical = $target_dir . $unique_name; // Path fisik lengkap
            // === [AKHIR PERUBAHAN] ===


            if (!move_uploaded_file($file_tmp_name, $target_path_physical)) {
                throw new Exception("Gagal memindahkan file logo baru yang di-upload.");
            }

        } elseif (isset($_FILES['logo']) && $_FILES['logo']['error'] != UPLOAD_ERR_NO_FILE) {
            throw new Exception("Terjadi error saat mengupload logo: error code " . $_FILES['logo']['error']);
        }
        // === [AKHIR LOGIKA UPLOAD] ===

        // Ambil data lain dari form
        $nama_perusahaan = trim($_POST['nama_perusahaan']);
        $tanggal_pendirian = !empty($_POST['tanggal_pendirian']) ? $_POST['tanggal_pendirian'] : NULL;
        $tempat_kedudukan = !empty($_POST['tempat_kedudukan']) ? $_POST['tempat_kedudukan'] : NULL;
        $alamat = !empty($_POST['alamat']) ? $_POST['alamat'] : NULL;
        $nib = !empty($_POST['nib']) ? $_POST['nib'] : NULL;
        $npwp = !empty($_POST['npwp']) ? $_POST['npwp'] : NULL;
        $akta_pendirian = !empty($_POST['akta_pendirian']) ? $_POST['akta_pendirian'] : NULL;
        $tanggal_akta_pendirian = !empty($_POST['tanggal_akta_pendirian']) ? $_POST['tanggal_akta_pendirian'] : NULL;
        $sk_ahu_pendirian = !empty($_POST['sk_ahu_pendirian']) ? $_POST['sk_ahu_pendirian'] : NULL;
        $tanggal_sk_ahu_pendirian = !empty($_POST['tanggal_sk_ahu_pendirian']) ? $_POST['tanggal_sk_ahu_pendirian'] : NULL;
        $notaris_pendirian = !empty($_POST['notaris_pendirian']) ? $_POST['notaris_pendirian'] : NULL;
        $domisili_notaris_pendirian = !empty($_POST['domisili_notaris_pendirian']) ? $_POST['domisili_notaris_pendirian'] : NULL;
        // === [PERBAIKAN TIPE DATA] ===
        $modal_dasar = !empty($_POST['modal_dasar']) ? (float)preg_replace('/[Rp. ]|\./', '', $_POST['modal_dasar']) : 0; // Hapus Rp, spasi, titik -> float
        $nilai_nominal_saham = !empty($_POST['nilai_nominal_saham']) ? (float)preg_replace('/[Rp. ]|\./', '', $_POST['nilai_nominal_saham']) : 0; // Hapus Rp, spasi, titik -> float
        // === [AKHIR PERBAIKAN] ===

        // Validasi Sederhana
        if (empty($nama_perusahaan)) {
            throw new Exception("Nama Perusahaan wajib diisi.");
        }

        // Siapkan query UPDATE
        $sql = "UPDATE companies SET
                    nama_perusahaan = ?, logo_path = ?, tanggal_pendirian = ?, tempat_kedudukan = ?,
                    alamat = ?, nib = ?, npwp = ?,
                    akta_pendirian = ?, tanggal_akta_pendirian = ?,
                    sk_ahu_pendirian = ?, tanggal_sk_ahu_pendirian = ?,
                    notaris_pendirian = ?, domisili_notaris_pendirian = ?,
                    modal_dasar = ?, nilai_nominal_saham = ?
                WHERE id = ?"; // Tambahkan WHERE clause

        $stmt = $conn_db->prepare($sql);
        if ($stmt === false) throw new Exception("Error persiapan statement: " . $conn_db->error);

        // === [PERBAIKAN TIPE DATA BIND] ===
        // Tipe data: s s s s s s s s s s s s d d i (13 string, 2 double, 1 id)
        $bind_types = "sssssssssssssdd" . "i";
        // === [AKHIR PERBAIKAN] ===
        
        $bind_vars = [
            $nama_perusahaan, $logo_path_to_save, $tanggal_pendirian, $tempat_kedudukan,
            $alamat, $nib, $npwp,
            $akta_pendirian, $tanggal_akta_pendirian,
            $sk_ahu_pendirian, $tanggal_sk_ahu_pendirian,
            $notaris_pendirian, $domisili_notaris_pendirian,
            $modal_dasar, $nilai_nominal_saham,
            $company_id_to_edit // ID untuk WHERE
        ];

         // Cek jumlah tipe vs jumlah variabel
        if (strlen($bind_types) != count($bind_vars)) {
             throw new Exception("Jumlah tipe bind (" . strlen($bind_types) . ") tidak cocok dengan jumlah variabel (" . count($bind_vars) . ").");
        }

        $stmt->bind_param($bind_types, ...$bind_vars);

        if (!$stmt->execute()) {
            // Jika UPDATE gagal, hapus file logo BARU yg sudah terlanjur di-upload
             // === [PERBAIKAN PATH: MENGGUNAKAN BASE_PATH] ===
            if ($new_logo_uploaded && $target_path_physical && file_exists($target_path_physical)) {
                unlink($target_path_physical);
            }
             // === [AKHIR PERUBAHAN] ===
            throw new Exception($conn_db->error);
        }
        $stmt->close();

        // Commit
        $conn_db->commit();

        // === [PERBAIKAN PATH: MENGGUNAKAN BASE_PATH] ===
        // Hapus file LAMA jika ada logo BARU dan path-nya beda
        $old_logo_physical_path = $old_logo_path_db ? BASE_PATH . "/" . $old_logo_path_db : null;
        if ($new_logo_uploaded && $old_logo_physical_path && file_exists($old_logo_physical_path)) {
            if ($old_logo_path_db != $logo_path_to_save) { // Bandingkan path DB
                 unlink($old_logo_physical_path);
            }
        }
        // === [AKHIR PERUBAHAN] ===
        
        // Set flash message dan redirect KEMBALI ke view_company.php
        $_SESSION['flash_message'] = "Data perusahaan '" . htmlspecialchars($nama_perusahaan) . "' berhasil diperbarui!";
        // === [PERBAIKAN PATH: MENGGUNAKAN BASE_URL] ===
        header("Location: " . BASE_URL . "/modules/companies/view_company.php?id=" . $company_id_to_edit);
        // === [AKHIR PERUBAHAN] ===
        exit;

    } catch (Exception $e) {
        $conn_db->rollback();
        // === [PERBAIKAN PATH: MENGGUNAKAN BASE_PATH] ===
        // Jika error terjadi, hapus file BARU yg mungkin terlanjur terupload
        if ($new_logo_uploaded && $target_path_physical && file_exists($target_path_physical)) {
             unlink($target_path_physical);
        }
        // === [AKHIR PERUBAHAN] ===

        if (strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'nama_perusahaan') !== false) {
             $error_message = "Error: Nama Perusahaan '" . htmlspecialchars($nama_perusahaan ?? '') . "' sudah ada.";
        } else {
             $error_message = "Error saat update data: " . $e->getMessage();
        }
    }

} // Akhir dari method POST

// 8. Logika GET (Mengambil data perusahaan untuk ditampilkan di form)
try {
    // Ambil SEMUA kolom
    $stmt_get = $conn_db->prepare("SELECT * FROM companies WHERE id = ?");
    if (!$stmt_get) throw new Exception("Gagal query: " . $conn_db->error);

    $stmt_get->bind_param("i", $company_id_to_edit);
    $stmt_get->execute();
    $result = $stmt_get->get_result();
    $company_data = $result->fetch_assoc();
    $stmt_get->close();

    if (!$company_data) {
        throw new Exception("Perusahaan dengan ID $company_id_to_edit tidak ditemukan.");
    }
    
    // Set judul halaman setelah data didapat
    $page_title = "Edit Perusahaan: " . htmlspecialchars($company_data['nama_perusahaan']);


} catch (Exception $e) {
    // Jika perusahaan tidak ditemukan, set error dan redirect
    $_SESSION['flash_message'] = "Error: " . $e->getMessage();
    // === [PERBAIKAN PATH: MENGGUNAKAN BASE_URL] ===
    header("Location: " . BASE_URL . "/modules/companies/manage_companies.php");
    // === [AKHIR PERUBAHAN] ===
    exit;
} finally {
     // Tutup koneksi jika masih terbuka
     if($conn_db->ping()) $conn_db->close();
}


// 9. Include template sidebar
// === [PERBAIKAN PATH: MENGGUNAKAN BASE_PATH] ===
require dirname(dirname(__DIR__)) . '/includes/sidebar.php'; // BASE_PATH.'/includes/sidebar.php'
// === [AKHIR PERUBAHAN] ===
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">

    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800"><?php echo $page_title; ?></h1>
        <a href="<?php echo BASE_URL; ?>/modules/companies/view_company.php?id=<?php echo $company_id_to_edit; ?>"
           class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg text-sm font-semibold transition duration-200 flex items-center no-underline">
           <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
           Kembali ke Detail Perusahaan
        </a>
        </div>

    <?php if (!empty($error_message)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
        </div>
    <?php endif; ?>

    <form action="<?php echo BASE_URL; ?>/modules/companies/edit_company.php?id=<?php echo $company_id_to_edit; ?>" method="POST" enctype="multipart/form-data" class="bg-white p-6 rounded-lg shadow-md space-y-6 border border-gray-200">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-start">
            <div class="md:col-span-2">
                <label for="nama_perusahaan" class="form-label">Nama Perusahaan <span class="text-red-500">*</span></label>
                <input type="text" id="nama_perusahaan" name="nama_perusahaan" class="form-input uppercase-input" required value="<?php echo htmlspecialchars($company_data['nama_perusahaan']); ?>">
            </div>

            <div>
                <label for="logo" class="form-label">Ganti Logo Perusahaan</label>
                 <?php
                     // === [PERBAIKAN PATH: MENGGUNAKAN BASE_PATH & BASE_URL] ===
                     $logo_db_path = $company_data['logo_path'];
                     $logo_physical_path = $logo_db_path ? BASE_PATH . "/" . $logo_db_path : null;
                     $logo_src_url = $logo_db_path ? BASE_URL . "/" . $logo_db_path : null;
                     // === [AKHIR PERUBAHAN] ===
                 ?>
                 <?php if ($logo_src_url && file_exists($logo_physical_path)): ?>
                     <img src="<?php echo htmlspecialchars($logo_src_url); ?>?t=<?php echo time(); ?>" alt="Logo Lama" class="h-16 w-auto mb-2 border rounded bg-gray-50 object-contain">
                     <p class="text-xs text-gray-500 mb-1">Logo saat ini.</p>
                 <?php else: ?>
                     <p class="text-xs text-gray-500 mb-1">Belum ada logo.</p>
                 <?php endif; ?>
                <input type="file" id="logo" name="logo" class="form-input file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" accept="image/png, image/jpeg, image/gif">
                <p class="text-xs text-gray-500 mt-1">Biarkan kosong jika tidak ingin ganti. Maks: 2MB.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label for="tanggal_pendirian" class="form-label">Tanggal Pendirian</label>
                <input type="date" id="tanggal_pendirian" name="tanggal_pendirian" class="form-input" value="<?php echo htmlspecialchars($company_data['tanggal_pendirian']); ?>">
            </div>
             <div>
                <label for="tempat_kedudukan" class="form-label">Tempat Kedudukan (Kota)</label>
                <input type="text" id="tempat_kedudukan" name="tempat_kedudukan" class="form-input" placeholder="Misal: Jakarta Selatan" value="<?php echo htmlspecialchars($company_data['tempat_kedudukan']); ?>">
            </div>
        </div>

        <div>
            <label for="alamat" class="form-label">Alamat Lengkap Perusahaan</label>
            <textarea id="alamat" name="alamat" rows="3" class="form-input"><?php echo htmlspecialchars($company_data['alamat']); ?></textarea>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
             <div>
                <label for="nib" class="form-label">Nomor Induk Berusaha (NIB)</label>
                <input type="text" id="nib" name="nib" class="form-input" maxlength="13" placeholder="13 Digit" value="<?php echo htmlspecialchars($company_data['nib']); ?>">
            </div>
             <div>
                <label for="npwp" class="form-label">NPWP Perusahaan</label>
                <input type="text" id="npwp" name="npwp" class="form-input" placeholder="XX.XXX.XXX.X-XXX.XXX" value="<?php echo htmlspecialchars($company_data['npwp']); ?>">
            </div>
        </div>

        <div class="pt-4 border-t border-gray-200">
            <h4 class="text-lg font-semibold text-gray-700 mb-4">Data Akta Pendirian</h4>
             <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                 <div>
                    <label for="akta_pendirian" class="form-label">Nomor Akta Pendirian</label>
                    <input type="text" id="akta_pendirian" name="akta_pendirian" class="form-input" value="<?php echo htmlspecialchars($company_data['akta_pendirian']); ?>">
                </div>
                <div>
                    <label for="tanggal_akta_pendirian" class="form-label">Tanggal Akta Pendirian</label>
                    <input type="date" id="tanggal_akta_pendirian" name="tanggal_akta_pendirian" class="form-input" value="<?php echo htmlspecialchars($company_data['tanggal_akta_pendirian']); ?>">
                </div>
                 <div>
                    <label for="sk_ahu_pendirian" class="form-label">Nomor SK AHU Pendirian</label>
                    <input type="text" id="sk_ahu_pendirian" name="sk_ahu_pendirian" class="form-input" value="<?php echo htmlspecialchars($company_data['sk_ahu_pendirian']); ?>">
                </div>
                 <div>
                    <label for="tanggal_sk_ahu_pendirian" class="form-label">Tanggal SK AHU Pendirian</label>
                    <input type="date" id="tanggal_sk_ahu_pendirian" name="tanggal_sk_ahu_pendirian" class="form-input" value="<?php echo htmlspecialchars($company_data['tanggal_sk_ahu_pendirian']); ?>">
                </div>
            </div>
             <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                 <div>
                    <label for="notaris_pendirian" class="form-label">Nama Notaris Akta Pendirian</label>
                    <input type="text" id="notaris_pendirian" name="notaris_pendirian" class="form-input" value="<?php echo htmlspecialchars($company_data['notaris_pendirian']); ?>">
                </div>
                 <div>
                    <label for="domisili_notaris_pendirian" class="form-label">Domisili Notaris Akta Pendirian</label>
                    <input type="text" id="domisili_notaris_pendirian" name="domisili_notaris_pendirian" class="form-input" value="<?php echo htmlspecialchars($company_data['domisili_notaris_pendirian']); ?>">
                </div>
            </div>
        </div>

        <div class="pt-4 border-t border-gray-200">
            <h4 class="text-lg font-semibold text-gray-700 mb-4">Data Permodalan</h4>
             <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                 <div>
                    <label for="modal_dasar" class="form-label">Modal Dasar (Rp)</label>
                    <input type="text" id="modal_dasar" name="modal_dasar" class="form-input input-rupiah" placeholder="Rp 0" value="<?php echo number_format($company_data['modal_dasar'], 0, ',', '.'); ?>">
                </div>
                 <div>
                    <label for="nilai_nominal_saham" class="form-label">Nilai Nominal per Saham (Rp)</label>
                    <input type="text" id="nilai_nominal_saham" name="nilai_nominal_saham" class="form-input input-rupiah" placeholder="Rp 0" value="<?php echo number_format($company_data['nilai_nominal_saham'], 0, ',', '.'); ?>">
                </div>
            </div>
        </div>

        <div class="mt-8 pt-6 border-t flex justify-end">
            <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition duration-200 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                Simpan Perubahan
            </button>
        </div>

    </form>

</main>
<script>
    function formatRupiah(angkaInput) {
        // Hapus karakter selain digit
        let angka = angkaInput.value.replace(/[^\d]/g, '').toString();
        // Format ribuan
        let rupiah = angka.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        // Hanya tambahkan 'Rp ' jika ada angka
        angkaInput.value = rupiah ? 'Rp ' + rupiah : '';
    }

    const inputsRupiah = document.querySelectorAll('.input-rupiah');
    inputsRupiah.forEach(input => {
        input.addEventListener('keyup', function(e) {
            formatRupiah(this);
        });
        // Panggil formatRupiah saat halaman dimuat
        formatRupiah(input);
    });
</script>


<?php
// === [PERBAIKAN PATH: MENGGUNAKAN BASE_PATH] ===
require dirname(dirname(__DIR__)) . '/includes/footer.php'; // BASE_PATH.'/includes/footer.php'
// === [AKHIR PERUBAHAN] ===
?>