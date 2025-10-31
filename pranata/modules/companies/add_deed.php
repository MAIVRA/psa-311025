<?php
// modules/companies/add_deed.php

// 1. Set variabel halaman
$page_title = "Tambah Akta Baru";
$page_active = "manage_companies";

// 2. Mulai session dan panggil DB
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require '../../includes/db.php';

// 3. Cek status login dan tier (Admin)
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}
if ($_SESSION['tier'] != 'Admin') {
    $_SESSION['flash_message'] = "Anda tidak memiliki hak akses untuk halaman ini!";
    // [Perbaikan Kecil] Tambahkan session_write_close() sebelum redirect error juga
    session_write_close();
    header("Location: " . BASE_URL . "/dashboard.php");
    exit;
}
$tier = $_SESSION['tier']; // Ambil tier untuk digunakan nanti jika perlu

$company_id = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;
$errors = [];

// Helper function untuk file upload (Tetap sama)
function handleFileUpload($file, $uploadDir, $baseFileName) {
    if (isset($file) && $file['error'] == UPLOAD_ERR_OK) {
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($fileExtension != 'pdf') {
             return ['error' => 'Tipe file harus PDF.'];
        }
        $newFileName = $baseFileName . "_" . time() . "." . $fileExtension;
        $targetPath = $uploadDir . $newFileName;
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return ['path' => str_replace(BASE_PATH . '/', '', $targetPath)];
        } else {
            return ['error' => 'Gagal memindahkan file akta.'];
        }
    }
    if (isset($file) && $file['error'] != UPLOAD_ERR_OK && $file['error'] != UPLOAD_ERR_NO_FILE) {
        return ['error' => 'Terjadi error saat upload file akta: Error Code ' . $file['error']];
    }
    return ['path' => null];
}


