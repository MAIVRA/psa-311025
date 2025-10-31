<?php
// modules/users/add_user.php

// 1. Set variabel khusus untuk halaman ini
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$page_title = "Tambah User Baru"; // Default untuk Admin
$page_active = "manage_users"; // Default untuk Admin

// Cek jika diakses dari menu HR
$is_hr = (isset($_SESSION['app_akses']) && $_SESSION['app_akses'] == 'HR');
if ($is_hr) {
    $page_title = "Tambah Karyawan";
    $page_active = "hr_daftar_karyawan"; // Highlight menu HR
}


// 2. Panggil file header
require '../../includes/header.php'; // $conn, $user_id, $_SESSION tersedia

// 3. Keamanan Halaman (PENTING!)
// Sekarang HR juga boleh mengakses halaman ini
if ($_SESSION['app_akses'] != 'Admin' && $_SESSION['app_akses'] != 'HR') {
    // Panggil sidebar
    require '../../includes/sidebar.php';
    
    // Tampilkan pesan error di konten
    echo '<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">';
    echo '  <div class="card">';
    echo '    <div class="card-content">';
    echo '      <h2 class="text-xl font-bold text-red-600">Akses Ditolak</h2>';
    echo '      <p class="text-gray-600 mt-2">Anda tidak memiliki izin untuk mengakses halaman ini.</p>';
    echo '    </div>';
    echo '  </div>';
    echo '</main>';
    
    // Panggil footer
    require '../../includes/footer.php';
    
    // Hentikan eksekusi sisa script
    exit;
}


// 4. Inisialisasi variabel
$errors = [];
$success_message = '';
// Variabel form default
$nik = $nama_lengkap = $email = $telepon = $nama_jabatan = $status_karyawan = $tier = $app_akses = '';
$atasan_id = $id_direktorat = $id_divisi = $id_departemen = $id_section = null;
$tanggal_masuk = $expired_pkwt = null;
// Variabel form
$alamat = $no_ktp = $npwp = $bank_nama = $bank_rekening = $bank_atas_nama = '';
$kontak_darurat_nama = $kontak_darurat_hubungan = $kontak_darurat_telepon = '';
// [PERUBAHAN] Variabel Data HRD baru
$jenis_kelamin = $status_perkawinan = $pendidikan_terakhir = '';
$jumlah_tanggungan = 0;


// Ambil data untuk dropdown
$list_direktorat = $conn->query("SELECT id, nama_direktorat FROM direktorat ORDER BY nama_direktorat");
$list_atasan = $conn->query("SELECT id, nama_lengkap FROM users WHERE status_karyawan IN ('PKWT', 'PKWTT', 'BOD', 'BOC') ORDER BY nama_lengkap");

// [PERUBAHAN] Data statis untuk dropdown baru
$list_status_karyawan = ['PKWT', 'PKWTT', 'Freelance', 'OS', 'Magang', 'KHL'];
$list_status_ptkp = [
    'TK/0' => 'TK/0 (Tidak Kawin, 0 Tanggungan)',
    'TK/1' => 'TK/1 (Tidak Kawin, 1 Tanggungan)',
    'TK/2' => 'TK/2 (Tidak Kawin, 2 Tanggungan)',
    'TK/3' => 'TK/3 (Tidak Kawin, 3 Tanggungan)',
    'K/0'  => 'K/0 (Kawin, 0 Tanggungan)',
    'K/1'  => 'K/1 (Kawin, 1 Tanggungan)',
    'K/2'  => 'K/2 (Kawin, 2 Tanggungan)',
    'K/3'  => 'K/3 (Kawin, 3 Tanggungan)',
];
$list_pendidikan = ['Tidak Sekolah','SD','SMP','SMA/SMK','D1','D2','D3','S1','S2','S3'];


