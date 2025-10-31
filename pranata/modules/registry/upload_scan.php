<?php
// upload_scan.php

// 1. Set variabel khusus untuk halaman ini
// Judul akan di-set setelah data registrasi diambil
$page_active = "view_registry"; // Tetap tandai view_registry sebagai menu aktif

// 2. Include template header
// === [PATH DIPERBAIKI] ===
require '../../includes/header.php';
// === [AKHIR PERUBAHAN] ===

// 3. Ambil data user login (dari header.php)
$user_id_login = $_SESSION['user_id'];
$user_tier_login = $_SESSION['tier'];


// 4. Inisialisasi variabel dan ambil ID Registrasi dari URL
$error_message = '';
$registry_id = $_GET['registry_id'] ?? 0;
$registry_data = null;
$nomor_surat = '';
$old_file_path_db = NULL; // Untuk menyimpan path file LAMA dari DB (relatif root)

// 5. Validasi ID Registrasi
if (empty($registry_id)) {
    $_SESSION['flash_message'] = "Error: ID Registrasi Surat tidak valid.";
    // === [PERBAIKAN PATH: MENGGUNAKAN BASE_URL] ===
    header("Location: " . BASE_URL . "/modules/registry/view_registry.php");
    // === [AKHIR PERBAIKAN] ===
    exit;
}

// 6. Buka koneksi untuk GET data dan POST file
$conn_db = new mysqli($servername, $username, $password, $dbname);
if ($conn_db->connect_error) {
    die("Koneksi Gagal: " . $conn_db->connect_error);
}
$conn_db->set_charset("utf8mb4");

