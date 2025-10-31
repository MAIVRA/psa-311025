<?php
// modules/companies/add_company.php

// === [PERBAIKAN STRUKTUR & HEADERS] ===
// Pindahkan SEMUA logika PHP ke bagian atas, SEBELUM ada output HTML.

// Mulai session jika belum ada (diambil dari db.php)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Muat db.php SEKALI saja di awal untuk konstanta dan kredensial DB
require_once dirname(dirname(__DIR__)) . '/includes/db.php';

// Inisialisasi variabel global
$page_title = "Tambah Perusahaan Baru";
$page_active = "manage_companies";
$errors = []; // Mengganti $error_message menjadi array $errors
$logo_path_to_save = NULL;
$target_path_physical = NULL;
$deed_file_path_to_save = NULL;
$deed_target_path_physical = NULL;

// Cek otentikasi DULU (diambil dari header.php)
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}
// Ambil data user dari session (akan digunakan nanti di sidebar)
$user_id = $_SESSION['user_id'];
$nama_lengkap = $_SESSION['nama_lengkap'];
$tier = $_SESSION['tier'];

// Proteksi Halaman Khusus (Hanya Admin)
if ($tier != 'Admin') {
    $_SESSION['flash_message'] = "Anda tidak memiliki hak akses untuk halaman ini!";
    // [Perbaikan Flash Message] Tambahkan session_write_close() sebelum redirect error
    session_write_close();
    header("Location: " . BASE_URL . "/dashboard.php");
    exit;
}


