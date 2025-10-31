<?php
// modules/companies/edit_board.php

$page_title = "Edit Pengurus (BOD/BOC)";
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

// Helper function untuk file upload (Sama seperti di edit_shareholder.php)
function handleFileUpload($file, $uploadDir, $baseFileName) {
    if (isset($file) && $file['error'] == UPLOAD_ERR_OK) {
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
            return ['path' => str_replace(BASE_PATH . '/', '', $targetPath)];
        } else {
            return ['error' => 'Gagal memindahkan file.'];
        }
    }
    return ['path' => null];
}

// Logika POST (Update Data)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil data dari form
    $id = (int)$_POST['id'];
    $company_id = (int)$_POST['company_id'];
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $jabatan = trim($_POST['jabatan']);
    $deed_id_pengangkatan = (int)$_POST['deed_id_pengangkatan'] ?: NULL; // Set NULL jika 0
    $no_ktp = trim($_POST['no_ktp']);
    $npwp = trim($_POST['npwp']);
    $alamat = trim($_POST['alamat']);
    $telepon = trim($_POST['telepon']);
    $email = trim($_POST['email']);
    $masa_jabatan_mulai = trim($_POST['masa_jabatan_mulai']);
    $masa_jabatan_akhir = trim($_POST['masa_jabatan_akhir']);
    
    // Path file lama
    $old_file_ktp = $_POST['old_file_ktp'];
    $old_file_npwp = $_POST['old_file_npwp'];

    $file_ktp_path_sql = $old_file_ktp;
    $file_npwp_path_sql = $old_file_npwp;

    $uploadDir = BASE_PATH . "/uploads/companies/{$company_id}/board_members/";

    // Handle Upload File KTP
    if (isset($_FILES['file_ktp']) && $_FILES['file_ktp']['error'] == UPLOAD_ERR_OK) {
        $uploadKtp = handleFileUpload($_FILES['file_ktp'], $uploadDir, "board_{$id}_ktp");
        if (isset($uploadKtp['error'])) {
            $errors[] = "File KTP: " . $uploadKtp['error'];
        } else if ($uploadKtp['path']) {
            $file_ktp_path_sql = $uploadKtp['path'];
            if ($old_file_ktp && file_exists(BASE_PATH . '/' . $old_file_ktp)) {
                unlink(BASE_PATH . '/' . $old_file_ktp);
            }
        }
    }

    // Handle Upload File NPWP
    if (isset($_FILES['file_npwp']) && $_FILES['file_npwp']['error'] == UPLOAD_ERR_OK) {
        $uploadNpwp = handleFileUpload($_FILES['file_npwp'], $uploadDir, "board_{$id}_npwp");
        if (isset($uploadNpwp['error'])) {
            $errors[] = "File NPWP: " . $uploadNpwp['error'];
        } else if ($uploadNpwp['path']) {
            $file_npwp_path_sql = $uploadNpwp['path'];
            if ($old_file_npwp && file_exists(BASE_PATH . '/' . $old_file_npwp)) {
                unlink(BASE_PATH . '/' . $old_file_npwp);
            }
        }
    }

    if (empty($errors)) {
        $sql = "UPDATE board_members SET 
                    nama_lengkap = ?,
                    jabatan = ?,
                    deed_id_pengangkatan = ?,
                    no_ktp = ?,
                    file_ktp_path = ?,
                    npwp = ?,
                    file_npwp_path = ?,
                    alamat = ?,
                    telepon = ?,
                    email = ?,
                    masa_jabatan_mulai = ?,
                    masa_jabatan_akhir = ?
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssisssssssssi", 
            $nama_lengkap, $jabatan, $deed_id_pengangkatan, $no_ktp, $file_ktp_path_sql, 
            $npwp, $file_npwp_path_sql, $alamat, $telepon, $email, 
            $masa_jabatan_mulai, $masa_jabatan_akhir, $id
        );

        if ($stmt->execute()) {
            $_SESSION['flash_message'] = "Data pengurus berhasil diperbarui!";
            header("Location: view_company.php?id=" . $company_id . "#bod");
            exit;
        } else {
            $errors[] = "Gagal memperbarui database: " . $stmt->error;
        }
        $stmt->close();
    }
} else {
    // Logika GET (Load Data)
    if ($id == 0 || $company_id == 0) {
        $_SESSION['flash_message'] = "ID Pengurus atau Perusahaan tidak valid!";
        header("Location: manage_companies.php");
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM board_members WHERE id = ? AND company_id = ?");
    $stmt->bind_param("ii", $id, $company_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        $_SESSION['flash_message'] = "Data pengurus tidak ditemukan!";
        header("Location: view_company.php?id=" . $company_id);
        exit;
    }
    $board_member = $result->fetch_assoc();
    $stmt->close();
}

// Ambil daftar Akta (Deeds) untuk dropdown
$deeds_list = [];
$stmt_deeds = $conn->prepare("SELECT id, nomor_akta, tanggal_akta FROM deeds WHERE company_id = ? ORDER BY tanggal_akta DESC");
$stmt_deeds->bind_param("i", $company_id);
$stmt_deeds->execute();
$result_deeds = $stmt_deeds->get_result();
while ($row = $result_deeds->fetch_assoc()) {
    $deeds_list[] = $row;
}
$stmt_deeds->close();


require '../../includes/sidebar.php'; // Path relatif ke sidebar.php
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    <div class="container mx-auto max-w-3xl">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Edit Pengurus (BOD/BOC)</h1>

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
            <form action="edit_board.php?id=<?php echo $id; ?>&company_id=<?php echo $company_id; ?>" method="POST" enctype="multipart/form-data" class="card-content space-y-5">
                
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($board_member['id']); ?>">
                <input type="hidden" name="company_id" value="<?php echo htmlspecialchars($board_member['company_id']); ?>">
                <input type="hidden" name="old_file_ktp" value="<?php echo htmlspecialchars($board_member['file_ktp_path']); ?>">
                <input type="hidden" name="old_file_npwp" value="<?php echo htmlspecialchars($board_member['file_npwp_path']); ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                        <input type="text" id="nama_lengkap" name="nama_lengkap" class="form-input uppercase-input" 
                               value="<?php echo htmlspecialchars($board_member['nama_lengkap']); ?>" required>
                    </div>
                    <div>
                        <label for="jabatan" class="form-label">Jabatan</label>
                        <select id="jabatan" name="jabatan" class="form-input" required>
                            <option value="Komisaris Utama" <?php echo ($board_member['jabatan'] == 'Komisaris Utama') ? 'selected' : ''; ?>>Komisaris Utama</option>
                            <option value="Komisaris" <?php echo ($board_member['jabatan'] == 'Komisaris') ? 'selected' : ''; ?>>Komisaris</option>
                            <option value="Direktur Utama" <?php echo ($board_member['jabatan'] == 'Direktur Utama') ? 'selected' : ''; ?>>Direktur Utama</option>
                            <option value="Direktur" <?php echo ($board_member['jabatan'] == 'Direktur') ? 'selected' : ''; ?>>Direktur</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="no_ktp" class="form-label">Nomor KTP</label>
                    <input type="text" id="no_ktp" name="no_ktp" class="form-input"
                           value="<?php echo htmlspecialchars($board_member['no_ktp']); ?>">
                </div>

                <div>
                    <label for="file_ktp" class="form-label">Upload File KTP</label>
                    <input type="file" id="file_ktp" name="file_ktp" class="form-input">
                    <p class="text-xs text-gray-500 mt-1">Kosongkan jika tidak ingin mengganti file. (Hanya PDF, JPG, PNG)</p>
                    <?php if ($board_member['file_ktp_path']): ?>
                        <div class="mt-2">
                            <a href="<?php echo BASE_URL . '/' . htmlspecialchars($board_member['file_ktp_path']); ?>" target="_blank" 
                               class="text-blue-600 hover:text-blue-800 text-sm">Lihat File KTP Saat Ini</a>
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <label for="npwp" class="form-label">NPWP</label>
                    <input type="text" id="npwp" name="npwp" class="form-input"
                           value="<?php echo htmlspecialchars($board_member['npwp']); ?>">
                </div>

                <div>
                    <label for="file_npwp" class="form-label">Upload File NPWP</label>
                    <input type="file" id="file_npwp" name="file_npwp" class="form-input">
                    <p class="text-xs text-gray-500 mt-1">Kosongkan jika tidak ingin mengganti file. (Hanya PDF, JPG, PNG)</p>
                    <?php if ($board_member['file_npwp_path']): ?>
                        <div class="mt-2">
                            <a href="<?php echo BASE_URL . '/' . htmlspecialchars($board_member['file_npwp_path']); ?>" target="_blank" 
                               class="text-blue-600 hover:text-blue-800 text-sm">Lihat File NPWP Saat Ini</a>
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <label for="alamat" class="form-label">Alamat</label>
                    <textarea id="alamat" name="alamat" rows="3" class="form-input"><?php echo htmlspecialchars($board_member['alamat']); ?></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="telepon" class="form-label">Telepon</label>
                        <input type="text" id="telepon" name="telepon" class="form-input"
                               value="<?php echo htmlspecialchars($board_member['telepon']); ?>">
                    </div>
                    <div>
                        <label for="email" class="form-label">Email</label>
                        <input type="email" id="email" name="email" class="form-input"
                               value="<?php echo htmlspecialchars($board_member['email']); ?>">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="masa_jabatan_mulai" class="form-label">Masa Jabatan Mulai</label>
                        <input type="date" id="masa_jabatan_mulai" name="masa_jabatan_mulai" class="form-input"
                               value="<?php echo htmlspecialchars($board_member['masa_jabatan_mulai']); ?>">
                    </div>
                    <div>
                        <label for="masa_jabatan_akhir" class="form-label">Masa Jabatan Akhir</label>
                        <input type="date" id="masa_jabatan_akhir" name="masa_jabatan_akhir" class="form-input"
                               value="<?php echo htmlspecialchars($board_member['masa_jabatan_akhir']); ?>">
                    </div>
                </div>
                
                <div>
                    <label for="deed_id_pengangkatan" class="form-label">Akta Pengangkatan (Rujukan)</label>
                    <select id="deed_id_pengangkatan" name="deed_id_pengangkatan" class="form-input">
                        <option value="">-- Tidak Ditentukan --</option>
                        <?php foreach ($deeds_list as $deed): ?>
                            <option value="<?php echo $deed['id']; ?>" <?php echo ($board_member['deed_id_pengangkatan'] == $deed['id']) ? 'selected' : ''; ?>>
                                Akta No. <?php echo htmlspecialchars($deed['nomor_akta']); ?> (Tgl. <?php echo htmlspecialchars($deed['tanggal_akta']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex justify-end space-x-3 pt-4 border-t">
                    <a href="view_company.php?id=<?php echo $company_id; ?>#bod" 
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