<?php
// modules/companies/add_license.php

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

// 4. Inisialisasi variabel dan ambil ID Perusahaan dari URL
$error_message = '';
$company_id = $_GET['company_id'] ?? 0;
$company_name = '';

// 5. Validasi ID Perusahaan dan ambil nama perusahaan
if (empty($company_id)) {
    $_SESSION['flash_message'] = "Error: ID Perusahaan tidak valid untuk menambah izin.";
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

// Ambil nama perusahaan untuk judul
try {
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
} catch (Exception $e) {
    $conn_db->close();
    $_SESSION['flash_message'] = "Error: " . $e->getMessage();
     // === [PERBAIKAN PATH: MENGGUNAKAN BASE_URL] ===
    header("Location: " . BASE_URL . "/modules/companies/manage_companies.php");
     // === [AKHIR PERUBAHAN] ===
    exit;
}

// Set judul halaman
$page_title = "Tambah Izin Baru untuk " . htmlspecialchars($company_name);

// 6. Logika saat form di-submit (method POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    try {
        // Ambil data dari form
        $nama_izin = trim($_POST['nama_izin']);
        $nomor_izin = !empty($_POST['nomor_izin']) ? trim($_POST['nomor_izin']) : NULL;
        $tanggal_izin = !empty($_POST['tanggal_izin']) ? $_POST['tanggal_izin'] : NULL;
        $keterangan = !empty($_POST['keterangan']) ? trim($_POST['keterangan']) : NULL;
        $penerbit_izin = !empty($_POST['penerbit_izin']) ? trim($_POST['penerbit_izin']) : NULL;
        $tanggal_expired = !empty($_POST['tanggal_expired']) ? $_POST['tanggal_expired'] : NULL;


        // Validasi Sederhana
        if (empty($nama_izin)) {
            throw new Exception("Nama Izin wajib diisi.");
        }

        // Insert ke tabel company_licenses
        $sql_insert = "INSERT INTO company_licenses (
                            company_id, nama_izin, nomor_izin, tanggal_izin, keterangan,
                            penerbit_izin, tanggal_expired
                        ) VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt_insert = $conn_db->prepare($sql_insert);
        if ($stmt_insert === false) throw new Exception("Error persiapan statement insert: " . $conn_db->error);

        // Tipe data: i s s s s s s
        $stmt_insert->bind_param("issssss",
            $company_id, $nama_izin, $nomor_izin, $tanggal_izin, $keterangan,
            $penerbit_izin, $tanggal_expired
        );

        if (!$stmt_insert->execute()) {
             throw new Exception("Gagal menyimpan data izin: " . $stmt_insert->error);
        }
        $stmt_insert->close();
        $conn_db->close();

        // Set flash message dan redirect kembali ke view company
        $_SESSION['flash_message'] = "Izin '" . htmlspecialchars($nama_izin) . "' berhasil ditambahkan!";
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

    <form action="<?php echo BASE_URL; ?>/modules/companies/add_license.php?company_id=<?php echo $company_id; ?>" method="POST" class="bg-white p-6 rounded-lg shadow-md space-y-6 max-w-2xl mx-auto border border-gray-200">
    <div>
            <label for="nama_izin" class="form-label">Nama Izin <span class="text-red-500">*</span></label>
            <input type="text" id="nama_izin" name="nama_izin" class="form-input" required placeholder="Contoh: Izin Usaha Perdagangan (SIUP)">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="nomor_izin" class="form-label">Nomor Izin</label>
                <input type="text" id="nomor_izin" name="nomor_izin" class="form-input">
            </div>
             <div>
                <label for="tanggal_izin" class="form-label">Tanggal Terbit Izin</label>
                <input type="date" id="tanggal_izin" name="tanggal_izin" class="form-input">
            </div>
        </div>

        <div>
            <label for="penerbit_izin" class="form-label">Diterbitkan Oleh</label>
            <input type="text" id="penerbit_izin" name="penerbit_izin" class="form-input" placeholder="Contoh: Dinas Penanaman Modal dan PTSP">
        </div>

        <div>
            <label for="keterangan" class="form-label">Keterangan Tambahan</label>
            <textarea id="keterangan" name="keterangan" rows="3" class="form-input"></textarea>
        </div>

        <div>
            <label for="tanggal_expired" class="form-label">Tanggal Kedaluwarsa (Opsional)</label>
            <input type="date" id="tanggal_expired" name="tanggal_expired" class="form-input">
             <p class="text-xs text-gray-500 mt-1">Kosongkan jika izin berlaku seumur hidup.</p>
        </div>


        <div class="mt-8 pt-6 border-t flex justify-end">
            <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition duration-200 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                Simpan Izin
            </button>
        </div>

    </form>

</main>
<?php
// === [PERBAIKAN PATH: MENGGUNAKAN BASE_PATH] ===
require dirname(dirname(__DIR__)) . '/includes/footer.php'; // BASE_PATH.'/includes/footer.php'
// === [AKHIR PERUBAHAN] ===
?>