// 4. Logika POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $company_id = (int)$_POST['company_id'];
    $nomor_akta = trim($_POST['nomor_akta']);
    $tanggal_akta = trim($_POST['tanggal_akta']);
    $nama_notaris = trim($_POST['nama_notaris']);
    $domisili_notaris = trim($_POST['domisili_notaris']);
    $nomor_sk_ahu = trim($_POST['nomor_sk_ahu']);
    $tanggal_sk_ahu = !empty($_POST['tanggal_sk_ahu']) ? trim($_POST['tanggal_sk_ahu']) : NULL;
    $isi_akta_summary = trim($_POST['isi_akta_summary']);
    $tipe_akta = $_POST['tipe_akta'];

    // Validasi dasar
    if (empty($company_id) || empty($nomor_akta) || empty($tanggal_akta) || empty($nama_notaris)) {
        $errors[] = "ID Perusahaan, Nomor Akta, Tanggal Akta, dan Nama Notaris wajib diisi.";
    }
     if (empty($tipe_akta)) {
        $errors[] = "Tipe Akta wajib dipilih.";
    }

    $file_path_sql = null;

    // Handle File Upload
    $uploadDir = BASE_PATH . "/uploads/companies/{$company_id}/deeds/";
    $uploadResult = handleFileUpload($_FILES['file_akta'], $uploadDir, "deed_{$company_id}");

    if (isset($uploadResult['error'])) {
        $errors[] = "File Akta: " . $uploadResult['error'];
    } else {
        $file_path_sql = $uploadResult['path'];
    }

    // Jika tidak ada error, insert ke DB
    if (empty($errors)) {

        // Hitung ID Berikutnya Secara Manual (jika AUTO_INCREMENT masih bermasalah)
        $next_id = 1;
        $result_max_id = $conn->query("SELECT MAX(id) as max_id FROM deeds");
        if ($result_max_id && $row_max = $result_max_id->fetch_assoc()) {
            if ($row_max['max_id'] !== null) {
                $next_id = $row_max['max_id'] + 1;
            }
        }

        // Tambahkan `id` ke statement INSERT
        $sql = "INSERT INTO deeds (
                    id, company_id, nomor_akta, tanggal_akta, nama_notaris, domisili_notaris,
                    nomor_sk_ahu, tanggal_sk_ahu, isi_akta_summary, tipe_akta, file_path
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"; // 11 placeholders

        $stmt = $conn->prepare($sql);
        // Tambahkan tipe 'i' di awal dan bind $next_id
        $stmt->bind_param("iisssssssss",
            $next_id, // Bind ID yang sudah dihitung
            $company_id, $nomor_akta, $tanggal_akta, $nama_notaris, $domisili_notaris,
            $nomor_sk_ahu, $tanggal_sk_ahu, $isi_akta_summary, $tipe_akta, $file_path_sql
        );

        if ($stmt->execute()) {
            $new_deed_id = $next_id;

            // Jika ini akta Pendirian, update data di tabel companies
            if ($tipe_akta == 'Pendirian') {
                $stmt_update_comp = $conn->prepare("UPDATE companies SET
                                                    akta_pendirian = ?, tanggal_akta_pendirian = ?,
                                                    sk_ahu_pendirian = ?, tanggal_sk_ahu_pendirian = ?,
                                                    notaris_pendirian = ?, domisili_notaris_pendirian = ?
                                                WHERE id = ?");
                 $stmt_update_comp->bind_param("ssssssi",
                    $nomor_akta, $tanggal_akta, $nomor_sk_ahu, $tanggal_sk_ahu, $nama_notaris, $domisili_notaris, $company_id
                 );
                 $stmt_update_comp->execute();
                 $stmt_update_comp->close();
            }

            // Selalu update id_akta_terakhir di tabel companies
            $stmt_update_latest = $conn->prepare("UPDATE companies SET id_akta_terakhir = ? WHERE id = ?");
            $stmt_update_latest->bind_param("ii", $new_deed_id, $company_id);
            $stmt_update_latest->execute();
            $stmt_update_latest->close();

            $_SESSION['flash_message'] = "Akta baru berhasil ditambahkan!";

            // ===============================================
            // [PERBAIKAN UTAMA] Tulis dan tutup session SEBELUM redirect
            // ===============================================
            session_write_close();
            // ===============================================

            header("Location: view_company.php?id=" . $company_id . "#deeds");
            exit; // Pastikan exit tetap ada setelah header
        } else {
            // Tangkap error spesifik
             $errors[] = "Gagal menyimpan ke database: (" . $stmt->errno . ") " . $stmt->error;
             // Jika error, sebaiknya session juga ditutup jika ada operasi session setelah ini
             // session_write_close();
        }
        $stmt->close();
    } else {
         // Jika ada $errors validasi di awal, session mungkin perlu ditutup di sini
         // session_write_close();
    }
} // Akhir dari if ($_SERVER["REQUEST_METHOD"] == "POST")

// Cek company_id untuk GET request
if ($company_id == 0 && $_SERVER["REQUEST_METHOD"] != "POST") {
    $_SESSION['flash_message'] = "ID Perusahaan tidak valid!";
     // [Perbaikan Kecil] Tambahkan session_write_close() sebelum redirect error juga
    session_write_close();
    header("Location: manage_companies.php");
    exit;
}

// 5. Panggil header.php
require '../../includes/header.php';

// Ambil nama perusahaan untuk judul
$company_name = '';
if ($company_id > 0) {
    $stmt_comp = $conn->prepare("SELECT nama_perusahaan FROM companies WHERE id = ?");
    $stmt_comp->bind_param("i", $company_id);
    $stmt_comp->execute();
    $result_comp = $stmt_comp->get_result();
    if ($result_comp->num_rows > 0) {
        $company_name = $result_comp->fetch_assoc()['nama_perusahaan'];
    }
    $stmt_comp->close();
}


