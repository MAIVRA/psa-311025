<?php
// modules/companies/edit_deed.php

// 1. Set variabel khusus untuk halaman ini
$page_title = "Edit Akta Perusahaan";
$page_active = "manage_companies";

// 4. Ambil ID dari URL
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    // Perlu db.php untuk BASE_URL
    require_once dirname(dirname(__DIR__)) . '/includes/db.php';
    header("Location: " . BASE_URL . "/modules/companies/manage_companies.php");
    exit;
}
$deed_id = (int)$_GET['id'];
$company_id = (int)$_GET['company_id']; // Ambil company_id juga

// 5. Inisialisasi variabel
$error_message = '';
$deed_details = null;
$company_details = null;
$is_currently_akta_terakhir = false;

// 7. Logika saat form di-submit (method POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Muat db.php untuk kredensial dan BASE_PATH
    require_once dirname(dirname(__DIR__)) . '/includes/db.php';
    
    // Ambil ID dari form
    $posted_deed_id = (int)$_POST['deed_id'];
    $posted_company_id = (int)$_POST['company_id'];
    $old_file_path = !empty($_POST['old_file_path']) ? $_POST['old_file_path'] : NULL;
    $file_path_to_save = $old_file_path; // Default: pertahankan file lama
    $target_path_physical = NULL;

    // Validasi ID
    if ($posted_deed_id !== $deed_id || $posted_company_id !== $company_id) {
        $error_message = "Error: ID Akta atau ID Perusahaan tidak cocok.";
    } else {
    
        // Buka koneksi baru untuk memproses POST
        $conn_post = new mysqli($servername, $username, $password, $dbname);
        if ($conn_post->connect_error) {
            $error_message = "Koneksi Gagal: " . $conn_post->connect_error;
        } else {
            $conn_post->set_charset("utf8mb4");
            
            try {
                // === Logika Upload File (Jika ada file BARU) ===
                if (isset($_FILES['scan_akta']) && $_FILES['scan_akta']['error'] == UPLOAD_ERR_OK) {
                    $file_info = $_FILES['scan_akta'];
                    $file_tmp_name = $file_info['tmp_name'];
                    $file_size = $file_info['size'];
                    
                    $max_size = 10 * 1024 * 1024; // 10 MB
                    if ($file_size > $max_size) throw new Exception("Ukuran file scan baru terlalu besar. Maksimal 10MB.");

                    $allowed_types = ['application/pdf'];
                    $file_type = mime_content_type($file_tmp_name);
                    if (!in_array($file_type, $allowed_types)) throw new Exception("Tipe file baru tidak valid. Hanya file PDF.");

                    $file_extension = pathinfo($file_info['name'], PATHINFO_EXTENSION);
                    $unique_name = "deed_" . $company_id . "_" . uniqid() . '.' . strtolower($file_extension);
                    
                    $file_path_to_save = "uploads/deeds/" . $unique_name; // Path baru
                    $target_dir = BASE_PATH . "/uploads/deeds/";
                    
                    if (!is_dir($target_dir)) {
                        mkdir($target_dir, 0755, true);
                    }
                    
                    $target_path_physical = $target_dir . $unique_name;

                    if (!move_uploaded_file($file_tmp_name, $target_path_physical)) {
                        throw new Exception("Gagal memindahkan file scan baru yang di-upload.");
                    }
                    
                    // Jika upload file baru BERHASIL, hapus file lama (jika ada)
                    if ($old_file_path && file_exists(BASE_PATH . '/' . $old_file_path)) {
                        unlink(BASE_PATH . '/' . $old_file_path);
                    }
                    
                } elseif (isset($_FILES['scan_akta']) && $_FILES['scan_akta']['error'] != UPLOAD_ERR_NO_FILE) {
                    throw new Exception("Error saat upload file baru, code: " . $_FILES['scan_akta']['error']);
                }
                // === Akhir Logika Upload File ===

                // Ambil data lain dari form
                $nomor_akta = trim($_POST['nomor_akta']);
                $tanggal_akta = $_POST['tanggal_akta'];
                $nama_notaris = trim($_POST['nama_notaris']);
                $domisili_notaris = !empty($_POST['domisili_notaris']) ? trim($_POST['domisili_notaris']) : NULL;
                $nomor_sk_ahu = !empty($_POST['nomor_sk_ahu']) ? trim($_POST['nomor_sk_ahu']) : NULL;
                $tanggal_sk_ahu = !empty($_POST['tanggal_sk_ahu']) ? $_POST['tanggal_sk_ahu'] : NULL;
                $isi_akta_summary = !empty($_POST['isi_akta_summary']) ? trim($_POST['isi_akta_summary']) : NULL;
                $is_akta_terakhir = isset($_POST['is_akta_terakhir']);
                $tipe_akta = $_POST['tipe_akta']; // Ambil tipe akta baru

                if (empty($nomor_akta) || empty($tanggal_akta) || empty($nama_notaris) || empty($tipe_akta)) {
                    throw new Exception("Nomor Akta, Tanggal Akta, Nama Notaris, dan Tipe Akta wajib diisi.");
                }
                
                $conn_post->begin_transaction();

                // === Query UPDATE ===
                $sql = "UPDATE deeds SET 
                            nomor_akta = ?, 
                            tanggal_akta = ?, 
                            nama_notaris = ?, 
                            domisili_notaris = ?, 
                            nomor_sk_ahu = ?, 
                            tanggal_sk_ahu = ?, 
                            isi_akta_summary = ?, 
                            file_path = ?,
                            tipe_akta = ?
                        WHERE id = ? AND company_id = ?";
                
                $stmt = $conn_post->prepare($sql);
                if ($stmt === false) throw new Exception("Error persiapan statement update: " . $conn_post->error);
                
                $stmt->bind_param("sssssssssii", 
                    $nomor_akta, $tanggal_akta, $nama_notaris,
                    $domisili_notaris, $nomor_sk_ahu, $tanggal_sk_ahu,
                    $isi_akta_summary, $file_path_to_save, $tipe_akta,
                    $deed_id, $company_id
                );
                
                if (!$stmt->execute()) {
                    throw new Exception($conn_post->error); 
                }
                $stmt->close();
                
                // Jika checkbox "Jadikan Akta Terakhir" dicentang
                if ($is_akta_terakhir) {
                    $stmt_update_company = $conn_post->prepare("UPDATE companies SET id_akta_terakhir = ? WHERE id = ?");
                    if ($stmt_update_company === false) throw new Exception("Gagal prepare update company: " . $conn_post->error);
                    
                    $stmt_update_company->bind_param("ii", $deed_id, $company_id);
                    if (!$stmt_update_company->execute()) {
                         throw new Exception("Gagal update akta terakhir: " . $stmt_update_company->error);
                    }
                    $stmt_update_company->close();
                }

                $conn_post->commit();
                $conn_post->close();

                $_SESSION['flash_message'] = "Akta No. '" . htmlspecialchars($nomor_akta) . "' berhasil diperbarui!";
                header("Location: " . BASE_URL . "/modules/companies/view_company.php?id=" . $company_id);
                exit;

            } catch (Exception $e) {
                $conn_post->rollback();
                if($conn_post->ping()) $conn_post->close();
                
                // Jika error, dan kita TADI-nya upload file baru, hapus file baru itu.
                if (isset($target_path_physical) && file_exists($target_path_physical)) {
                     unlink($target_path_physical);
                }
                
                $error_message = "Error saat menyimpan data: " . $e->getMessage();
            }
        }
    }
}

