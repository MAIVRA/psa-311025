<?php
// modules/companies/add_shareholder.php

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
$nilai_nominal_saham = 0; // Akan diambil dari DB

// 5. Validasi ID Perusahaan dan ambil nama + nilai nominal saham
if (empty($company_id)) {
    $_SESSION['flash_message'] = "Error: ID Perusahaan tidak valid untuk menambah pemegang saham.";
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

// Ambil nama perusahaan dan nilai nominal saham
try {
    $stmt_comp_info = $conn_db->prepare("SELECT nama_perusahaan, nilai_nominal_saham FROM companies WHERE id = ?");
    if (!$stmt_comp_info) throw new Exception("Gagal query info perusahaan: " . $conn_db->error);
    $stmt_comp_info->bind_param("i", $company_id);
    $stmt_comp_info->execute();
    $result_comp_info = $stmt_comp_info->get_result();
    if ($result_comp_info->num_rows === 0) {
        throw new Exception("Perusahaan dengan ID $company_id tidak ditemukan.");
    }
    $company_data = $result_comp_info->fetch_assoc();
    $company_name = $company_data['nama_perusahaan'];
    $nilai_nominal_saham = (float)$company_data['nilai_nominal_saham']; // Simpan untuk perhitungan modal
    $stmt_comp_info->close();
} catch (Exception $e) {
    $conn_db->close();
    $_SESSION['flash_message'] = "Error: " . $e->getMessage();
    // === [PERBAIKAN PATH: MENGGUNAKAN BASE_URL] ===
    header("Location: " . BASE_URL . "/modules/companies/manage_companies.php");
    // === [AKHIR PERUBAHAN] ===
    exit;
}

// Set judul halaman
$page_title = "Tambah Pemegang Saham untuk " . htmlspecialchars($company_name);

// 6. Logika saat form di-submit (method POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Mulai Transaksi
    $conn_db->begin_transaction();
    try {
        // Ambil data dari form
        $nama_pemegang = trim($_POST['nama_pemegang']);
        $nomor_identitas = trim($_POST['nomor_identitas']);
        $npwp = !empty($_POST['npwp']) ? trim($_POST['npwp']) : NULL;
        $jumlah_saham = isset($_POST['jumlah_saham']) ? (int)$_POST['jumlah_saham'] : 0; // Pastikan integer

        // Validasi Sederhana
        if (empty($nama_pemegang) || empty($nomor_identitas) || $jumlah_saham <= 0) {
            throw new Exception("Nama, Nomor Identitas, dan Jumlah Saham (harus > 0) wajib diisi.");
        }

        // LANGKAH 1: Insert ke tabel shareholders (persentase diisi 0 dulu)
        $sql_insert_sh = "INSERT INTO shareholders (
                                company_id, nama_pemegang, nomor_identitas, npwp, jumlah_saham, persentase_kepemilikan
                            ) VALUES (?, ?, ?, ?, ?, 0)"; // Persentase 0 dulu

        $stmt_insert = $conn_db->prepare($sql_insert_sh);
        if ($stmt_insert === false) throw new Exception("Error persiapan statement insert: " . $conn_db->error);

        // Tipe data: i s s s i
        $stmt_insert->bind_param("isssi",
            $company_id, $nama_pemegang, $nomor_identitas, $npwp, $jumlah_saham
        );

        if (!$stmt_insert->execute()) {
            throw new Exception("Gagal menyimpan data pemegang saham: " . $stmt_insert->error);
        }
        $stmt_insert->close();

        // LANGKAH 2: Hitung ulang Total Saham dan Modal Disetor
        $total_saham_perusahaan = 0;
        $stmt_total_saham = $conn_db->prepare("SELECT SUM(jumlah_saham) AS total_saham FROM shareholders WHERE company_id = ?");
        if (!$stmt_total_saham) throw new Exception("Gagal hitung total saham: " . $conn_db->error);
        $stmt_total_saham->bind_param("i", $company_id);
        $stmt_total_saham->execute();
        $result_total_saham = $stmt_total_saham->get_result();
        if ($row_total = $result_total_saham->fetch_assoc()) {
            $total_saham_perusahaan = (int)$row_total['total_saham'];
        }
        $stmt_total_saham->close();

        // Hitung Modal Disetor Baru
        $new_modal_disetor = $total_saham_perusahaan * $nilai_nominal_saham;

        // LANGKAH 3: Update Modal Disetor di tabel companies
        $sql_update_company = "UPDATE companies SET modal_disetor = ? WHERE id = ?";
        $stmt_update_comp = $conn_db->prepare($sql_update_company);
        if ($stmt_update_comp === false) throw new Exception("Error persiapan statement update company: " . $conn_db->error);
        $stmt_update_comp->bind_param("di", $new_modal_disetor, $company_id); // 'd' untuk double/bigint
        if (!$stmt_update_comp->execute()) {
            throw new Exception("Gagal update modal disetor: " . $stmt_update_comp->error);
        }
        $stmt_update_comp->close();

        // LANGKAH 4: Hitung ulang dan Update Persentase Kepemilikan untuk SEMUA pemegang saham
        if ($total_saham_perusahaan > 0) { // Hindari pembagian dengan nol
            $stmt_get_all_sh = $conn_db->prepare("SELECT id, jumlah_saham FROM shareholders WHERE company_id = ?");
            if (!$stmt_get_all_sh) throw new Exception("Gagal get all shareholders: " . $conn_db->error);
            $stmt_get_all_sh->bind_param("i", $company_id);
            $stmt_get_all_sh->execute();
            $result_all_sh = $stmt_get_all_sh->get_result();

            $sql_update_percent = "UPDATE shareholders SET persentase_kepemilikan = ? WHERE id = ?";
            $stmt_update_pct = $conn_db->prepare($sql_update_percent);
            if (!$stmt_update_pct) throw new Exception("Gagal prepare update percentage: " . $conn_db->error);

            while ($sh = $result_all_sh->fetch_assoc()) {
                $percentage = ((float)$sh['jumlah_saham'] / $total_saham_perusahaan) * 100;
                $stmt_update_pct->bind_param("di", $percentage, $sh['id']); // 'd' for double/decimal
                if (!$stmt_update_pct->execute()) {
                    // Log error tapi jangan hentikan transaksi, coba update yg lain
                    error_log("Gagal update persentase untuk shareholder ID " . $sh['id'] . ": " . $stmt_update_pct->error);
                }
            }
            $stmt_get_all_sh->close();
            $stmt_update_pct->close();
        } else {
             // Jika total saham 0 (misal setelah delete), set semua persentase jadi 0
             $conn_db->query("UPDATE shareholders SET persentase_kepemilikan = 0 WHERE company_id = $company_id");
        }

        // Commit Transaksi
        $conn_db->commit();
        $conn_db->close();

        // Set flash message dan redirect kembali ke view company
        $_SESSION['flash_message'] = "Pemegang saham '" . htmlspecialchars($nama_pemegang) . "' berhasil ditambahkan!";
        // === [PERBAIKAN PATH: MENGGUNAKAN BASE_URL] ===
        header("Location: " . BASE_URL . "/modules/companies/view_company.php?id=" . $company_id);
        // === [AKHIR PERUBAHAN] ===
        exit;

    } catch (Exception $e) {
        $conn_db->rollback();
        if($conn_db->ping()) $conn_db->close();
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

    <div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-lg relative mb-4 text-sm" role="alert">
        <span class="block sm:inline">Info: Nilai Nominal per Saham untuk perusahaan ini adalah <strong>Rp <?php echo number_format($nilai_nominal_saham, 0, ',', '.'); ?></strong>. Modal Disetor akan dihitung otomatis.</span>
    </div>


    <?php if (!empty($error_message)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
        </div>
    <?php endif; ?>

    <form action="<?php echo BASE_URL; ?>/modules/companies/add_shareholder.php?company_id=<?php echo $company_id; ?>" method="POST" class="bg-white p-6 rounded-lg shadow-md space-y-6 border border-gray-200 max-w-xl mx-auto">
    <div>
            <label for="nama_pemegang" class="form-label">Nama Pemegang Saham <span class="text-red-500">*</span></label>
            <input type="text" id="nama_pemegang" name="nama_pemegang" class="form-input uppercase-input" required>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="nomor_identitas" class="form-label">Nomor Identitas (KTP/NIB) <span class="text-red-500">*</span></label>
                <input type="text" id="nomor_identitas" name="nomor_identitas" class="form-input" required>
            </div>
             <div>
                <label for="npwp" class="form-label">NPWP</label>
                <input type="text" id="npwp" name="npwp" class="form-input">
            </div>
        </div>

        <div>
            <label for="jumlah_saham" class="form-label">Jumlah Lembar Saham Dimiliki <span class="text-red-500">*</span></label>
            <input type="number" id="jumlah_saham" name="jumlah_saham" class="form-input" required min="1" value="1">
             <p class="text-xs text-gray-500 mt-1">Modal Disetor dan Persentase akan dihitung otomatis.</p>
        </div>


        <div class="mt-8 pt-6 border-t flex justify-end">
            <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition duration-200 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                Simpan Pemegang Saham
            </button>
        </div>

    </form>

</main>
<?php
// === [PERBAIKAN PATH: MENGGUNAKAN BASE_PATH] ===
require dirname(dirname(__DIR__)) . '/includes/footer.php'; // BASE_PATH.'/includes/footer.php'
// === [AKHIR PERUBAHAN] ===
?>