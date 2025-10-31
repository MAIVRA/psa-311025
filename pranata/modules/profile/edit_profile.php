<?php
// modules/profile/edit_profile.php

// 1. Set variabel halaman (DI PINDAH KE ATAS)
$page_title = "Edit Profile";
$page_active = "profile";

// 2. Mulai session DULU (Jika belum dimulai oleh file lain)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Panggil db.php untuk koneksi & BASE_PATH (DI PINDAH KE ATAS)
require '../../includes/db.php'; 

// Cek login DULU (DI PINDAH KE ATAS)
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}
$user_id = $_SESSION['user_id']; // Ambil user_id dari session

$errors = [];
$user_data = []; // Untuk menampung data user nanti

// === [LOGIKA PENTING: Folder Upload] === (DI PINDAH KE ATAS)
$upload_dir_relative = 'uploads/profile_pics/';
$upload_dir_physical = BASE_PATH . '/' . $upload_dir_relative;
if (!is_dir($upload_dir_physical)) {
    if (!mkdir($upload_dir_physical, 0777, true)) {
        // Jangan die(), tampilkan error nanti saja jika upload gagal
    }
}
// === [AKHIR LOGIKA FOLDER] ===


// === 3. LOGIKA POST (DI PINDAH KE ATAS, SEBELUM HEADER.PHP) ===
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Ambil data dari form
    $alamat = trim($_POST['alamat']);
    $telepon = trim($_POST['telepon']);
    $no_ktp = trim($_POST['no_ktp']);
    $npwp = trim($_POST['npwp']);
    
    // Terapkan strtoupper()
    $bank_nama = strtoupper(trim($_POST['bank_nama']));
    $bank_rekening = trim($_POST['bank_rekening']);
    $bank_atas_nama = strtoupper(trim($_POST['bank_atas_nama']));

    // Kontak Darurat
    $kontak_darurat_nama = strtoupper(trim($_POST['kontak_darurat_nama']));
    $kontak_darurat_hubungan = trim($_POST['kontak_darurat_hubungan']);
    $kontak_darurat_telepon = trim($_POST['kontak_darurat_telepon']);
    
    $password_baru = $_POST['password_baru'];
    $konfirmasi_password = $_POST['konfirmasi_password'];

    $sql_parts = [];
    $params = [];
    $types = "";

    // Field dasar
    $sql_parts = [
        "alamat = ?", "telepon = ?", "no_ktp = ?", "npwp = ?",
        "bank_nama = ?", "bank_rekening = ?", "bank_atas_nama = ?",
        "kontak_darurat_nama = ?", "kontak_darurat_hubungan = ?", "kontak_darurat_telepon = ?"
    ];
    $types = "ssssssssss"; 
    $params = [
        $alamat, $telepon, $no_ktp, $npwp, 
        $bank_nama, $bank_rekening, $bank_atas_nama,
        $kontak_darurat_nama, $kontak_darurat_hubungan, $kontak_darurat_telepon
    ];

    // Validasi & Logika Ganti Password 
    if (!empty($password_baru)) {
        if ($password_baru !== $konfirmasi_password) {
            $errors[] = "Konfirmasi Password Baru tidak cocok!";
        } elseif (strlen($password_baru) < 6) {
            $errors[] = "Password baru harus minimal 6 karakter.";
        } else {
            $password_hashed = password_hash($password_baru, PASSWORD_DEFAULT);
            $sql_parts[] = "password = ?";
            $types .= "s";
            $params[] = $password_hashed;
        }
    }

    // Validasi & Logika Upload Foto Profil
    $foto_path_lama = $_POST['foto_path_lama']; 
    
    if (isset($_FILES['foto_profile']) && $_FILES['foto_profile']['error'] == UPLOAD_ERR_OK) {
        // Cek writable DULU sebelum proses
        if (!is_writable($upload_dir_physical)) {
             $errors[] = "Error: Folder upload '{$upload_dir_relative}' tidak writable. Hubungi Admin.";
        } else {
            $foto_info = $_FILES['foto_profile'];
            $foto_name = $foto_info['name'];
            $foto_tmp_name = $foto_info['tmp_name'];
            $foto_size = $foto_info['size'];
            
            $foto_ext = strtolower(pathinfo($foto_name, PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png'];

            if (in_array($foto_ext, $allowed_ext)) {
                if ($foto_size <= 2 * 1024 * 1024) { // Max 2MB
                    $new_foto_name = "user_{$user_id}_" . time() . '.' . $foto_ext;
                    $new_foto_path_physical = $upload_dir_physical . $new_foto_name;
                    $new_foto_path_relative = $upload_dir_relative . $new_foto_name; 

                    if (move_uploaded_file($foto_tmp_name, $new_foto_path_physical)) {
                        $sql_parts[] = "foto_profile_path = ?";
                        $types .= "s";
                        $params[] = $new_foto_path_relative;

                        if (!empty($foto_path_lama) && $foto_path_lama != $new_foto_path_relative) {
                            $old_foto_physical = BASE_PATH . '/' . $foto_path_lama;
                            if (file_exists($old_foto_physical)) {
                                @unlink($old_foto_physical);
                            }
                        }
                    } else {
                        $errors[] = "Gagal memindahkan file foto yang diupload.";
                    }
                } else {
                    $errors[] = "Ukuran file foto terlalu besar (maks 2MB).";
                }
            } else {
                $errors[] = "Format file foto tidak diizinkan (hanya JPG, JPEG, PNG).";
            }
        } // end is_writable check
    } elseif (isset($_FILES['foto_profile']) && $_FILES['foto_profile']['error'] != UPLOAD_ERR_NO_FILE) {
         $errors[] = "Terjadi error saat upload foto: Error Code " . $_FILES['foto_profile']['error'];
    }

    // --- Eksekusi Query Update ---
    if (empty($errors)) {
        $params[] = $user_id;
        $types .= "i";
        $sql = "UPDATE users SET " . implode(", ", $sql_parts) . " WHERE id = ?";
        
        $stmt_update = $conn->prepare($sql);
        if ($stmt_update === false) {
             $errors[] = "Gagal mempersiapkan statement update: " . $conn->error;
        } else {
            $stmt_update->bind_param($types, ...$params);
            if ($stmt_update->execute()) {
                $_SESSION['flash_message'] = "Profile Anda berhasil diperbarui!";
                session_write_close(); 
                header("Location: view_profile.php");
                exit; 
            } else {
                $errors[] = "Gagal memperbarui database: " . $stmt_update->error;
            }
            $stmt_update->close();
        }
    }
} // --- Akhir LOGIKA POST ---


// === Panggil header.php SEKARANG (setelah POST logic) ===
require '../../includes/header.php';
// $nama_lengkap, $tier, $app_akses sudah ada dari header.php


// === 4. LOGIKA GET (Load data ke form, dijalankan setelah POST atau jika bukan POST) ===
// (Jika POST error, data lama akan diambil lagi di sini untuk ditampilkan di form)
$stmt_get = $conn->prepare("SELECT * FROM users WHERE id = ?");
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
    $foto_display = BASE_URL . '/' . htmlspecialchars($user_data['foto_profile_path']) . '?v=' . time(); // Tambah 'cache buster'
}


