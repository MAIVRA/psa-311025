<?php
// modules/companies/add_board.php

// 1. Set variabel khusus untuk halaman ini
$page_active = "manage_companies";

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

// 4. Inisialisasi variabel dan ambil ID Perusahaan dari URL
$error_message = '';
$company_id = $_GET['company_id'] ?? 0;
$company_name = '';
$deeds_for_dropdown = []; // Untuk dropdown Akta Pengangkatan

// 5. Validasi ID Perusahaan dan ambil nama perusahaan + daftar akta
if (empty($company_id)) {
    $_SESSION['flash_message'] = "Error: ID Perusahaan tidak valid untuk menambah pengurus.";
    // === [PERBAIKAN PATH: MENGGUNAKAN BASE_URL] ===
    header("Location: " . BASE_URL . "/modules/companies/manage_companies.php");
    // === [AKHIR PERUBAHAN] ===
    exit;
}

// Buka koneksi
$conn_db = new mysqli($servername, $username, $password, $dbname);
if ($conn_db->connect_error) {
    die("Koneksi Gagal: " . $conn_db->connect_error);
}
$conn_db->set_charset("utf8mb4");

// Ambil nama perusahaan dan daftar akta
try {
    // Nama Perusahaan
    $stmt_comp_name = $conn_db->prepare("SELECT nama_perusahaan FROM companies WHERE id = ?");
    if (!$stmt_comp_name) throw new Exception("Gagal query nama perusahaan: " . $conn_db->error);
    $stmt_comp_name->bind_param("i", $company_id);
    $stmt_comp_name->execute();
    $result_comp_name = $stmt_comp_name->get_result();
    if ($result_comp_name->num_rows === 0) {
        throw new Exception("Perusahaan dengan ID $company_id tidak ditemukan.");
    }
    $company_data = $result_comp_name->fetch_assoc();
    $company_name = $company_data['nama_perusahaan'];
    $stmt_comp_name->close();

    // Daftar Akta untuk Dropdown
    $stmt_deeds = $conn_db->prepare("SELECT id, nomor_akta, tanggal_akta FROM deeds WHERE company_id = ? ORDER BY tanggal_akta DESC, id DESC");
     if (!$stmt_deeds) throw new Exception("Gagal query daftar akta: " . $conn_db->error);
    $stmt_deeds->bind_param("i", $company_id);
    $stmt_deeds->execute();
    $result_deeds = $stmt_deeds->get_result();
    while ($row = $result_deeds->fetch_assoc()) {
        $deeds_for_dropdown[] = $row;
    }
    $stmt_deeds->close();

} catch (Exception $e) {
    $conn_db->close();
    $_SESSION['flash_message'] = "Error: " . $e->getMessage();
     // === [PERBAIKAN PATH: MENGGUNAKAN BASE_URL] ===
    header("Location: " . BASE_URL . "/modules/companies/manage_companies.php");
     // === [AKHIR PERUBAHAN] ===
    exit;
}

// Set judul halaman
$page_title = "Tambah Pengurus untuk " . htmlspecialchars($company_name);

// 6. Logika saat form di-submit (method POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    try {
        // Ambil data dari form
        $nama_lengkap = trim($_POST['nama_lengkap']);
        $jabatan = $_POST['jabatan']; // Enum
        $no_ktp = !empty($_POST['no_ktp']) ? trim($_POST['no_ktp']) : NULL;
        $npwp = !empty($_POST['npwp']) ? trim($_POST['npwp']) : NULL;
        $alamat = !empty($_POST['alamat']) ? $_POST['alamat'] : NULL;
        $telepon = !empty($_POST['telepon']) ? $_POST['telepon'] : NULL;
        $email = !empty($_POST['email']) ? trim($_POST['email']) : NULL;
        $deed_id_pengangkatan = !empty($_POST['deed_id_pengangkatan']) ? (int)$_POST['deed_id_pengangkatan'] : NULL;
        $masa_jabatan_mulai = !empty($_POST['masa_jabatan_mulai']) ? $_POST['masa_jabatan_mulai'] : NULL;
        $masa_jabatan_akhir = !empty($_POST['masa_jabatan_akhir']) ? $_POST['masa_jabatan_akhir'] : NULL;

        // Validasi Sederhana
        if (empty($nama_lengkap) || empty($jabatan)) {
            throw new Exception("Nama Lengkap dan Jabatan wajib diisi.");
        }
        if ($email !== NULL && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
             throw new Exception("Format Email tidak valid.");
        }

        // Insert ke tabel board_members
        $sql_insert = "INSERT INTO board_members (
                            company_id, deed_id_pengangkatan, nama_lengkap, no_ktp, npwp, alamat,
                            telepon, email, jabatan, masa_jabatan_mulai, masa_jabatan_akhir
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt_insert = $conn_db->prepare($sql_insert);
        if ($stmt_insert === false) throw new Exception("Error persiapan statement insert: " . $conn_db->error);

        // Tipe data: i i s s s s s s s s s
        $stmt_insert->bind_param("iisssssssss",
            $company_id, $deed_id_pengangkatan, $nama_lengkap, $no_ktp, $npwp, $alamat,
            $telepon, $email, $jabatan, $masa_jabatan_mulai, $masa_jabatan_akhir
        );

        if (!$stmt_insert->execute()) {
            throw new Exception("Gagal menyimpan data pengurus: " . $stmt_insert->error);
        }
        $stmt_insert->close();
        $conn_db->close();

        // Set flash message dan redirect kembali ke view company
        $_SESSION['flash_message'] = "Pengurus '" . htmlspecialchars($nama_lengkap) . "' berhasil ditambahkan!";
         // === [PERBAIKAN PATH: MENGGUNAKAN BASE_URL] ===
        header("Location: " . BASE_URL . "/modules/companies/view_company.php?id=" . $company_id);
         // === [AKHIR PERUBAHAN] ===
        exit;

    } catch (Exception $e) {
        if($conn_db->ping()) $conn_db->close(); // Pastikan koneksi ditutup jika error
        $error_message = "Error: " . $e->getMessage();
    }
} else {
    // Tutup koneksi jika bukan POST
     if($conn_db->ping()) $conn_db->close();
}