// 7. Ambil data registrasi DAN data untuk validasi akses
try {
    // === [PERBAIKAN KEAMANAN: Ambil data akses] ===
    $stmt_get_reg = $conn_db->prepare("
        SELECT nomor_lengkap, file_path, created_by_id, tipe_dokumen, 
               akses_dokumen, akses_terbatas_level, akses_karyawan_ids 
        FROM document_registry 
        WHERE id = ?
    ");
    // === [AKHIR PERBAIKAN] ===
    
    if (!$stmt_get_reg) throw new Exception("Gagal query data registrasi: " . $conn_db->error);
    $stmt_get_reg->bind_param("i", $registry_id);
    $stmt_get_reg->execute();
    $result_reg = $stmt_get_reg->get_result();
    
    if ($result_reg->num_rows === 0) {
        throw new Exception("Registrasi surat dengan ID $registry_id tidak ditemukan.");
    }
    
    $registry_data = $result_reg->fetch_assoc();
    $nomor_surat = $registry_data['nomor_lengkap'];
    $old_file_path_db = $registry_data['file_path']; // Simpan path lama (relatif root)
    $stmt_get_reg->close();

    // === [PERBAIKAN KEAMANAN: Validasi Hak Akses Upload] ===
    // Logika disalin dari view_registry.php
    $can_access_file = false;
    $is_direksi = ($user_tier_login == 'Direksi');
    $is_uploader = ($registry_data['created_by_id'] == $user_id_login);

    if ($registry_data['akses_dokumen'] == 'Semua') {
        if ($registry_data['tipe_dokumen'] == 'Rahasia' && !$is_uploader && !$is_direksi) {
            $can_access_file = false;
        } else {
            $can_access_file = true;
        }
    } elseif ($registry_data['akses_dokumen'] == 'Dilarang') {
        if ($is_uploader || $is_direksi) {
            $can_access_file = true;
        }
    } elseif ($registry_data['akses_dokumen'] == 'Terbatas') {
        if ($is_uploader || $is_direksi) {
            $can_access_file = true;
        } else {
            $level = $registry_data['akses_terbatas_level'];
            if ($level == 'Manager' && $user_tier_login == 'Manager') {
                $can_access_file = true;
            } elseif ($level == 'Karyawan' && !empty($registry_data['akses_karyawan_ids'])) {
                $allowed_ids = json_decode($registry_data['akses_karyawan_ids'], true);
                if (is_array($allowed_ids) && in_array($user_id_login, $allowed_ids)) {
                    $can_access_file = true;
                }
            }
        }
    }

    // Jika user tidak punya hak akses, tendang keluar
    if (!$can_access_file) {
        throw new Exception("Anda tidak memiliki hak akses untuk mengupload file pada dokumen ini.");
    }
    // === [AKHIR PERBAIKAN KEAMANAN] ===

} catch (Exception $e) {
    $conn_db->close();
    $_SESSION['flash_message'] = "Error: " . $e->getMessage();
    // === [PERBAIKAN PATH: MENGGUNAKAN BASE_URL] ===
    header("Location: " . BASE_URL . "/modules/registry/view_registry.php");
    // === [AKHIR PERBAIKAN] ===
    exit;
}

// Set judul halaman
$page_title = "Upload Scan Surat: " . htmlspecialchars($nomor_surat);


// 8. Logika saat form di-submit (method POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Mulai Transaksi
    $conn_db->begin_transaction();
    $new_file_path_db = NULL; // Path file BARU yg akan disimpan ke DB (relatif root)
    $new_file_path_physical = NULL; // Path file BARU di server (fisik)
    $new_file_uploaded_successfully = false; // Flag

    try {
        // Cek apakah file diupload
        if (!isset($_FILES['scan_file']) || $_FILES['scan_file']['error'] != UPLOAD_ERR_OK) {
            $upload_error_code = $_FILES['scan_file']['error'] ?? UPLOAD_ERR_NO_FILE;
            if ($upload_error_code == UPLOAD_ERR_NO_FILE) {
                throw new Exception("Anda belum memilih file PDF untuk diupload.");
            } else {
                throw new Exception("Terjadi error saat mengupload file: Kode Error " . $upload_error_code);
            }
        }

        $file_info = $_FILES['scan_file'];
        $file_name = $file_info['name'];
        $file_tmp_name = $file_info['tmp_name'];
        $file_size = $file_info['size'];

        // 1. Validasi Ukuran (maks 5MB)
        $max_size = 5 * 1024 * 1024; // 5 MB in bytes
        if ($file_size > $max_size) {
            throw new Exception("Ukuran file terlalu besar. Maksimal 5MB.");
        }

        // 2. Validasi Tipe File (Hanya PDF)
        $allowed_type = 'application/pdf';
        $file_type = mime_content_type($file_tmp_name);
        if ($file_type !== $allowed_type) {
            throw new Exception("Tipe file tidak valid. Hanya file PDF yang diizinkan.");
        }

        // 3. Buat Nama File Unik (misal: scan_REGID_timestamp.pdf)
        $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
        $unique_name = "scan_" . $registry_id . "_" . time() . '.' . strtolower($file_extension);

        // 4. Tentukan Path Tujuan
        // === [PERBAIKAN PATH: MENGGUNAKAN BASE_PATH] ===
        $target_dir_relative_to_root = "uploads/scans/"; // Path relatif dari root pranata/ (untuk DB)
        $target_dir_physical = BASE_PATH . "/" . $target_dir_relative_to_root; // Path fisik dari file ini
        
        // Pastikan folder fisik ada
        if (!is_dir($target_dir_physical)) {
            mkdir($target_dir_physical, 0755, true);
        }

        $new_file_path_physical = $target_dir_physical . $unique_name; // Path fisik lengkap file baru
        $new_file_path_db = $target_dir_relative_to_root . $unique_name; // Path untuk disimpan ke DB
        // === [AKHIR PERBAIKAN] ===

        // 5. Pindahkan File
        if (!move_uploaded_file($file_tmp_name, $new_file_path_physical)) { // Gunakan path fisik
            throw new Exception("Gagal memindahkan file scan yang di-upload.");
        }
        $new_file_uploaded_successfully = true; // Tandai berhasil upload

        // 6. Update file_path di database (gunakan path relatif root)
        $sql_update = "UPDATE document_registry SET file_path = ? WHERE id = ?";
        $stmt_update = $conn_db->prepare($sql_update);
        if ($stmt_update === false) throw new Exception("Gagal prepare update path: " . $conn_db->error);
        $stmt_update->bind_param("si", $new_file_path_db, $registry_id); // Simpan path db

        if (!$stmt_update->execute()) {
             throw new Exception("Gagal mengupdate path file di database: " . $stmt_update->error);
        }
        $stmt_update->close();

        // Commit Transaksi
        $conn_db->commit();

        // 7. Hapus file LAMA jika ada DAN update berhasil
        // === [PERBAIKAN PATH: MENGGUNAKAN BASE_PATH] ===
        if ($old_file_path_db) {
            $old_file_path_physical = BASE_PATH . "/" . $old_file_path_db; // Bentuk path fisik dari path db lama
            if (file_exists($old_file_path_physical)) {
                // Pastikan path lama beda dgn path baru (just in case)
                if ($old_file_path_physical != $new_file_path_physical) {
                    unlink($old_file_path_physical);
                }
            }
        }
        // === [AKHIR PERBAIKAN] ===

        $conn_db->close();

        // Set flash message dan redirect kembali ke view registry
        $_SESSION['flash_message'] = "File scan untuk surat '" . htmlspecialchars($nomor_surat) . "' berhasil diupload!";
        // === [PERBAIKAN PATH: MENGGUNAKAN BASE_URL] ===
        header("Location: " . BASE_URL . "/modules/registry/view_registry.php");
        // === [AKHIR PERBAIKAN] ===
        exit;

    } catch (Exception $e) {
        $conn_db->rollback(); // Batalkan jika ada error

        // === [PERBAIKAN PATH: MENGGUNAKAN BASE_PATH] ===
        // Jika error terjadi SETELAH file baru terupload, hapus file baru tersebut (gunakan path fisik)
        if ($new_file_uploaded_successfully && $new_file_path_physical && file_exists($new_file_path_physical)) {
             unlink($new_file_path_physical);
        }
        // === [AKHIR PERBAIKAN] ===

        $conn_db->close(); // Pastikan koneksi ditutup
        $error_message = "Error: " . $e->getMessage();
    }
} else {
    // Tutup koneksi jika bukan POST
     $conn_db->close();
}