// === [LOGIKA POST PINDAH KE SINI] ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Ambil data Perusahaan
    $nama_perusahaan = strtoupper(trim($_POST['nama_perusahaan']));
    $tanggal_pendirian = $_POST['tanggal_pendirian'];
    $tempat_kedudukan = trim($_POST['tempat_kedudukan']);
    $alamat = trim($_POST['alamat']);
    $nib = trim($_POST['nib']);
    $npwp_perusahaan = trim($_POST['npwp_perusahaan']);
    // Hilangkan 'Rp ' dan '.' dari input rupiah
    $modal_dasar = (int)preg_replace('/[Rp. ]/', '', $_POST['modal_dasar']);
    $modal_disetor = (int)preg_replace('/[Rp. ]/', '', $_POST['modal_disetor']);
    $nilai_nominal_saham = (int)preg_replace('/[Rp. ]/', '', $_POST['nilai_nominal_saham']);

    // Ambil data Akta Pendirian
    $nomor_akta_pendirian = trim($_POST['nomor_akta_pendirian']);
    $tanggal_akta_pendirian = $_POST['tanggal_akta_pendirian'];
    $nama_notaris_pendirian = trim($_POST['nama_notaris_pendirian']);
    $domisili_notaris_pendirian = trim($_POST['domisili_notaris_pendirian']);
    $nomor_sk_ahu_pendirian = trim($_POST['nomor_sk_ahu_pendirian']);
    $tanggal_sk_ahu_pendirian = !empty($_POST['tanggal_sk_ahu_pendirian']) ? trim($_POST['tanggal_sk_ahu_pendirian']) : NULL;
    $isi_akta_summary_pendirian = trim($_POST['isi_akta_summary_pendirian']);

    // Validasi Dasar
    if (empty($nama_perusahaan) || empty($tanggal_pendirian) || empty($nomor_akta_pendirian) || empty($tanggal_akta_pendirian) || empty($nama_notaris_pendirian)) {
        $errors[] = "Nama Perusahaan, Tanggal Pendirian, Nomor Akta Pendirian, Tanggal Akta Pendirian, dan Nama Notaris Pendirian wajib diisi.";
    }

    // --- LOGIKA UPLOAD LOGO ---
    if (isset($_FILES['logo_perusahaan']) && $_FILES['logo_perusahaan']['error'] == UPLOAD_ERR_OK) {
        $logo_info = $_FILES['logo_perusahaan'];
        $logo_name = $logo_info['name'];
        $logo_tmp_name = $logo_info['tmp_name'];
        $logo_size = $logo_info['size'];
        $logo_error = $logo_info['error'];

        $logo_ext = strtolower(pathinfo($logo_name, PATHINFO_EXTENSION));
        $allowed_logo_ext = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($logo_ext, $allowed_logo_ext)) {
            if ($logo_size <= 5 * 1024 * 1024) { // Max 5MB
                 // Format: uploads/logos/logo_UNIQUEID.ext
                 $new_logo_name = 'logo_' . uniqid('', true) . '.' . $logo_ext;
                 $target_dir_relative = 'uploads/logos/';
                 $logo_path_to_save = $target_dir_relative . $new_logo_name; // Path relatif untuk DB
                 $target_path_physical = BASE_PATH . '/' . $logo_path_to_save; // Path fisik untuk move_uploaded_file

                 // Buat direktori jika belum ada
                 if (!is_dir(dirname($target_path_physical))) {
                     mkdir(dirname($target_path_physical), 0777, true);
                 }

                 // Pindahkan file logo (akan dilakukan nanti setelah company_id didapat jika sukses)
            } else {
                 $errors[] = "Ukuran file logo terlalu besar (maks 5MB).";
            }
        } else {
             $errors[] = "Format file logo tidak diizinkan (hanya JPG, JPEG, PNG, GIF).";
        }
    } elseif (isset($_FILES['logo_perusahaan']) && $_FILES['logo_perusahaan']['error'] != UPLOAD_ERR_NO_FILE) {
         $errors[] = "Terjadi error saat upload logo: Error Code " . $_FILES['logo_perusahaan']['error'];
    }

    // --- LOGIKA UPLOAD FILE AKTA PENDIRIAN ---
    if (isset($_FILES['file_akta_pendirian']) && $_FILES['file_akta_pendirian']['error'] == UPLOAD_ERR_OK) {
        $deed_file_info = $_FILES['file_akta_pendirian'];
        $deed_file_ext = strtolower(pathinfo($deed_file_info['name'], PATHINFO_EXTENSION));

        if ($deed_file_ext == 'pdf') {
             if ($deed_file_info['size'] <= 10 * 1024 * 1024) { // Max 10MB untuk PDF
                 // Path file akta akan ditentukan SETELAH company ID didapat
                 // Format: uploads/companies/COMPANY_ID/deeds/deed_pendirian_TIME.pdf
             } else {
                 $errors[] = "Ukuran file akta pendirian terlalu besar (maks 10MB).";
             }
        } else {
             $errors[] = "Format file akta pendirian harus PDF.";
        }
     } elseif (isset($_FILES['file_akta_pendirian']) && $_FILES['file_akta_pendirian']['error'] != UPLOAD_ERR_NO_FILE) {
         $errors[] = "Terjadi error saat upload file akta pendirian: Error Code " . $_FILES['file_akta_pendirian']['error'];
     }


    // --- INSERT KE DATABASE JIKA TIDAK ADA ERROR ---
    if (empty($errors)) {
        $conn->begin_transaction(); // Mulai transaksi

        try {
            // 1. Insert ke tabel 'companies'
            $stmt_company = $conn->prepare(
                "INSERT INTO companies (
                    nama_perusahaan, logo_path, tanggal_pendirian,
                    akta_pendirian, tanggal_akta_pendirian, sk_ahu_pendirian, tanggal_sk_ahu_pendirian,
                    notaris_pendirian, domisili_notaris_pendirian,
                    modal_dasar, modal_disetor, nilai_nominal_saham,
                    tempat_kedudukan, alamat, nib, npwp
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            // Tipe: s s s s s s s s s i i i s s s s
            $stmt_company->bind_param("sssssssssiisssss",
                $nama_perusahaan, $logo_path_to_save, $tanggal_pendirian,
                $nomor_akta_pendirian, $tanggal_akta_pendirian, $nomor_sk_ahu_pendirian, $tanggal_sk_ahu_pendirian,
                $nama_notaris_pendirian, $domisili_notaris_pendirian,
                $modal_dasar, $modal_disetor, $nilai_nominal_saham,
                $tempat_kedudukan, $alamat, $nib, $npwp_perusahaan
            );

            if (!$stmt_company->execute()) {
                 throw new Exception("Gagal menyimpan data perusahaan: " . $stmt_company->error);
            }
            $new_company_id = $stmt_company->insert_id;
            $stmt_company->close();

            // Pindahkan file logo SEKARANG setelah company_id didapat
            if ($logo_path_to_save && $target_path_physical) {
                if (!move_uploaded_file($_FILES['logo_perusahaan']['tmp_name'], $target_path_physical)) {
                    throw new Exception("Gagal memindahkan file logo yang diupload.");
                }
            }

            // Tentukan path file akta SEKARANG setelah company ID didapat
            if (isset($_FILES['file_akta_pendirian']) && $_FILES['file_akta_pendirian']['error'] == UPLOAD_ERR_OK) {
                $deed_file_info = $_FILES['file_akta_pendirian'];
                $deed_file_ext = strtolower(pathinfo($deed_file_info['name'], PATHINFO_EXTENSION));
                $deed_base_name = "deed_pendirian_" . time(); // Nama file unik
                $deed_target_dir_relative = "uploads/companies/{$new_company_id}/deeds/";
                $deed_file_path_to_save = $deed_target_dir_relative . $deed_base_name . '.' . $deed_file_ext; // Path relatif DB
                $deed_target_path_physical = BASE_PATH . '/' . $deed_file_path_to_save; // Path fisik upload

                 // Buat direktori jika belum ada
                 if (!is_dir(dirname($deed_target_path_physical))) {
                     mkdir(dirname($deed_target_path_physical), 0777, true);
                 }

                 // Pindahkan file akta
                 if (!move_uploaded_file($deed_file_info['tmp_name'], $deed_target_path_physical)) {
                     throw new Exception("Gagal memindahkan file akta pendirian yang diupload.");
                 }
            }


            // 2. Insert ke tabel 'deeds' sebagai Akta Pendirian
            // Hitung ID manual untuk deeds (jika AUTO_INCREMENT masih bermasalah)
            $next_deed_id = 1;
            $result_max_id_deed = $conn->query("SELECT MAX(id) as max_id FROM deeds");
            if ($result_max_id_deed && $row_max_deed = $result_max_id_deed->fetch_assoc()) {
                if ($row_max_deed['max_id'] !== null) {
                    $next_deed_id = $row_max_deed['max_id'] + 1;
                }
            }

            $stmt_deed = $conn->prepare(
                "INSERT INTO deeds (
                    id, company_id, nomor_akta, tanggal_akta, nama_notaris, domisili_notaris,
                    nomor_sk_ahu, tanggal_sk_ahu, isi_akta_summary, tipe_akta, file_path
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendirian', ?)" // Tipe Akta di-hardcode 'Pendirian'
            );
             // Tipe: i i s s s s s s s s
            $stmt_deed->bind_param("iissssssss",
                $next_deed_id, // ID manual
                $new_company_id, $nomor_akta_pendirian, $tanggal_akta_pendirian, $nama_notaris_pendirian, $domisili_notaris_pendirian,
                $nomor_sk_ahu_pendirian, $tanggal_sk_ahu_pendirian, $isi_akta_summary_pendirian, $deed_file_path_to_save
            );

            if (!$stmt_deed->execute()) {
                 throw new Exception("Gagal menyimpan data akta pendirian: " . $stmt_deed->error);
            }
            $new_deed_id = $next_deed_id; // Gunakan ID manual
            $stmt_deed->close();

            // 3. Update 'id_akta_terakhir' di tabel 'companies'
            $stmt_update_comp = $conn->prepare("UPDATE companies SET id_akta_terakhir = ? WHERE id = ?");
            $stmt_update_comp->bind_param("ii", $new_deed_id, $new_company_id);
            if (!$stmt_update_comp->execute()) {
                 throw new Exception("Gagal mengupdate akta terakhir perusahaan: " . $stmt_update_comp->error);
            }
            $stmt_update_comp->close();

            // Jika semua berhasil, commit transaksi
            $conn->commit();
            $_SESSION['flash_message'] = "Perusahaan baru dan akta pendirian berhasil ditambahkan!";

             // ===============================================
             // [PERBAIKAN UTAMA] Tulis dan tutup session SEBELUM redirect
             // ===============================================
             session_write_close();
             // ===============================================

            header("Location: manage_companies.php"); // Redirect ke daftar perusahaan
            exit;

        } catch (Exception $e) {
            $conn->rollback(); // Batalkan semua query jika ada error
            $errors[] = $e->getMessage(); // Tampilkan pesan error spesifik

            // Hapus file yang mungkin sudah terupload jika terjadi error DB
            if ($target_path_physical && file_exists($target_path_physical)) {
                unlink($target_path_physical);
            }
             if ($deed_target_path_physical && file_exists($deed_target_path_physical)) {
                unlink($deed_target_path_physical);
            }
            // session_write_close(); // Tutup session jika ada error juga? Opsional
        }
    } else {
        // Jika ada error validasi di awal, hapus file logo/akta jika terlanjur terupload sementara
        // (Ini jarang terjadi karena validasi sebelum move_uploaded_file, tapi untuk jaga-jaga)
        if ($target_path_physical && file_exists($target_path_physical)) {
             unlink($target_path_physical);
        }
         if ($deed_target_path_physical && file_exists($deed_target_path_physical)) {
            unlink($deed_target_path_physical);
        }
        // session_write_close(); // Tutup session jika ada error juga? Opsional
    }

} // --- Akhir dari 'if ($_SERVER['REQUEST_METHOD'] === 'POST')' ---


