<?php
// document_codes.php

// 1. Set variabel khusus untuk halaman ini
$page_title = "Kelola Kode Dokumen";
$page_active = "document_codes"; // Untuk menandai menu di sidebar

// 2. Include template header
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

// 4. Inisialisasi variabel
$error_message = '';
$success_message = '';
$document_codes = []; // Untuk menyimpan daftar kode

// 5. Buka koneksi baru
$conn_db = new mysqli($servername, $username, $password, $dbname);
if ($conn_db->connect_error) {
    die("Koneksi Gagal: " . $conn_db->connect_error);
}
$conn_db->set_charset("utf8mb4");


// 6. Logika saat form di-submit (Tambah atau Hapus)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action_type = $_POST['action_type'] ?? '';

    try {
        // A. TAMBAH KODE BARU
        if ($action_type == 'add_code') {
            $kode_surat = strtoupper(trim($_POST['kode_surat'])); // Ubah ke uppercase
            $deskripsi = trim($_POST['deskripsi']);

            if (empty($kode_surat) || empty($deskripsi)) {
                throw new Exception("Kode Surat dan Deskripsi wajib diisi.");
            }
            // Validasi format kode (misal: hanya huruf/angka, maks 10 char)
            if (!preg_match('/^[A-Z0-9]{1,10}$/', $kode_surat)) {
                 throw new Exception("Format Kode Surat tidak valid (hanya huruf kapital/angka, maks 10 karakter).");
            }

            $sql = "INSERT INTO document_codes (kode_surat, deskripsi) VALUES (?, ?)";
            $stmt = $conn_db->prepare($sql);
            if (!$stmt) throw new Exception("Gagal prepare insert: " . $conn_db->error);
            $stmt->bind_param("ss", $kode_surat, $deskripsi);
            if (!$stmt->execute()) {
                 if ($conn_db->errno == 1062) { // Error duplikat
                     throw new Exception("Kode Surat '$kode_surat' sudah ada.");
                 } else {
                     throw new Exception("Gagal menyimpan kode: " . $stmt->error);
                 }
            }
            $stmt->close();
            $success_message = "Kode Dokumen '$kode_surat' berhasil ditambahkan!";

        }
        // B. HAPUS KODE
        elseif ($action_type == 'delete_code') {
             $code_id_to_delete = (int)$_POST['code_id'];

             // Opsional: Cek dulu apakah kode ini sudah pernah dipakai di document_registry
             $check_usage = $conn_db->prepare("SELECT id FROM document_registry WHERE document_code_id = ? LIMIT 1");
             if (!$check_usage) throw new Exception("Gagal cek penggunaan kode: " . $conn_db->error);
             $check_usage->bind_param("i", $code_id_to_delete);
             $check_usage->execute();
             $check_usage->store_result(); // Store result to get num_rows
             if ($check_usage->num_rows > 0) {
                  throw new Exception("Kode Dokumen ini tidak dapat dihapus karena sudah digunakan dalam registrasi surat.");
             }
             $check_usage->close();

             $sql = "DELETE FROM document_codes WHERE id = ?";
             $stmt = $conn_db->prepare($sql);
              if (!$stmt) throw new Exception("Gagal prepare delete: " . $conn_db->error);
             $stmt->bind_param("i", $code_id_to_delete);
             if (!$stmt->execute()) {
                 throw new Exception("Gagal menghapus kode: " . $stmt->error);
             }
             if ($stmt->affected_rows > 0) {
                 $success_message = "Kode Dokumen berhasil dihapus.";
             } else {
                 throw new Exception("Kode Dokumen tidak ditemukan untuk dihapus.");
             }
             $stmt->close();
        }

    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
} // Akhir dari method POST


// 7. Logika GET (Mengambil daftar kode dokumen)
try {
    $result = $conn_db->query("SELECT id, kode_surat, deskripsi FROM document_codes ORDER BY kode_surat ASC");
    if (!$result) throw new Exception("Gagal mengambil daftar kode: " . $conn_db->error);
    while ($row = $result->fetch_assoc()) {
        $document_codes[] = $row;
    }

} catch (Exception $e) {
     $error_message = "Error mengambil data: " . $e->getMessage();
}

// Tutup koneksi jika masih terbuka
if ($conn_db->ping()) {
    $conn_db->close();
}