// 5. Panggil Sidebar (SEKARANG)
require '../../includes/sidebar.php';
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    <div class="container mx-auto max-w-4xl">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Edit Profile</h1>

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
            <form action="edit_profile.php" method="POST" enctype="multipart/form-data" class="card-content space-y-5">
                
                <input type="hidden" name="foto_path_lama" value="<?php echo htmlspecialchars($user_data['foto_profile_path']); ?>">

                <div class="flex items-center space-x-6 pb-4 border-b">
                    <img id="foto_preview" src="<?php echo $foto_display; ?>" alt="Foto Profil" class="w-24 h-24 rounded-full object-cover border-4 border-gray-200">
                    <div>
                        <label for="foto_profile" class="form-label">Ganti Foto Profil</label>
                        <input type="file" id="foto_profile" name="foto_profile" class="form-input" accept="image/png, image/jpeg, image/jpg">
                        <p class="text-xs text-gray-500 mt-1">Maks 2MB. (JPG, JPEG, PNG)</p>
                    </div>
                </div>

                <div class="border-b pb-4">
                    <h3 class="text-lg font-semibold mb-3">Informasi Karyawan (Read-only)</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-input bg-gray-100" value="<?php echo htmlspecialchars($user_data['nama_lengkap']); ?>" readonly>
                        </div>
                        <div>
                            <label class="form-label">Email (Login)</label>
                            <input type="email" class="form-input bg-gray-100" value="<?php echo htmlspecialchars($user_data['email']); ?>" readonly>
                        </div>
                        <div>
                            <label class="form-label">NIK</label>
                            <input type="text" class="form-input bg-gray-100" value="<?php echo htmlspecialchars($user_data['nik']); ?>" readonly>
                        </div>
                        <div>
                            <label class="form-label">Jabatan</label>
                            <input type="text" class="form-input bg-gray-100" value="<?php echo htmlspecialchars($user_data['nama_jabatan']); ?>" readonly>
                        </div>
                    </div>
                </div>

                <div class="border-b pb-4">
                    <h3 class="text-lg font-semibold mb-3">Data Kontak & Pribadi</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="telepon" class="form-label">Nomor Telepon</label>
                            <input type="text" id="telepon" name="telepon" class="form-input" 
                                   value="<?php echo htmlspecialchars($user_data['telepon']); ?>">
                        </div>
                        <div>
                            <label for="alamat" class="form-label">Alamat Lengkap</label>
                            <textarea id="alamat" name="alamat" rows="3" class="form-input"><?php echo htmlspecialchars($user_data['alamat']); ?></textarea>
                        </div>
                        <div>
                            <label for="no_ktp" class="form-label">No. KTP</label>
                            <input type="text" id="no_ktp" name="no_ktp" class="form-input" 
                                   value="<?php echo htmlspecialchars($user_data['no_ktp']); ?>">
                        </div>
                        <div>
                            <label for="npwp" class="form-label">NPWP</label>
                            <input type="text" id="npwp" name="npwp" class="form-input" 
                                   value="<?php echo htmlspecialchars($user_data['npwp']); ?>">
                        </div>
                    </div>
                </div>

                <div class="border-b pb-4">
                    <h3 class="text-lg font-semibold mb-3">Informasi Bank (Payroll)</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="bank_nama" class="form-label">Nama Bank</label>
                            <input type="text" id="bank_nama" name="bank_nama" class="form-input uppercase-input" 
                                   value="<?php echo htmlspecialchars($user_data['bank_nama']); ?>">
                        </div>
                        <div>
                            <label for="bank_rekening" class="form-label">Nomor Rekening</label>
                            <input type="text" id="bank_rekening" name="bank_rekening" class="form-input" 
                                   value="<?php echo htmlspecialchars($user_data['bank_rekening']); ?>">
                        </div>
                        <div>
                            <label for="bank_atas_nama" class="form-label">Atas Nama</label>
                            <input type="text" id="bank_atas_nama" name="bank_atas_nama" class="form-input uppercase-input" 
                                   value="<?php echo htmlspecialchars($user_data['bank_atas_nama']); ?>">
                        </div>
                    </div>
                </div>

                <div class="border-b pb-4">
                    <h3 class="text-lg font-semibold mb-3">Kontak Darurat</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="kontak_darurat_nama" class="form-label">Nama</label>
                            <input type="text" id="kontak_darurat_nama" name="kontak_darurat_nama" class="form-input uppercase-input" 
                                   value="<?php echo htmlspecialchars($user_data['kontak_darurat_nama']); ?>">
                        </div>
                        <div>
                            <label for="kontak_darurat_hubungan" class="form-label">Hubungan</label>
                            <input type="text" id="kontak_darurat_hubungan" name="kontak_darurat_hubungan" class="form-input" 
                                   value="<?php echo htmlspecialchars($user_data['kontak_darurat_hubungan']); ?>">
                        </div>
                        <div>
                            <label for="kontak_darurat_telepon" class="form-label">Nomor Telepon</label>
                            <input type="text" id="kontak_darurat_telepon" name="kontak_darurat_telepon" class="form-input" 
                                   value="<?php echo htmlspecialchars($user_data['kontak_darurat_telepon']); ?>">
                        </div>
                    </div>
                </div>

                <div class="pb-4">
                    <h3 class="text-lg font-semibold mb-3">Ganti Password</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="password_baru" class="form-label">Password Baru</label>
                            <input type="password" id="password_baru" name="password_baru" class="form-input" 
                                   placeholder="Kosongkan jika tidak ingin mengubah">
                        </div>
                        <div>
                            <label for="konfirmasi_password" class="form-label">Konfirmasi Password Baru</label>
                            <input type="password" id="konfirmasi_password" name="konfirmasi_password" class="form-input">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t">
                    <a href="view_profile.php" class="btn-primary-sm btn-secondary no-underline mr-3">
                        Batal
                    </a>
                    <button type="submit" class="btn-primary-sm px-6 py-2">
                        Simpan Perubahan
                    </button>
                </div>

            </form>
        </div>
    </div>
</main>

<script>
    // Script untuk preview foto profil instan
    document.getElementById('foto_profile').addEventListener('change', function(event) {
        const preview = document.getElementById('foto_preview');
        const file = event.target.files[0];
        
        if (file) {
            preview.src = URL.createObjectURL(file);
            preview.onload = function() {
                URL.revokeObjectURL(preview.src) 
            }
        }
    });
</script>

<?php
// 6. Panggil Footer
require '../../includes/footer.php';
?>