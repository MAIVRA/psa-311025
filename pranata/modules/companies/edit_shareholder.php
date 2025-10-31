<?php
// modules/companies/edit_shareholder.php

$page_title = "Edit Pemegang Saham";
$page_active = "manage_companies";

require '../../includes/header.php'; // Path relatif ke header.php
if ($tier != 'Admin') {
    $_SESSION['flash_message'] = "Anda tidak memiliki hak akses untuk halaman ini!";
    header("Location: " . BASE_URL . "/dashboard.php");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$company_id = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;
$errors = [];

// Helper function untuk file upload
function handleFileUpload($file, $uploadDir, $baseFileName) {
    if (isset($file) && $file['error'] == UPLOAD_ERR_OK) {
        
        // Buat direktori jika belum ada
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];

        if (!in_array($fileExtension, $allowedExtensions)) {
            return ['error' => 'Tipe file tidak diizinkan. Hanya (PDF, JPG, PNG).'];
        }

        $newFileName = $baseFileName . "_" . time() . "." . $fileExtension;
        $targetPath = $uploadDir . $newFileName;
        
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            // Return path relatif untuk DB
            return ['path' => str_replace(BASE_PATH . '/', '', $targetPath)];
        } else {
            return ['error' => 'Gagal memindahkan file.'];
        }
    }
    return ['path' => null]; // Tidak ada file baru diupload
}