// === Panggil Header HTML (Setelah semua logika PHP selesai) ===
require '../../includes/header.php';
require '../../includes/sidebar.php';
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Tambah Perusahaan Baru</h1>

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

    <form action="add_company.php" method="POST" enctype="multipart/form-data" class="space-y-8">

        <div class="card">
            <div class="card-header"><h3 class="text-lg font-semibold">1. Data Utama Perusahaan</h3></div>
            <div class="card-content grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="nama_perusahaan" class="form-label">Nama Perusahaan</label>
                    <input type="text" id="nama_perusahaan" name="nama_perusahaan" class="form-input uppercase-input" value="<?php echo htmlspecialchars($_POST['nama_perusahaan'] ?? ''); ?>" required>
                </div>
                 <div>
                    <label for="logo_perusahaan" class="form-label">Logo Perusahaan</label>
                    <input type="file" id="logo_perusahaan" name="logo_perusahaan" class="form-input" accept="image/*">
                    <p class="text-xs text-gray-500 mt-1">Opsional. Maks 5MB (JPG, PNG, GIF).</p>
                </div>
                <div>
                    <label for="tanggal_pendirian" class="form-label">Tanggal Pendirian</label>
                    <input type="date" id="tanggal_pendirian" name="tanggal_pendirian" class="form-input" value="<?php echo htmlspecialchars($_POST['tanggal_pendirian'] ?? ''); ?>" required>
                </div>
                <div>
                    <label for="tempat_kedudukan" class="form-label">Tempat Kedudukan (Kota/Kab)</label>
                    <input type="text" id="tempat_kedudukan" name="tempat_kedudukan" class="form-input" value="<?php echo htmlspecialchars($_POST['tempat_kedudukan'] ?? ''); ?>">
                </div>
                <div class="md:col-span-2">
                    <label for="alamat" class="form-label">Alamat Lengkap Sesuai Domisili</label>
                    <textarea id="alamat" name="alamat" rows="3" class="form-input"><?php echo htmlspecialchars($_POST['alamat'] ?? ''); ?></textarea>
                </div>
                 <div>
                    <label for="nib" class="form-label">Nomor Induk Berusaha (NIB)</label>
                    <input type="text" id="nib" name="nib" class="form-input" value="<?php echo htmlspecialchars($_POST['nib'] ?? ''); ?>" maxlength="13">
                </div>
                 <div>
                    <label for="npwp_perusahaan" class="form-label">NPWP Perusahaan</label>
                    <input type="text" id="npwp_perusahaan" name="npwp_perusahaan" class="form-input" value="<?php echo htmlspecialchars($_POST['npwp_perusahaan'] ?? ''); ?>">
                </div>
                 <div>
                    <label for="modal_dasar" class="form-label">Modal Dasar</label>
                    <input type="text" id="modal_dasar" name="modal_dasar" class="form-input" value="<?php echo htmlspecialchars($_POST['modal_dasar'] ?? ''); ?>" onkeyup="formatRupiah(this)">
                </div>
                 <div>
                    <label for="modal_disetor" class="form-label">Modal Ditempatkan & Disetor</label>
                    <input type="text" id="modal_disetor" name="modal_disetor" class="form-input" value="<?php echo htmlspecialchars($_POST['modal_disetor'] ?? ''); ?>" onkeyup="formatRupiah(this)">
                </div>
                 <div>
                    <label for="nilai_nominal_saham" class="form-label">Nilai Nominal Saham</label>
                    <input type="text" id="nilai_nominal_saham" name="nilai_nominal_saham" class="form-input" value="<?php echo htmlspecialchars($_POST['nilai_nominal_saham'] ?? ''); ?>" onkeyup="formatRupiah(this)">
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="text-lg font-semibold">2. Akta Pendirian</h3></div>
            <div class="card-content space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="nomor_akta_pendirian" class="form-label">Nomor Akta Pendirian</label>
                        <input type="text" id="nomor_akta_pendirian" name="nomor_akta_pendirian" class="form-input" value="<?php echo htmlspecialchars($_POST['nomor_akta_pendirian'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label for="tanggal_akta_pendirian" class="form-label">Tanggal Akta Pendirian</label>
                        <input type="date" id="tanggal_akta_pendirian" name="tanggal_akta_pendirian" class="form-input" value="<?php echo htmlspecialchars($_POST['tanggal_akta_pendirian'] ?? ''); ?>" required>
                    </div>
                </div>
                <div>
                    <label for="nama_notaris_pendirian" class="form-label">Nama Notaris Pendirian</label>
                    <input type="text" id="nama_notaris_pendirian" name="nama_notaris_pendirian" class="form-input uppercase-input" value="<?php echo htmlspecialchars($_POST['nama_notaris_pendirian'] ?? ''); ?>" required>
                </div>
                 <div>
                    <label for="domisili_notaris_pendirian" class="form-label">Domisili Notaris Pendirian</label>
                    <input type="text" id="domisili_notaris_pendirian" name="domisili_notaris_pendirian" class="form-input" value="<?php echo htmlspecialchars($_POST['domisili_notaris_pendirian'] ?? ''); ?>">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="nomor_sk_ahu_pendirian" class="form-label">Nomor SK AHU Pendirian</label>
                        <input type="text" id="nomor_sk_ahu_pendirian" name="nomor_sk_ahu_pendirian" class="form-input" value="<?php echo htmlspecialchars($_POST['nomor_sk_ahu_pendirian'] ?? ''); ?>">
                    </div>
                    <div>
                        <label for="tanggal_sk_ahu_pendirian" class="form-label">Tanggal SK AHU Pendirian</label>
                        <input type="date" id="tanggal_sk_ahu_pendirian" name="tanggal_sk_ahu_pendirian" class="form-input" value="<?php echo htmlspecialchars($_POST['tanggal_sk_ahu_pendirian'] ?? ''); ?>">
                    </div>
                </div>
                 <div>
                    <label for="isi_akta_summary_pendirian" class="form-label">Ringkasan Isi Akta Pendirian</label>
                    <textarea id="isi_akta_summary_pendirian" name="isi_akta_summary_pendirian" rows="4" class="form-input"><?php echo htmlspecialchars($_POST['isi_akta_summary_pendirian'] ?? ''); ?></textarea>
                </div>
                 <div>
                    <label for="file_akta_pendirian" class="form-label">Upload Scan Akta Pendirian (PDF)</label>
                    <input type="file" id="file_akta_pendirian" name="file_akta_pendirian" class="form-input" accept=".pdf">
                    <p class="text-xs text-gray-500 mt-1">Opsional. Maks 10MB (Hanya PDF).</p>
                </div>
            </div>
        </div>

        <div class="flex justify-end space-x-4">
            <a href="manage_companies.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-3 rounded-lg font-semibold transition duration-200 no-underline">
                Batal
            </a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition duration-200 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                Simpan Perusahaan Baru
            </button>
        </div>

    </form>