// 8. Include template sidebar
// === [PATH DIPERBAIKI] ===
require '../../includes/sidebar.php';
// === [AKHIR PERUBAHAN] ===
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">

    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800"><?php echo $page_title; ?></h1>
        <a href="<?php echo BASE_URL; ?>/modules/registry/view_registry.php"
           class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg text-sm font-semibold transition duration-200 flex items-center no-underline">
           <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
           Kembali ke Daftar Registrasi
        </a>
        </div>

    <?php
     // === [PERBAIKAN PATH: Cek file fisik, tapi link pakai URL] ===
     $old_file_physical_path = '';
     if ($old_file_path_db) {
         $old_file_physical_path = BASE_PATH . "/" . $old_file_path_db;
     }
     ?>
     <?php if (!empty($old_file_physical_path) && file_exists($old_file_physical_path)): ?>
         <div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-lg relative mb-4 text-sm" role="alert">
             <span class="block sm:inline">Saat ini sudah ada file scan terupload. Mengupload file baru akan **menggantikan** file lama.</span>
             <a href="<?php echo BASE_URL . '/' . htmlspecialchars($old_file_path_db); ?>" target="_blank" class="font-medium underline ml-2">Lihat File Lama</a>
         </div>
     <?php endif; ?>
     <?php if (!empty($error_message)): ?>
         <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4" role="alert">
             <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
         </div>
     <?php endif; ?>
     <form action="<?php echo BASE_URL; ?>/modules/registry/upload_scan.php?registry_id=<?php echo $registry_id; ?>" method="POST" enctype="multipart/form-data" class="bg-white p-6 rounded-lg shadow-md space-y-6 max-w-xl mx-auto">
    <div>
            <label for="scan_file" class="form-label">Pilih File Scan (PDF) <span class="text-red-500">*</span></label>
            <input type="file" id="scan_file" name="scan_file" class="form-input file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" required accept="application/pdf">
            <p class="text-xs text-gray-500 mt-1">Hanya file PDF, maksimal 5MB.</p>
        </div>


        <div class="mt-8 pt-6 border-t flex justify-end">
            <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition duration-200 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                Upload File Scan
            </button>
        </div>

    </form>

</main>
<?php
// 9. Include template footer
// === [PATH DIPERBAIKI] ===
require '../../includes/footer.php';
// === [AKHIR PERUBAHAN] ===
?>