// === LOGIKA GET (Saat Halaman Dimuat) ===

// 2. Include template header
require dirname(dirname(__DIR__)) . '/includes/header.php';

// 3. Proteksi Halaman (Hanya Admin)
if ($tier != 'Admin') {
    echo "<script>alert('Anda tidak memiliki hak akses untuk halaman ini.'); window.location.href = '" . BASE_URL . "/dashboard.php';</script>";
    exit;
}

// 6. Ambil data Akta & Perusahaan untuk form
$conn_get = new mysqli($servername, $username, $password, $dbname);
if ($conn_get->connect_error) {
    $error_message = "Koneksi Gagal: " . $conn_get->connect_error;
} else {
    $conn_get->set_charset("utf8mb4");
    
    // Ambil data Akta
    $stmt_deed = $conn_get->prepare("SELECT * FROM deeds WHERE id = ? AND company_id = ?");
    if ($stmt_deed) {
        $stmt_deed->bind_param("ii", $deed_id, $company_id);
        $stmt_deed->execute();
        $result_deed = $stmt_deed->get_result();
        if ($result_deed->num_rows === 0) {
            $error_message = "Error: Data akta tidak ditemukan.";
        } else {
            $deed_details = $result_deed->fetch_assoc();
        }
        $stmt_deed->close();
    } else {
        $error_message = "Gagal query data akta: " . $conn_get->error;
    }

    // Ambil data Perusahaan (untuk nama & cek akta terakhir)
    if ($deed_details) {
        $stmt_company = $conn_get->prepare("SELECT nama_perusahaan, id_akta_terakhir FROM companies WHERE id = ?");
        if ($stmt_company) {
            $stmt_company->bind_param("i", $company_id);
            $stmt_company->execute();
            $result_company = $stmt_company->get_result();
            if ($row_company = $result_company->fetch_assoc()) {
                $company_details = $row_company;
                $is_currently_akta_terakhir = ($company_details['id_akta_terakhir'] == $deed_id);
                // Set judul halaman
                $page_title = "Edit Akta No. " . htmlspecialchars($deed_details['nomor_akta']);
                echo "<script>document.title = '" . htmlspecialchars($page_title, ENT_QUOTES) . " - " . htmlspecialchars($company_details['nama_perusahaan'], ENT_QUOTES) . " - PNU';</script>";
            }
            $stmt_company->close();
        }
    }
    $conn_get->close();
}