require '../../includes/sidebar.php';
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    <div class="container mx-auto max-w-2xl">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">
            Tambah Akta Baru <?php echo $company_name ? 'untuk ' . htmlspecialchars($company_name) : ''; ?>
        </h1>

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

        <div class="card">
            <form action="add_deed.php?company_id=<?php echo $company_id; ?>" method="POST" enctype="multipart/form-data" class="card-content space-y-5">

                <input type="hidden" name="company_id" value="<?php echo htmlspecialchars($company_id); ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="nomor_akta" class="form-label">Nomor Akta</label>
                        <input type="text" id="nomor_akta" name="nomor_akta" class="form-input"
                               value="<?php echo htmlspecialchars($_POST['nomor_akta'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label for="tanggal_akta" class="form-label">Tanggal Akta</label>
                        <input type="date" id="tanggal_akta" name="tanggal_akta" class="form-input"
                               value="<?php echo htmlspecialchars($_POST['tanggal_akta'] ?? ''); ?>" required>
                    </div>
                </div>

                <div>
                    <label for="nama_notaris" class="form-label">Nama Notaris</label>
                    <input type="text" id="nama_notaris" name="nama_notaris" class="form-input uppercase-input"
                           value="<?php echo htmlspecialchars($_POST['nama_notaris'] ?? ''); ?>" required>
                </div>
                <div>
                     <label for="domisili_notaris" class="form-label">Domisili Notaris</label>
                    <input type="text" id="domisili_notaris" name="domisili_notaris" class="form-input"
                           value="<?php echo htmlspecialchars($_POST['domisili_notaris'] ?? ''); ?>">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="nomor_sk_ahu" class="form-label">Nomor SK AHU</label>
                        <input type="text" id="nomor_sk_ahu" name="nomor_sk_ahu" class="form-input"
                               value="<?php echo htmlspecialchars($_POST['nomor_sk_ahu'] ?? ''); ?>">
                    </div>
                    <div>
                        <label for="tanggal_sk_ahu" class="form-label">Tanggal SK AHU</label>
                        <input type="date" id="tanggal_sk_ahu" name="tanggal_sk_ahu" class="form-input"
                               value="<?php echo htmlspecialchars($_POST['tanggal_sk_ahu'] ?? ''); ?>">
                    </div>
                </div>

                 <div>
                    <label for="tipe_akta" class="form-label">Tipe Akta</label>
                    <select id="tipe_akta" name="tipe_akta" class="form-input" required>
                        <option value="">-- Pilih Tipe --</option>
                        <option value="Pendirian" <?php echo (isset($_POST['tipe_akta']) && $_POST['tipe_akta'] == 'Pendirian') ? 'selected' : ''; ?>>Pendirian</option>
                        <option value="Perubahan" <?php echo (isset($_POST['tipe_akta']) && $_POST['tipe_akta'] == 'Perubahan') ? 'selected' : ''; ?>>Perubahan</option>
                    </select>
                </div>

                <div>
                    <label for="isi_akta_summary" class="form-label">Ringkasan Isi Akta</label>
                    <textarea id="isi_akta_summary" name="isi_akta_summary" rows="4" class="form-input"><?php echo htmlspecialchars($_POST['isi_akta_summary'] ?? ''); ?></textarea>
                </div>

                <div>
                    <label for="file_akta" class="form-label">Upload Scan Akta (PDF)</label>
                    <input type="file" id="file_akta" name="file_akta" class="form-input" accept=".pdf">
                    <p class="text-xs text-gray-500 mt-1">Hanya file PDF yang diizinkan.</p>
                </div>

                <div class="flex justify-end space-x-3 pt-4 border-t">
                    <a href="view_company.php?id=<?php echo $company_id; ?>#deeds"
                       class="btn-primary-sm btn-secondary no-underline">
                        Batal
                    </a>
                    <button type="submit" class="btn-primary-sm">
                        Simpan Akta
                    </button>
                </div>

            </form>
        </div>
    </div>
</main>

<?php
require '../../includes/footer.php';
?>