// 5. Logika POST (Saat tombol 'Simpan' diklik)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil data dari form
    $nik = trim($_POST['nik']);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    
    // [PERUBAHAN] Ambil data HRD baru
    $jenis_kelamin = $_POST['jenis_kelamin'] ?: null;
    
    $telepon = trim($_POST['telepon']) ?: null;
    $nama_jabatan = trim($_POST['nama_jabatan']) ?: null;
    $status_karyawan = $_POST['status_karyawan'];
    $tanggal_masuk = !empty($_POST['tanggal_masuk']) ? $_POST['tanggal_masuk'] : null;
    $expired_pkwt = !empty($_POST['expired_pkwt']) ? $_POST['expired_pkwt'] : null;
    
    $id_direktorat = !empty($_POST['id_direktorat']) ? (int)$_POST['id_direktorat'] : null;
    $id_divisi = !empty($_POST['id_divisi']) ? (int)$_POST['id_divisi'] : null;
    $id_departemen = !empty($_POST['id_departemen']) ? (int)$_POST['id_departemen'] : null;
    $id_section = !empty($_POST['id_section']) ? (int)$_POST['id_section'] : null;
    $atasan_id = !empty($_POST['atasan_id']) ? (int)$_POST['atasan_id'] : null;

    $tier = $_POST['tier'];
    
    $app_akses = 'Karyawan'; // Default
    if ($_SESSION['app_akses'] == 'Admin') {
        $app_akses = $_POST['app_akses']; // Admin bisa memilih
    }
    
    // Ambil data dari form
    $alamat = trim($_POST['alamat']) ?: null;
    $no_ktp = trim($_POST['no_ktp']) ?: null;
    $npwp = trim($_POST['npwp']) ?: null;
    
    // [PERUBAHAN] Ambil data HRD baru
    $status_perkawinan = $_POST['status_perkawinan'] ?: null;
    $pendidikan_terakhir = $_POST['pendidikan_terakhir'] ?: null;
    $jumlah_tanggungan = (int)($_POST['jumlah_tanggungan'] ?? 0);
    
    $bank_nama = trim($_POST['bank_nama']) ?: null;
    $bank_rekening = trim($_POST['bank_rekening']) ?: null;
    $bank_atas_nama = trim($_POST['bank_atas_nama']) ?: null;
    $kontak_darurat_nama = trim($_POST['kontak_darurat_nama']) ?: null;
    $kontak_darurat_hubungan = trim($_POST['kontak_darurat_hubungan']) ?: null;
    $kontak_darurat_telepon = trim($_POST['kontak_darurat_telepon']) ?: null;

    // Validasi
    if (empty($nik)) $errors[] = "NIK tidak boleh kosong.";
    if (empty($nama_lengkap)) $errors[] = "Nama lengkap tidak boleh kosong.";
    if (empty($email)) $errors[] = "Email tidak boleh kosong.";
    if (empty($jenis_kelamin)) $errors[] = "Jenis kelamin tidak boleh kosong.";
    if (empty($status_perkawinan)) $errors[] = "Status Perkawinan (PTKP) tidak boleh kosong.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Format email tidak valid.";
    if (empty($password)) $errors[] = "Password tidak boleh kosong.";
    if ($password != $password_confirm) $errors[] = "Konfirmasi password tidak cocok.";
    if (empty($status_karyawan)) $errors[] = "Status karyawan harus dipilih.";
    if (empty($tier)) $errors[] = "Level Jabatan (Tier) harus dipilih.";
    if (empty($app_akses) && $_SESSION['app_akses'] == 'Admin') $errors[] = "Hak akses aplikasi harus dipilih.";

    // Cek NIK unik
    $stmt_nik = $conn->prepare("SELECT id FROM users WHERE nik = ?");
    $stmt_nik->bind_param("s", $nik);
    $stmt_nik->execute();
    if ($stmt_nik->get_result()->num_rows > 0) {
        $errors[] = "NIK sudah terdaftar. Gunakan NIK lain.";
    }
    $stmt_nik->close();

    // Cek Email unik
    $stmt_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt_email->bind_param("s", $email);
    $stmt_email->execute();
    if ($stmt_email->get_result()->num_rows > 0) {
        $errors[] = "Email sudah terdaftar. Gunakan email lain.";
    }
    $stmt_email->close();

    // Jika tidak ada error, lakukan INSERT
    if (empty($errors)) {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // [PERUBAHAN] Query INSERT diperbarui
        $sql_insert = "INSERT INTO users (
                            nik, password, nama_lengkap, email, jenis_kelamin, telepon, nama_jabatan, 
                            id_direktorat, id_divisi, id_departemen, id_section, atasan_id, 
                            tanggal_masuk, status_karyawan, expired_pkwt, 
                            tier, app_akses,
                            alamat, no_ktp, npwp, status_perkawinan, pendidikan_terakhir, jumlah_tanggungan,
                            bank_nama, bank_rekening, bank_atas_nama,
                            kontak_darurat_nama, kontak_darurat_hubungan, kontak_darurat_telepon
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, 
                            ?, ?, ?, ?, ?, 
                            ?, ?, ?,
                            ?, ?,
                            ?, ?, ?, ?, ?, ?,
                            ?, ?, ?,
                            ?, ?, ?
                        )";
        
        $stmt_insert = $conn->prepare($sql_insert);
        if ($stmt_insert === false) {
            $errors[] = "Gagal mempersiapkan statement: " . $conn->error;
        } else {
            // [PERUBAHAN] bind_param diperbarui (29 parameter)
            $stmt_insert->bind_param("ssssssssiiiiisssssssssissssss", 
                $nik, $hashed_password, $nama_lengkap, $email, $jenis_kelamin, $telepon, $nama_jabatan,
                $id_direktorat, $id_divisi, $id_departemen, $id_section, $atasan_id,
                $tanggal_masuk, $status_karyawan, $expired_pkwt,
                $tier, $app_akses,
                $alamat, $no_ktp, $npwp, $status_perkawinan, $pendidikan_terakhir, $jumlah_tanggungan,
                $bank_nama, $bank_rekening, $bank_atas_nama,
                $kontak_darurat_nama, $kontak_darurat_hubungan, $kontak_darurat_telepon
            );
            
            if ($stmt_insert->execute()) {
                $_SESSION['flash_message'] = "Karyawan baru berhasil ditambahkan.";
                
                // Arahkan kembali ke halaman daftar yang sesuai
                if ($is_hr) {
                    header("Location: ../hr/hr_daftar_karyawan.php");
                } else {
                    header("Location: manage_users.php");
                }
                exit;
            } else {
                $errors[] = "Gagal menyimpan data: " . $stmt_insert->error;
            }
            $stmt_insert->close();
        }
    }
}