// 8. Include template sidebar
require dirname(dirname(__DIR__)) . '/includes/sidebar.php';
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    
    <div class="max-w-4xl mx-auto">
    
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-800"><?php echo $page_title; ?></h1>
                <p class="text-gray-600">Untuk Perusahaan: <?php echo htmlspecialchars($company_details['nama_perusahaan'] ?? 'N/A'); ?></p>
            </div>
            <a href="<?php echo BASE_URL; ?>/modules/companies/view_company.php?id=<?php echo $company_id; ?>" 
               class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg text-sm font-semibold transition duration-200 flex items-center no-underline">
               <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
               Batal
            </a>
        </div>
    
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($deed_details): // Hanya tampilkan form jika data akta ditemukan ?>
        <form action="<?php echo BASE_URL; ?>/modules/companies/edit_deed.php?id=<?php echo $deed_id; ?>&company_id=<?php echo $company_id; ?>" method="POST" enctype="multipart/form-data" class="bg-white p-6 rounded-lg shadow-md space-y-6 border border-gray-200">
            
            <input type="hidden" name="deed_id" value="<?php echo $deed_id; ?>">
            <input type="hidden" name="company_id" value="<?php echo $company_id; ?>">
            <input type="hidden" name="old_file_path" value="<?php echo htmlspecialchars($deed_details['file_path'] ?? ''); ?>">
    
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label for="nomor_akta" class="form-label">Nomor Akta <span class="text-red-500">*</span></label>
                    <input type="text" id="nomor_akta" name="nomor_akta" class="form-input" required value="<?php echo htmlspecialchars($deed_details['nomor_akta']); ?>">
                </div>
                <div>
                    <label for="tanggal_akta" class="form-label">Tanggal Akta <span class="text-red-500">*</span></label>
                    <input type="date" id="tanggal_akta" name="tanggal_akta" class="form-input" required value="<?php echo htmlspecialchars($deed_details['tanggal_akta']); ?>">
                </div>
                <div>
                    <label for="tipe_akta" class="form-label">Tipe Akta <span class="text-red-500">*</span></label>
                    <select id="tipe_akta" name="tipe_akta" class="form-input" required>
                        <option value="Perubahan" <?php if ($deed_details['tipe_akta'] == 'Perubahan') echo 'selected'; ?>>Akta Perubahan</option>
                        <option value="Pendirian" <?php if ($deed_details['tipe_akta'] == 'Pendirian') echo 'selected'; ?>>Akta Pendirian</option>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                 <div>
                    <label for="nama_notaris" class="form-label">Nama Notaris <span class="text-red-500">*</span></label>
                    <input type="text" id="nama_notaris" name="nama_notaris" class="form-input" required value="<?php echo htmlspecialchars($deed_details['nama_notaris']); ?>">
                </div>
                 <div>
                    <label for="domisili_notaris" class="form-label">Domisili Notaris</label>
                    <input type="text" id="domisili_notaris" name="domisili_notaris" class="form-input" value="<?php echo htmlspecialchars($deed_details['domisili_notaris'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                 <div>
                    <label for="nomor_sk_ahu" class="form-label">Nomor SK AHU</label>
                    <input type="text" id="nomor_sk_ahu" name="nomor_sk_ahu" class="form-input" value="<?php echo htmlspecialchars($deed_details['nomor_sk_ahu'] ?? ''); ?>">
                </div>
                 <div>
                    <label for="tanggal_sk_ahu" class="form-label">Tanggal SK AHU</label>
                    <input type="date" id="tanggal_sk_ahu" name="tanggal_sk_ahu" class="form-input" value="<?php echo htmlspecialchars($deed_details['tanggal_sk_ahu'] ?? ''); ?>">
                </div>
            </div>
    
            <div>
                <label for="isi_akta_summary" class="form-label">Ringkasan Isi Akta</label>
                <textarea id="isi_akta_summary" name="isi_akta_summary" rows="5" class="form-input" placeholder="Contoh: Perubahan pengurus, Peningkatan modal, Perubahan KBLI, dll..."><?php echo htmlspecialchars($deed_details['isi_akta_summary'] ?? ''); ?></textarea>
            </div>
            
            <div>
                <label for="scan_akta" class="form-label">Upload Scan Akta (PDF)</label>
                <?php if (!empty($deed_details['file_path']) && file_exists(BASE_PATH . '/' . $deed_details['file_path'])): ?>
                    <div class="mb-2 text-sm">
                        File saat ini: 
                        <a href="<?php echo BASE_URL . '/' . htmlspecialchars($deed_details['file_path']); ?>" target="_blank" class="text-blue-600 hover:underline"><?php echo basename($deed_details['file_path']); ?></a>
                    </div>
                    <label class="text-sm text-gray-700">Ganti file (opsional):</label>
                <?php endif; ?>
                <input type="file" id="scan_akta" name="scan_akta" class="form-input file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" accept="application/pdf">
                <p class="text-xs text-gray-500 mt-1">Format: PDF. Maks: 10MB. Mengupload file baru akan menghapus file lama.</p>
            </div>
    
            <div>
                <label class="flex items-center" for="is_akta_terakhir">
                    <input type="checkbox" id="is_akta_terakhir" name="is_akta_terakhir" value="1" class="h-4 w-4 rounded text-blue-600 focus:ring-blue-500 border-gray-300 mr-2" <?php if ($is_currently_akta_terakhir) echo 'checked'; ?>>
                    <span class="text-sm text-gray-700 font-medium">Jadikan ini sebagai Akta Terakhir Perusahaan</span>
                </label>
            </div>
    
            <div class="mt-8 pt-6 border-t flex justify-end">
                <button type="submit" 
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition duration-200 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                    Simpan Perubahan
                </button>
            </div>
    
        </form>
        <?php endif; // Akhir dari if($deed_details) ?>
    
    </div>
    
</main>

<?php
require dirname(dirname(__DIR__)) . '/includes/footer.php';
?>