// 8. Include template sidebar
// === [PERBAIKAN PATH: MENGGUNAKAN BASE_PATH] ===
require dirname(dirname(__DIR__)) . '/includes/sidebar.php'; // BASE_PATH.'/includes/sidebar.php'
// === [AKHIR PERUBAHAN] ===
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">

    <h1 class="text-3xl font-bold text-gray-800 mb-6"><?php echo $page_title; ?></h1>

     <?php if (!empty($error_message)): ?>
         <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4" role="alert">
             <?php echo htmlspecialchars($error_message); ?>
         </div>
     <?php endif; ?>
     <?php if (!empty($success_message)): ?>
         <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative mb-4" role="alert">
             <?php echo htmlspecialchars($success_message); ?>
         </div>
     <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-1">
            <div class="card border border-gray-200">
                 <div class="card-header bg-gray-50"><h2 class="text-xl font-semibold text-gray-800">Tambah Kode Baru</h2></div>
                <div class="card-content">
                     <form action="<?php echo BASE_URL; ?>/modules/registry/document_codes.php" method="POST" class="space-y-4">
                     <input type="hidden" name="action_type" value="add_code">
                        <div>
                            <label for="kode_surat" class="form-label">Kode Surat <span class="text-red-500">*</span></label>
                            <input type="text" id="kode_surat" name="kode_surat" class="form-input uppercase-input" required maxlength="10" placeholder="Contoh: EXT, SK, PKS">
                            <p class="text-xs text-gray-500 mt-1">Hanya huruf kapital & angka, maks 10 karakter.</p>
                        </div>
                        <div>
                            <label for="deskripsi" class="form-label">Deskripsi <span class="text-red-500">*</span></label>
                            <input type="text" id="deskripsi" name="deskripsi" class="form-input" required placeholder="Contoh: Surat Keluar, Surat Keputusan">
                        </div>
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold">Tambah Kode</button>
                     </form>
                </div>
            </div>
        </div>

        <div class="md:col-span-2">
            <div class="card border border-gray-200">
                 <div class="card-header bg-gray-50"><h2 class="text-xl font-semibold text-gray-800">Daftar Kode Dokumen</h2></div>
                 <div class="card-content overflow-x-auto">
                    <table class="w-full min-w-max">
                        <thead>
                            <tr class="bg-gray-100 text-left text-sm font-semibold text-gray-600 uppercase">
                                <th class="py-3 px-4">Kode</th>
                                <th class="py-3 px-4">Deskripsi</th>
                                <th class="py-3 px-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 text-sm">
                            <?php if (empty($document_codes)): ?>
                                <tr>
                                    <td colspan="3" class="py-4 px-4 text-center text-gray-500">Belum ada data kode dokumen.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($document_codes as $code): ?>
                                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                                        <td class="py-3 px-4 font-mono font-semibold"><?php echo htmlspecialchars($code['kode_surat']); ?></td>
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($code['deskripsi']); ?></td>
                                        <td class="py-3 px-4">
                                            <button type="button" onclick="openDeleteModal(<?php echo $code['id']; ?>, '<?php echo htmlspecialchars(addslashes($code['kode_surat']), ENT_QUOTES); ?>')"
                                                    class="text-red-600 hover:text-red-800 font-medium text-xs bg-red-100 px-2 py-1 rounded">
                                                 Hapus
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                 </div>
            </div>
        </div>
    </div>

</main>

<div id="deleteModal" class="modal-overlay hidden">
    <div class="bg-white p-6 rounded-lg shadow-xl max-w-sm w-full mx-4">
        <h3 class="text-lg font-semibold text-gray-800">Konfirmasi Hapus</h3>
        <p class="mt-2 text-gray-600">Apakah Anda yakin ingin menghapus Kode Dokumen <strong id="deleteCodeName"></strong>?</p>
        <p class="text-xs text-red-600 mt-1">Perhatian: Tindakan ini tidak dapat diurungkan.</p>
        <form id="deleteForm" action="<?php echo BASE_URL; ?>/modules/registry/document_codes.php" method="POST" class="mt-6 flex justify-end space-x-3">
        <input type="hidden" name="action_type" value="delete_code">
            <input type="hidden" name="code_id" id="deleteCodeId">
            <button
                type="button"
                onclick="closeDeleteModal()"
                class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg text-sm font-semibold transition duration-200">
                Batal
            </button>
            <button type="submit"
               class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition duration-200">
               Ya, Hapus
            </button>
        </form>
    </div>
</div>

<script>
    // --- Script untuk Modal Konfirmasi Hapus ---
    function openDeleteModal(id, name) {
        document.getElementById('deleteCodeId').value = id;
        document.getElementById('deleteCodeName').innerText = name;
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }

     // --- Script untuk membuat input kode uppercase ---
     const uppercaseInputs = document.querySelectorAll('.uppercase-input');
     uppercaseInputs.forEach(input => {
         input.addEventListener('input', function() {
              this.value = this.value.toUpperCase();
         });
     });
</script>


<?php
// 9. Include template footer
// === [PERBAIKAN PATH: MENGGUNAKAN BASE_PATH] ===
require dirname(dirname(__DIR__)) . '/includes/footer.php'; // BASE_PATH.'/includes/footer.php'
// === [AKHIR PERUBAHAN] ===
?>