// Data statis untuk dropdown Jabatan
$list_jabatan = ['Komisaris Utama','Komisaris','Direktur Utama','Direktur'];


// 7. Include template sidebar
// === [PERBAIKAN PATH: MENGGUNAKAN BASE_PATH] ===
require dirname(dirname(__DIR__)) . '/includes/sidebar.php'; // BASE_PATH.'/includes/sidebar.php'
// === [AKHIR PERUBAHAN] ===
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">

    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800"><?php echo $page_title; ?></h1>
        <a href="<?php echo BASE_URL; ?>/modules/companies/view_company.php?id=<?php echo $company_id; ?>"
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

    <form action="<?php echo BASE_URL; ?>/modules/companies/add_board.php?company_id=<?php echo $company_id; ?>" method="POST" class="bg-white p-6 rounded-lg shadow-md space-y-6 border border-gray-200 max-w-2xl mx-auto">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="nama_lengkap" class="form-label">Nama Lengkap <span class="text-red-500">*</span></label>
                <input type="text" id="nama_lengkap" name="nama_lengkap" class="form-input uppercase-input" required>
            </div>
             <div>
                <label for="jabatan" class="form-label">Jabatan <span class="text-red-500">*</span></label>
                <select id="jabatan" name="jabatan" class="form-input" required>
                    <option value="" disabled selected>Pilih Jabatan</option>
                    <?php foreach ($list_jabatan as $j): ?>
                        <option value="<?php echo $j; ?>"><?php echo $j; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="no_ktp" class="form-label">No. KTP</label>
                <input type="text" id="no_ktp" name="no_ktp" class="form-input">
            </div>
             <div>
                <label for="npwp" class="form-label">NPWP</label>
                <input type="text" id="npwp" name="npwp" class="form-input">
            </div>
        </div>

        <div>
            <label for="alamat" class="form-label">Alamat</label>
            <textarea id="alamat" name="alamat" rows="3" class="form-input"></textarea>
        </div>

         <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="telepon" class="form-label">No. Telepon</label>
                <input type="text" id="telepon" name="telepon" class="form-input">
            </div>
             <div>
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" class="form-input">
            </div>
        </div>

         <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
             <div>
                <label for="deed_id_pengangkatan" class="form-label">Akta Pengangkatan</label>
                <select id="deed_id_pengangkatan" name="deed_id_pengangkatan" class="form-input">
                    <option value="">Pilih Akta Pengangkatan (Jika Ada)</option>
                    <?php foreach ($deeds_for_dropdown as $deed): ?>
                        <option value="<?php echo $deed['id']; ?>">
                            No: <?php echo htmlspecialchars($deed['nomor_akta']); ?> (<?php echo date('d M Y', strtotime($deed['tanggal_akta'])); ?>)
                        </option>
                    <?php endforeach; ?>
                     <?php if (empty($deeds_for_dropdown)): ?>
                         <option value="" disabled>Belum ada akta tercatat untuk perusahaan ini</option>
                    <?php endif; ?>
                </select>
            </div>
             <div>
                <label for="masa_jabatan_mulai" class="form-label">Masa Jabatan Mulai</label>
                <input type="date" id="masa_jabatan_mulai" name="masa_jabatan_mulai" class="form-input">
            </div>
             <div>
                <label for="masa_jabatan_akhir" class="form-label">Masa Jabatan Akhir</label>
                <input type="date" id="masa_jabatan_akhir" name="masa_jabatan_akhir" class="form-input">
            </div>
        </div>


        <div class="mt-8 pt-6 border-t flex justify-end">
            <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition duration-200 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                Simpan Data Pengurus
            </button>
        </div>

    </form>

</main>
<?php
// === [PERBAIKAN PATH: MENGGUNAKAN BASE_PATH] ===
require dirname(dirname(__DIR__)) . '/includes/footer.php'; // BASE_PATH.'/includes/footer.php'
// === [AKHIR PERUBAHAN] ===
?>