// Logika POST (Update Data)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil data dari form
    $id = (int)$_POST['id'];
    $company_id = (int)$_POST['company_id'];
    $nama_pemegang = trim($_POST['nama_pemegang']);
    $nomor_identitas = trim($_POST['nomor_identitas']);
    $npwp = trim($_POST['npwp']);
    $jumlah_saham = (int)$_POST['jumlah_saham'];
    $persentase_kepemilikan = (float)$_POST['persentase_kepemilikan'];
    
    // Path file lama dari hidden input
    $old_file_identitas = $_POST['old_file_identitas'];
    $old_file_npwp = $_POST['old_file_npwp'];

    // Path default adalah path lama
    $file_identitas_path_sql = $old_file_identitas;
    $file_npwp_path_sql = $old_file_npwp;

    // Tentukan direktori upload
    // BASE_PATH dari db.php (cth: C:/xampp/htdocs/pranata)
    $uploadDir = BASE_PATH . "/uploads/companies/{$company_id}/shareholders/";

    // Handle Upload File Identitas
    if (isset($_FILES['file_identitas']) && $_FILES['file_identitas']['error'] == UPLOAD_ERR_OK) {
        $uploadIdentitas = handleFileUpload($_FILES['file_identitas'], $uploadDir, "shareholder_{$id}_identitas");
        
        if (isset($uploadIdentitas['error'])) {
            $errors[] = "File Identitas: " . $uploadIdentitas['error'];
        } else if ($uploadIdentitas['path']) {
            $file_identitas_path_sql = $uploadIdentitas['path'];
            // Hapus file lama jika upload baru berhasil
            if ($old_file_identitas && file_exists(BASE_PATH . '/' . $old_file_identitas)) {
                unlink(BASE_PATH . '/' . $old_file_identitas);
            }
        }
    }

    // Handle Upload File NPWP
    if (isset($_FILES['file_npwp']) && $_FILES['file_npwp']['error'] == UPLOAD_ERR_OK) {
        $uploadNpwp = handleFileUpload($_FILES['file_npwp'], $uploadDir, "shareholder_{$id}_npwp");
        
        if (isset($uploadNpwp['error'])) {
            $errors[] = "File NPWP: " . $uploadNpwp['error'];
        } else if ($uploadNpwp['path']) {
            $file_npwp_path_sql = $uploadNpwp['path'];
            // Hapus file lama jika upload baru berhasil
            if ($old_file_npwp && file_exists(BASE_PATH . '/' . $old_file_npwp)) {
                unlink(BASE_PATH . '/' . $old_file_npwp);
            }
        }
    }


    if (empty($errors)) {
        // Update data ke database
        $sql = "UPDATE shareholders SET 
                    nama_pemegang = ?, 
                    nomor_identitas = ?, 
                    file_identitas_path = ?, 
                    npwp = ?, 
                    file_npwp_path = ?, 
                    jumlah_saham = ?, 
                    persentase_kepemilikan = ?
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssdi", 
            $nama_pemegang, 
            $nomor_identitas, 
            $file_identitas_path_sql, 
            $npwp, 
            $file_npwp_path_sql, 
            $jumlah_saham, 
            $persentase_kepemilikan, 
            $id
        );

        if ($stmt->execute()) {
            $_SESSION['flash_message'] = "Data pemegang saham berhasil diperbarui!";
            header("Location: view_company.php?id=" . $company_id . "#shareholders");
            exit;
        } else {
            $errors[] = "Gagal memperbarui database: " . $stmt->error;
        }
        $stmt->close();
    }

} else {
    // Logika GET (Load Data)
    if ($id == 0 || $company_id == 0) {
        $_SESSION['flash_message'] = "ID Pemegang Saham atau Perusahaan tidak valid!";
        header("Location: manage_companies.php");
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM shareholders WHERE id = ? AND company_id = ?");
    $stmt->bind_param("ii", $id, $company_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        $_SESSION['flash_message'] = "Data pemegang saham tidak ditemukan!";
        header("Location: view_company.php?id=" . $company_id);
        exit;
    }
    $shareholder = $result->fetch_assoc();
    $stmt->close();
}

require '../../includes/sidebar.php'; // Path relatif ke sidebar.php
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    <div class="container mx-auto max-w-2xl">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Edit Pemegang Saham</h1>

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
            <form action="edit_shareholder.php?id=<?php echo $id; ?>&company_id=<?php echo $company_id; ?>" method="POST" enctype="multipart/form-data" class="card-content space-y-5">
                
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($shareholder['id']); ?>">
                <input type="hidden" name="company_id" value="<?php echo htmlspecialchars($shareholder['company_id']); ?>">
                <input type="hidden" name="old_file_identitas" value="<?php echo htmlspecialchars($shareholder['file_identitas_path']); ?>">
                <input type="hidden" name="old_file_npwp" value="<?php echo htmlspecialchars($shareholder['file_npwp_path']); ?>">

                <div>
                    <label for="nama_pemegang" class="form-label">Nama Pemegang Saham</label>
                    <input type="text" id="nama_pemegang" name="nama_pemegang" class="form-input uppercase-input" 
                           value="<?php echo htmlspecialchars($shareholder['nama_pemegang']); ?>" required>
                </div>

                <div>
                    <label for="nomor_identitas" class="form-label">Nomor Identitas (KTP/NIB)</label>
                    <input type="text" id="nomor_identitas" name="nomor_identitas" class="form-input"
                           value="<?php echo htmlspecialchars($shareholder['nomor_identitas']); ?>">
                </div>

                <div>
                    <label for="file_identitas" class="form-label">Upload File Identitas (KTP/NIB)</label>
                    <input type="file" id="file_identitas" name="file_identitas" class="form-input">
                    <p class="text-xs text-gray-500 mt-1">Kosongkan jika tidak ingin mengganti file. (Hanya PDF, JPG, PNG)</p>
                    
                    <?php if ($shareholder['file_identitas_path']): ?>
                        <div class="mt-2">
                            <a href="<?php echo BASE_URL . '/' . htmlspecialchars($shareholder['file_identitas_path']); ?>" target="_blank" 
                               class="text-blue-600 hover:text-blue-800 text-sm">
                                Lihat File Identitas Saat Ini
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <label for="npwp" class="form-label">NPWP</label>
                    <input type="text" id="npwp" name="npwp" class="form-input"
                           value="<?php echo htmlspecialchars($shareholder['npwp']); ?>">
                </div>

                <div>
                    <label for="file_npwp" class="form-label">Upload File NPWP</label>
                    <input type="file" id="file_npwp" name="file_npwp" class="form-input">
                    <p class="text-xs text-gray-500 mt-1">Kosongkan jika tidak ingin mengganti file. (Hanya PDF, JPG, PNG)</p>
                    
                    <?php if ($shareholder['file_npwp_path']): ?>
                        <div class="mt-2">
                            <a href="<?php echo BASE_URL . '/' . htmlspecialchars($shareholder['file_npwp_path']); ?>" target="_blank" 
                               class="text-blue-600 hover:text-blue-800 text-sm">
                                Lihat File NPWP Saat Ini
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="jumlah_saham" class="form-label">Jumlah Saham</label>
                        <input type="number" id="jumlah_saham" name="jumlah_saham" class="form-input" 
                               value="<?php echo htmlspecialchars($shareholder['jumlah_saham']); ?>">
                    </div>
                    <div>
                        <label for="persentase_kepemilikan" class="form-label">Persentase (%)</label>
                        <input type="number" step="0.01" id="persentase_kepemilikan" name="persentase_kepemilikan" class="form-input" 
                               value="<?php echo htmlspecialchars($shareholder['persentase_kepemilikan']); ?>">
                    </div>
                </div>

                <div class="flex justify-end space-x-3 pt-4 border-t">
                    <a href="view_company.php?id=<?php echo $company_id; ?>#shareholders" 
                       class="btn-primary-sm btn-secondary no-underline">
                        Batal
                    </a>
                    <button type="submit" class="btn-primary-sm">
                        Simpan Perubahan
                    </button>
                </div>

            </form>
        </div>
    </div>
</main>

<?php
require '../../includes/footer.php'; // Path relatif ke footer.php
?>