// 6. Panggil Sidebar
require '../../includes/sidebar.php'; 
?>

<main class="overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    
    <h1 class="text-3xl font-bold text-gray-800 mb-6"><?php echo htmlspecialchars($page_title); ?></h1>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-6" role="alert">
            <strong class="font-bold">Error!</strong>
            <ul class="mt-2 list-disc list-inside">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <form action="add_user.php" method="POST">
            <div class="card-content space-y-6">
                
                <div class="border-b pb-6">
                    <h2 class="text-xl font-semibold text-gray-700 mb-4">Informasi Login & Utama</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="nama_lengkap" class="form-label">Nama Lengkap <span class="text-red-500">*</span></label>
                            <input type="text" id="nama_lengkap" name="nama_lengkap" class="form-input" value="<?php echo htmlspecialchars($nama_lengkap); ?>" required>
                        </div>
                        <div>
                            <label for="email" class="form-label">Email <span class="text-red-500">*</span></label>
                            <input type="email" id="email" name="email" class="form-input" value="<?php echo htmlspecialchars($email); ?>" required>
                        </div>
                        <div>
                            <label for="jenis_kelamin" class="form-label">Jenis Kelamin <span class="text-red-500">*</span></label>
                            <select id="jenis_kelamin" name="jenis_kelamin" class="form-input" required>
                                <option value="">-- Pilih --</option>
                                <option value="Laki-laki" <?php echo ($jenis_kelamin == 'Laki-laki') ? 'selected' : ''; ?>>Laki-laki</option>
                                <option value="Perempuan" <?php echo ($jenis_kelamin == 'Perempuan') ? 'selected' : ''; ?>>Perempuan</option>
                            </select>
                        </div>
                        <div>
                            <label for="password" class="form-label">Password <span class="text-red-500">*</span></label>
                            <input type="password" id="password" name="password" class="form-input" required>
                        </div>
                        <div>
                            <label for="password_confirm" class="form-label">Konfirmasi Password <span class="text-red-500">*</span></label>
                            <input type="password" id="password_confirm" name="password_confirm" class="form-input" required>
                        </div>
                    </div>
                </div>

                <div class="border-b pb-6">
                    <h2 class="text-xl font-semibold text-gray-700 mb-4">Informasi Kepegawaian</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="nik" class="form-label">NIK (Nomor Induk Karyawan) <span class="text-red-500">*</span></label>
                            <input type="text" id="nik" name="nik" class="form-input uppercase-input" value="<?php echo htmlspecialchars($nik); ?>" required>
                            <button type="button" id="btnGenerateNIK" class="btn-secondary text-sm px-3 py-1 mt-2">Generate NIK</button>
                        </div>
                        <div>
                            <label for="nama_jabatan" class="form-label">Nama Jabatan</label>
                            <input type="text" id="nama_jabatan" name="nama_jabatan" class="form-input" value="<?php echo htmlspecialchars($nama_jabatan); ?>">
                        </div>
                        <div>
                            <label for="status_karyawan" class="form-label">Status Karyawan <span class="text-red-500">*</span></label>
                            <select id="status_karyawan" name="status_karyawan" class="form-input" required>
                                <option value="">-- Pilih Status --</option>
                                <?php foreach ($list_status_karyawan as $status): ?>
                                    <option value="<?php echo $status; ?>" <?php echo ($status_karyawan == $status) ? 'selected' : ''; ?>>
                                        <?php echo $status; ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php if ($_SESSION['app_akses'] == 'Admin'): ?>
                                    <option value="BOD" <?php echo ($status_karyawan == 'BOD') ? 'selected' : ''; ?>>BOD</option>
                                    <option value="BOC" <?php echo ($status_karyawan == 'BOC') ? 'selected' : ''; ?>>BOC</option>
                                <?php endif; ?>
                            </select>
                            </div>
                        <div>
                            <label for="tanggal_masuk" class="form-label">Tanggal Masuk</label>
                            <input type="date" id="tanggal_masuk" name="tanggal_masuk" class="form-input" value="<?php echo htmlspecialchars($tanggal_masuk); ?>">
                        </div>
                        <div>
                            <label for="expired_pkwt" class="form-label">Tanggal Habis Kontrak (jika PKWT/Magang)</label>
                            <input type="date" id="expired_pkwt" name="expired_pkwt" class="form-input" value="<?php echo htmlspecialchars($expired_pkwt); ?>">
                        </div>
                        <div>
                            <label for="telepon" class="form-label">Telepon</label>
                            <input type="tel" id="telepon" name="telepon" class="form-input" value="<?php echo htmlspecialchars($telepon); ?>">
                        </div>
                    </div>
                </div>

                <div class="border-b pb-6">
                    <h2 class="text-xl font-semibold text-gray-700 mb-4">Informasi Pribadi & Pajak</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="no_ktp" class="form-label">No. KTP</label>
                            <input type="text" id="no_ktp" name="no_ktp" class="form-input" value="<?php echo htmlspecialchars($no_ktp); ?>">
                        </div>
                        <div>
                            <label for="npwp" class="form-label">NPWP</label>
                            <input type="text" id="npwp" name="npwp" class="form-input" value="<?php echo htmlspecialchars($npwp); ?>">
                        </div>
                        <div class="md:col-span-3">
                            <label for="alamat" class="form-label">Alamat (sesuai KTP)</label>
                            <textarea id="alamat" name="alamat" rows="3" class="form-input"><?php echo htmlspecialchars($alamat); ?></textarea>
                        </div>
                        
                        <div>
                            <label for="status_perkawinan" class="form-label">Status Perkawinan (PTKP) <span class="text-red-500">*</span></label>
                            <select id="status_perkawinan" name="status_perkawinan" class="form-input" required>
                                <option value="">-- Pilih Status Pajak --</option>
                                <?php foreach ($list_status_ptkp as $kode => $deskripsi): ?>
                                    <option value="<?php echo $kode; ?>" <?php echo ($status_perkawinan == $kode) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($deskripsi); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="jumlah_tanggungan" class="form-label">Jumlah Tanggungan</label>
                            <input type="number" id="jumlah_tanggungan" name="jumlah_tanggungan" class="form-input" value="<?php echo htmlspecialchars($jumlah_tanggungan); ?>" min="0" max="3">
                             <p class="text-xs text-gray-500 mt-1">(Sesuai aturan Pajak, maks 3)</p>
                        </div>
                         <div>
                            <label for="pendidikan_terakhir" class="form-label">Pendidikan Terakhir</label>
                            <select id="pendidikan_terakhir" name="pendidikan_terakhir" class="form-input">
                                <option value="">-- Pilih Pendidikan --</option>
                                <?php foreach ($list_pendidikan as $pend): ?>
                                    <option value="<?php echo $pend; ?>" <?php echo ($pendidikan_terakhir == $pend) ? 'selected' : ''; ?>>
                                        <?php echo $pend; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="border-b pb-6">
                    <h2 class="text-xl font-semibold text-gray-700 mb-4">Informasi Bank (untuk Payroll)</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="bank_nama" class="form-label">Nama Bank</label>
                            <input type="text" id="bank_nama" name="bank_nama" class="form-input" value="<?php echo htmlspecialchars($bank_nama); ?>">
                        </div>
                        <div>
                            <label for="bank_rekening" class="form-label">No. Rekening</label>
                            <input type="text" id="bank_rekening" name="bank_rekening" class="form-input" value="<?php echo htmlspecialchars($bank_rekening); ?>">
                        </div>
                        <div>
                            <label for="bank_atas_nama" class="form-label">Atas Nama Rekening</label>
                            <input type="text" id="bank_atas_nama" name="bank_atas_nama" class="form-input" value="<?php echo htmlspecialchars($bank_atas_nama); ?>">
                        </div>
                    </div>
                </div>

                <div class="border-b pb-6">
                    <h2 class="text-xl font-semibold text-gray-700 mb-4">Kontak Darurat</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="kontak_darurat_nama" class="form-label">Nama</label>
                            <input type="text" id="kontak_darurat_nama" name="kontak_darurat_nama" class="form-input" value="<?php echo htmlspecialchars($kontak_darurat_nama); ?>">
                        </div>
                        <div>
                            <label for="kontak_darurat_hubungan" class="form-label">Hubungan</label>
                            <input type="text" id="kontak_darurat_hubungan" name="kontak_darurat_hubungan" class="form-input" value="<?php echo htmlspecialchars($kontak_darurat_hubungan); ?>">
                        </div>
                        <div>
                            <label for="kontak_darurat_telepon" class="form-label">Telepon</label>
                            <input type="tel" id="kontak_darurat_telepon" name="kontak_darurat_telepon" class="form-input" value="<?php echo htmlspecialchars($kontak_darurat_telepon); ?>">
                        </div>
                    </div>
                </div>

                <div>
                    <h2 class="text-xl font-semibold text-gray-700 mb-4">Struktur Organisasi & Hak Akses</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="id_direktorat" class="form-label">Direktorat</label>
                            <select id="id_direktorat" name="id_direktorat" class="form-input">
                                <option value="">-- Pilih Direktorat --</option>
                                <?php mysqli_data_seek($list_direktorat, 0); // Reset pointer ?>
                                <?php while($row = $list_direktorat->fetch_assoc()): ?>
                                    <option value="<?php echo $row['id']; ?>" <?php echo ($id_direktorat == $row['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($row['nama_direktorat']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label for="id_divisi" class="form-label">Divisi</label>
                            <select id="id_divisi" name="id_divisi" class="form-input" disabled>
                                <option value="">-- Pilih Direktorat Dulu --</option>
                            </select>
                        </div>
                        <div>
                            <label for="id_departemen" class="form-label">Departemen</label>
                            <select id="id_departemen" name="id_departemen" class="form-input" disabled>
                                <option value="">-- Pilih Divisi Dulu --</option>
                            </select>
                        </div>
                         <div>
                            <label for="id_section" class="form-label">Section</label>
                            <select id="id_section" name="id_section" class="form-input" disabled>
                                <option value="">-- Pilih Departemen Dulu --</option>
                            </select>
                        </div>
                        <div>
                            <label for="atasan_id" class="form-label">Atasan Langsung</label>
                            <select id="atasan_id" name="atasan_id" class="form-input">
                                <option value="">-- Pilih Atasan --</option>
                                <?php mysqli_data_seek($list_atasan, 0); // Reset pointer ?>
                                <?php while($row = $list_atasan->fetch_assoc()): ?>
                                    <option value="<?php echo $row['id']; ?>" <?php echo ($atasan_id == $row['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($row['nama_lengkap']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="tier" class="form-label">Level Jabatan (Tier) <span class="text-red-500">*</span></label>
                            <select id="tier" name="tier" class="form-input" required>
                                <option value="">-- Pilih Level --</option>
                                <option value="Staf" <?php echo ($tier == 'Staf') ? 'selected' : ''; ?>>Staf</option>
                                <option value="Supervisor" <?php echo ($tier == 'Supervisor') ? 'selected' : ''; ?>>Supervisor</option>
                                <option value="Manager" <?php echo ($tier == 'Manager') ? 'selected' : ''; ?>>Manager</option>
                                
                                <?php if ($_SESSION['app_akses'] == 'Admin'): ?>
                                    <option value="Direksi" <?php echo ($tier == 'Direksi') ? 'selected' : ''; ?>>Direksi</option>
                                    <option value="Komisaris" <?php echo ($tier == 'Komisaris') ? 'selected' : ''; ?>>Komisaris</option>
                                    <option value="Admin" <?php echo ($tier == 'Admin') ? 'selected' : ''; ?>>Admin</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <?php if ($_SESSION['app_akses'] == 'Admin'): ?>
                            <div class="md:col-span-1">
                                <label for="app_akses" class="form-label">Hak Akses Aplikasi <span class="text-red-500">*</span></label>
                                <select id="app_akses" name="app_akses" class="form-input bg-yellow-50" required>
                                    <option value="Karyawan" <?php echo ($app_akses == 'Karyawan') ? 'selected' : ''; ?>>Karyawan (Default)</option>
                                    <option value="HR" <?php echo ($app_akses == 'HR') ? 'selected' : ''; ?>>HR</option>
                                    <option value="Legal" <?php echo ($app_akses == 'Legal') ? 'selected' : ''; ?>>Legal</option>
                                    <option value="Finance" <?php echo ($app_akses == 'Finance') ? 'selected' : ''; ?>>Finance</option>
                                    <option value="Top Management" <?php echo ($app_akses == 'Top Management') ? 'selected' : ''; ?>>Top Management</option>
                                    <option value="Admin" <?php echo ($app_akses == 'Admin') ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>

            </div> <div class="card-footer bg-gray-50 px-6 py-4 flex justify-end space-x-3">
                <a href="<?php echo ($is_hr) ? '../hr/hr_daftar_karyawan.php' : 'manage_users.php'; ?>" class="btn-secondary px-4 py-2 no-underline">
                    Batal
                </a>
                <button type="submit" class="btn-primary-sm bg-blue-600 hover:bg-blue-700 px-4 py-2">
                    Simpan Karyawan
                </button>
            </div>
        </form>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // --- AJAX untuk Dropdown Struktur ---
    const direktoratSelect = document.getElementById('id_direktorat');
    const divisiSelect = document.getElementById('id_divisi');
    const departemenSelect = document.getElementById('id_departemen');
    const sectionSelect = document.getElementById('id_section');

    function resetDropdown(selectElement, defaultText) {
        selectElement.innerHTML = `<option value="">-- ${defaultText} --</option>`;
        selectElement.disabled = true;
    }

    function fetchStruktur(parentId, targetSelect, childSelectName, level) {
        if (!parentId || parentId === "0" || parentId === "") {
            resetDropdown(targetSelect, `Pilih ${level == 2 ? 'Direktorat' : (level == 3 ? 'Divisi' : 'Departemen')} Dulu`);
            return;
        }

        // Tampilkan loading
        targetSelect.innerHTML = `<option value="">Memuat...</option>`;
        targetSelect.disabled = true;

        // Path ke file AJAX
        const ajaxUrl = `ajax_get_struktur.php?level=${level}&parent_id=${parentId}`;

        fetch(ajaxUrl)
            .then(response => response.json())
            .then(data => {
                // Periksa apakah query sukses (dari file ajax)
                if (data.success) { 
                    if (data.data.length > 0) {
                        targetSelect.innerHTML = `<option value="">-- Pilih ${childSelectName} --</option>`;
                        data.data.forEach(item => {
                            targetSelect.innerHTML += `<option value="${item.id}">${item.nama}</option>`;
                        });
                        targetSelect.disabled = false;
                    } else {
                        // Query sukses tapi tidak ada data (cth: Direktorat baru)
                        targetSelect.innerHTML = `<option value="">-- Tidak ada data ${childSelectName} --</option>`;
                        targetSelect.disabled = true;
                    }
                } else {
                    // Query gagal (error SQL, dll)
                    console.error('AJAX Error:', data.message);
                    targetSelect.innerHTML = '<option value="">-- Gagal memuat --</option>';
                    targetSelect.disabled = true;
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                targetSelect.innerHTML = '<option value="">-- Gagal memuat --</option>';
                targetSelect.disabled = true;
            });
    }

    direktoratSelect.addEventListener('change', function() {
        fetchStruktur(this.value, divisiSelect, 'Divisi', 2);
        // Reset anak-anaknya
        resetDropdown(departemenSelect, 'Pilih Divisi Dulu');
        resetDropdown(sectionSelect, 'Pilih Departemen Dulu');
    });

    divisiSelect.addEventListener('change', function() {
        fetchStruktur(this.value, departemenSelect, 'Departemen', 3);
        // Reset anak-anaknya
        resetDropdown(sectionSelect, 'Pilih Departemen Dulu');
    });

    departemenSelect.addEventListener('change', function() {
        fetchStruktur(this.value, sectionSelect, 'Section', 4);
    });
    
    // --- AJAX untuk Generate NIK ---
    document.getElementById('btnGenerateNIK').addEventListener('click', function() {
        const tglMasuk = document.getElementById('tanggal_masuk').value;
        const idDept = document.getElementById('id_departemen').value;
        
        if (!tglMasuk || !idDept) {
            alert('Harap pilih Tanggal Masuk dan Departemen terlebih dahulu untuk generate NIK.');
            return;
        }

        fetch(`ajax_generate_nik.php?tanggal_masuk=${tglMasuk}&id_departemen=${idDept}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('nik').value = data.nik_preview; // [PERUBAHAN] Sesuai output ajax_generate_nik.php
                } else {
                    alert('Error: ' + data.error); // [PERUBAHAN] Sesuai output ajax_generate_nik.php
                }
            })
            .catch(error => {
                console.error('Error fetching NIK:', error);
                alert('Gagal menghubungi server untuk generate NIK.');
            });
    });

});
</script>


<?php require '../../includes/footer.php'; ?>