</main>
<script>
    // Fungsi format Rupiah (Tetap sama)
    function formatRupiah(angkaInput) {
        if (!angkaInput) return;
        let angka = angkaInput.value.replace(/[^\d]/g, '').toString(); // Hanya angka
        // Hapus leading zeros
        angka = angka.replace(/^0+/, '');
        if (!angka) {
            angkaInput.value = '';
            return;
        }
        let number_string = angka.replace(/[^,\d]/g, '').toString(),
            split = number_string.split(','),
            sisa = split[0].length % 3,
            rupiah = split[0].substr(0, sisa),
            ribuan = split[0].substr(sisa).match(/\d{3}/gi);

        if (ribuan) {
            separator = sisa ? '.' : '';
            rupiah += separator + ribuan.join('.');
        }

        rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
        angkaInput.value = 'Rp ' + rupiah;
    }

    // Terapkan ke input relevan saat load dan saat input
    const inputsRupiah = ['modal_dasar', 'modal_disetor', 'nilai_nominal_saham'];
    inputsRupiah.forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            // Format saat load jika ada value
             if(input.value) formatRupiah(input);
            // Format saat user mengetik
            input.addEventListener('keyup', function(e){
                formatRupiah(this);
            });
             // Format saat user paste
            input.addEventListener('paste', function(e){
                 // Beri sedikit jeda agar value sempat ter-paste
                 setTimeout(() => formatRupiah(this), 10);
            });
             // Format saat user mengubah via panah/dll
            input.addEventListener('change', function(e){
                 formatRupiah(this);
            });
        }
    });

</script>
<?php
require '../../includes/footer.php';
?>