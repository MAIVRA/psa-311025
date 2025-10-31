<?php
// modules/users/manage_struktur.php

// 1. Set variabel halaman
$page_title = "Manajemen Struktur Organisasi";
$page_active = "manage_struktur";

// 2. Mulai session dan panggil DB
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require '../../includes/db.php';

// 3. Cek status login dan tier (Admin)
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}
if ($_SESSION['tier'] != 'Admin') {
    $_SESSION['flash_message'] = "Anda tidak memiliki hak akses untuk halaman ini!";
    header("Location: " . BASE_URL . "/dashboard.php");
    exit;
}
$tier = $_SESSION['tier'];

// --- LOGIKA POST (CREATE/UPDATE/DELETE) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'];

    // Tambah Direktorat
    if ($action == 'add_direktorat') {
        $nama_direktorat = trim($_POST['nama_direktorat']);
        $id_pimpinan = !empty($_POST['id_pimpinan']) ? (int)$_POST['id_pimpinan'] : NULL;
        $stmt = $conn->prepare("INSERT INTO direktorat (nama_direktorat, id_pimpinan) VALUES (?, ?)");
        $stmt->bind_param("si", $nama_direktorat, $id_pimpinan);
        $stmt->execute();
        $_SESSION['flash_message'] = "Direktorat baru berhasil ditambahkan.";
    }

    // Tambah Divisi
    elseif ($action == 'add_divisi') {
        $nama_divisi = trim($_POST['nama_divisi']);
        $id_direktorat = (int)$_POST['id_direktorat'];
        $id_pimpinan = !empty($_POST['id_pimpinan']) ? (int)$_POST['id_pimpinan'] : NULL;
        $stmt = $conn->prepare("INSERT INTO divisi (nama_divisi, id_direktorat, id_pimpinan) VALUES (?, ?, ?)");
        $stmt->bind_param("sii", $nama_divisi, $id_direktorat, $id_pimpinan);
        $stmt->execute();
        $_SESSION['flash_message'] = "Divisi baru berhasil ditambahkan.";
    }

    // Tambah Departemen
    elseif ($action == 'add_departemen') {
        $nama_departemen = trim($_POST['nama_departemen']);
        $id_divisi = (int)$_POST['id_divisi'];
        $id_pimpinan = !empty($_POST['id_pimpinan']) ? (int)$_POST['id_pimpinan'] : NULL;
        $stmt = $conn->prepare("INSERT INTO departemen (nama_departemen, id_divisi, id_pimpinan) VALUES (?, ?, ?)");
        $stmt->bind_param("sii", $nama_departemen, $id_divisi, $id_pimpinan);
        $stmt->execute();
        $_SESSION['flash_message'] = "Departemen baru berhasil ditambahkan.";
    }

    // Tambah Section
    elseif ($action == 'add_section') {
        $nama_section = trim($_POST['nama_section']);
        $id_departemen = (int)$_POST['id_departemen'];
        $id_pimpinan = !empty($_POST['id_pimpinan']) ? (int)$_POST['id_pimpinan'] : NULL;
        $stmt = $conn->prepare("INSERT INTO section (nama_section, id_departemen, id_pimpinan) VALUES (?, ?, ?)");
        $stmt->bind_param("sii", $nama_section, $id_departemen, $id_pimpinan);
        $stmt->execute();
        $_SESSION['flash_message'] = "Section baru berhasil ditambahkan.";
    }

    // Update Direktorat
    elseif ($action == 'update_direktorat') {
        $id = (int)$_POST['edit_id'];
        $nama_direktorat = trim($_POST['edit_nama']);
        $id_pimpinan = !empty($_POST['edit_id_pimpinan_dir']) ? (int)$_POST['edit_id_pimpinan_dir'] : NULL;
        $stmt = $conn->prepare("UPDATE direktorat SET nama_direktorat = ?, id_pimpinan = ? WHERE id = ?");
        $stmt->bind_param("sii", $nama_direktorat, $id_pimpinan, $id);
        $stmt->execute();
        $_SESSION['flash_message'] = "Data Direktorat berhasil diperbarui.";
    }

    // Update Divisi
    elseif ($action == 'update_divisi') {
        $id = (int)$_POST['edit_id'];
        $nama_divisi = trim($_POST['edit_nama']);
        $id_pimpinan = !empty($_POST['edit_id_pimpinan_div']) ? (int)$_POST['edit_id_pimpinan_div'] : NULL;
        $stmt = $conn->prepare("UPDATE divisi SET nama_divisi = ?, id_pimpinan = ? WHERE id = ?");
        $stmt->bind_param("sii", $nama_divisi, $id_pimpinan, $id);
        $stmt->execute();
        $_SESSION['flash_message'] = "Data Divisi berhasil diperbarui.";
    }

    // Update Departemen
    elseif ($action == 'update_departemen') {
        $id = (int)$_POST['edit_id'];
        $nama_departemen = trim($_POST['edit_nama']);
        $id_pimpinan = !empty($_POST['edit_id_pimpinan_dept']) ? (int)$_POST['edit_id_pimpinan_dept'] : NULL;
        $stmt = $conn->prepare("UPDATE departemen SET nama_departemen = ?, id_pimpinan = ? WHERE id = ?");
        $stmt->bind_param("sii", $nama_departemen, $id_pimpinan, $id);
        $stmt->execute();
        $_SESSION['flash_message'] = "Data Departemen berhasil diperbarui.";
    }

    // Update Section
    elseif ($action == 'update_section') {
        $id = (int)$_POST['edit_id'];
        $nama_section = trim($_POST['edit_nama']);
        $id_pimpinan = !empty($_POST['edit_id_pimpinan_sec']) ? (int)$_POST['edit_id_pimpinan_sec'] : NULL;
        $stmt = $conn->prepare("UPDATE section SET nama_section = ?, id_pimpinan = ? WHERE id = ?");
        $stmt->bind_param("sii", $nama_section, $id_pimpinan, $id);
        $stmt->execute();
        $_SESSION['flash_message'] = "Data Section berhasil diperbarui.";
    }

    // Hapus
    elseif ($action == 'delete') {
        $type = $_POST['type'];
        $id = (int)$_POST['id'];

        if ($type == 'direktorat') $stmt = $conn->prepare("DELETE FROM direktorat WHERE id = ?");
        elseif ($type == 'divisi') $stmt = $conn->prepare("DELETE FROM divisi WHERE id = ?");
        elseif ($type == 'departemen') $stmt = $conn->prepare("DELETE FROM departemen WHERE id = ?");
        elseif ($type == 'section') $stmt = $conn->prepare("DELETE FROM section WHERE id = ?");

        if (isset($stmt)) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $_SESSION['flash_message'] = "Data berhasil dihapus.";
        }
    }

    header("Location: manage_struktur.php");
    exit;
}

// 4. Panggil header.php
require '../../includes/header.php';

// Cek flash message
$flash_message = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// --- AMBIL DATA UNTUK FORM & TABEL ---

// 1. Direktorat
$stmt_pimpinan_dir = $conn->query("SELECT id, nama_lengkap, nik FROM users WHERE tier = 'Direksi' ORDER BY nama_lengkap");
$direktorat_data = [];
$result_direktorat = $conn->query("SELECT d.*, u.nama_lengkap as nama_pimpinan FROM direktorat d LEFT JOIN users u ON d.id_pimpinan = u.id ORDER BY d.nama_direktorat");
while ($row = $result_direktorat->fetch_assoc()) $direktorat_data[] = $row;

// 2. Divisi
$stmt_pimpinan_div = $conn->query("SELECT id, nama_lengkap, nik FROM users WHERE tier IN ('Manager', 'Direksi') ORDER BY nama_lengkap"); // GM/Sr. Mgr
$divisi_data = [];
$result_divisi = $conn->query("SELECT d.*, u.nama_lengkap as nama_pimpinan, dir.nama_direktorat FROM divisi d LEFT JOIN users u ON d.id_pimpinan = u.id LEFT JOIN direktorat dir ON d.id_direktorat = dir.id ORDER BY dir.nama_direktorat, d.nama_divisi");
while ($row = $result_divisi->fetch_assoc()) $divisi_data[] = $row;

// 3. Departemen
$stmt_pimpinan_dept = $conn->query("SELECT id, nama_lengkap, nik FROM users WHERE tier = 'Manager' ORDER BY nama_lengkap");
$departemen_data = [];
$result_departemen = $conn->query("SELECT d.*, u.nama_lengkap as nama_pimpinan, divisi_tbl.nama_divisi FROM departemen d LEFT JOIN users u ON d.id_pimpinan = u.id LEFT JOIN divisi divisi_tbl ON d.id_divisi = divisi_tbl.id ORDER BY divisi_tbl.nama_divisi, d.nama_departemen");
while ($row = $result_departemen->fetch_assoc()) $departemen_data[] = $row;

// 4. Section
$stmt_pimpinan_sec = $conn->query("SELECT id, nama_lengkap, nik FROM users WHERE tier = 'Supervisor' ORDER BY nama_lengkap");
$section_data = [];
$result_section = $conn->query("SELECT s.*, u.nama_lengkap as nama_pimpinan, dept.nama_departemen FROM section s LEFT JOIN users u ON s.id_pimpinan = u.id LEFT JOIN departemen dept ON s.id_departemen = dept.id ORDER BY dept.nama_departemen, s.nama_section");
while ($row = $result_section->fetch_assoc()) $section_data[] = $row;

// Ambil data untuk dropdown (Add Forms)
$direktorat_list = $conn->query("SELECT id, nama_direktorat FROM direktorat ORDER BY nama_direktorat");
$divisi_list = $conn->query("SELECT id, nama_divisi FROM divisi ORDER BY nama_divisi");
$departemen_list = $conn->query("SELECT id, nama_departemen FROM departemen ORDER BY nama_departemen");

require '../../includes/sidebar.php';
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Manajemen Struktur Organisasi</h1>

    <?php if (!empty($flash_message)): ?>
        <div id="flashModal" class="modal-overlay">
            <div class="bg-white p-6 rounded-lg shadow-xl max-w-sm w-full mx-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-green-100 rounded-full p-2">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-800">Sukses!</h3>
                        <p class="mt-1 text-gray-600"><?php echo htmlspecialchars($flash_message); ?></p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button type="button" onclick="closeFlashModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition duration-200">
                        OK
                    </button>
                </div>
            </div>
        </div>
        <script>
            function closeFlashModal() {
                 const modal = document.getElementById('flashModal');
                 if(modal) modal.classList.add('hidden');
            }
            setTimeout(() => { closeFlashModal(); }, 3000);
        </script>
    <?php endif; ?>

    <div class="container mx-auto space-y-6">

        <div class="card">
            <div class="card-header"><h3 class="text-lg font-semibold">Direktorat</h3></div>
            <div class="card-content">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <form action="manage_struktur.php" method="POST" class="space-y-3 border-b md:border-b-0 md:border-r md:pr-6 pb-6 md:pb-0">
                        <input type="hidden" name="action" value="add_direktorat">
                        <h4 class="text-md font-semibold mb-2">Tambah Direktorat Baru</h4>
                        <div>
                            <label for="nama_direktorat" class="form-label">Nama Direktorat</label>
                            <input type="text" name="nama_direktorat" id="nama_direktorat" class="form-input uppercase-input" required>
                        </div>
                        <div>
                            <label for="id_pimpinan_dir" class="form-label">Pimpinan (Direktur)</label>
                            <select name="id_pimpinan" id="id_pimpinan_dir" class="form-input">
                                <option value="">-- Pilih Pimpinan --</option>
                                <?php $stmt_pimpinan_dir->data_seek(0); while($row = $stmt_pimpinan_dir->fetch_assoc()): ?>
                                    <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['nama_lengkap']); ?> (<?php echo htmlspecialchars($row['nik']); ?>)</option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn-primary-sm w-full">Tambah</button>
                    </form>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Pimpinan</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($direktorat_data as $row): ?>
                                <tr>
                                    <td class="px-3 py-2 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['nama_direktorat']); ?></td>
                                    <td class="px-3 py-2 text-sm text-gray-600">
                                        <?php echo $row['nama_pimpinan'] ? htmlspecialchars($row['nama_pimpinan']) : '<span class="text-gray-400 italic">Kosong/Vacant</span>'; ?>
                                    </td>
                                    <td class="px-3 py-2 text-sm whitespace-nowrap">
                                        <button type="button" class="text-blue-600 hover:text-blue-900"
                                                data-id="<?php echo $row['id']; ?>" data-nama="<?php echo htmlspecialchars($row['nama_direktorat']); ?>" data-pimpinan="<?php echo $row['id_pimpinan']; ?>" data-type="direktorat" onclick="openEditModal(this)">
                                            Edit
                                        </button>
                                        <form action="manage_struktur.php" method="POST" onsubmit="return confirm('Yakin ingin menghapus?');" class="inline-block ml-2">
                                            <input type="hidden" name="action" value="delete"> <input type="hidden" name="type" value="direktorat"> <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-900">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($direktorat_data)): ?><tr><td colspan="3" class="text-center py-4 text-gray-500">Belum ada data.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
             <div class="card-header"><h3 class="text-lg font-semibold">Divisi</h3></div>
             <div class="card-content">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <form action="manage_struktur.php" method="POST" class="space-y-3 border-b md:border-b-0 md:border-r md:pr-6 pb-6 md:pb-0">
                        <input type="hidden" name="action" value="add_divisi">
                        <h4 class="text-md font-semibold mb-2">Tambah Divisi Baru</h4>
                        <div>
                            <label for="id_direktorat_div" class="form-label">Dibawah Direktorat</label>
                            <select name="id_direktorat" id="id_direktorat_div" class="form-input" required>
                                <option value="">-- Pilih Direktorat --</option>
                                <?php $direktorat_list->data_seek(0); while($row = $direktorat_list->fetch_assoc()): ?>
                                    <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['nama_direktorat']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label for="nama_divisi" class="form-label">Nama Divisi</label>
                            <input type="text" name="nama_divisi" id="nama_divisi" class="form-input uppercase-input" required>
                        </div>
                        <div>
                            <label for="id_pimpinan_div" class="form-label">Pimpinan (GM/Sr. Manager)</label>
                            <select name="id_pimpinan" id="id_pimpinan_div" class="form-input">
                                <option value="">-- Pilih Pimpinan --</option>
                                <?php $stmt_pimpinan_div->data_seek(0); while($row = $stmt_pimpinan_div->fetch_assoc()): ?>
                                    <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['nama_lengkap']); ?> (<?php echo htmlspecialchars($row['nik']); ?>)</option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn-primary-sm w-full">Tambah</button>
                    </form>
                     <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Pimpinan</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($divisi_data as $row): ?>
                                <tr>
                                    <td class="px-3 py-2 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['nama_divisi']); ?><span class="block text-xs text-gray-500">(Dir: <?php echo htmlspecialchars($row['nama_direktorat'] ?? 'N/A'); ?>)</span></td>
                                    <td class="px-3 py-2 text-sm text-gray-600">
                                         <?php echo $row['nama_pimpinan'] ? htmlspecialchars($row['nama_pimpinan']) : '<span class="text-gray-400 italic">Kosong/Vacant</span>'; ?>
                                    </td>
                                    <td class="px-3 py-2 text-sm whitespace-nowrap">
                                        <button type="button" class="text-blue-600 hover:text-blue-900"
                                                data-id="<?php echo $row['id']; ?>" data-nama="<?php echo htmlspecialchars($row['nama_divisi']); ?>" data-pimpinan="<?php echo $row['id_pimpinan']; ?>" data-type="divisi" onclick="openEditModal(this)">
                                            Edit
                                        </button>
                                        <form action="manage_struktur.php" method="POST" onsubmit="return confirm('Yakin ingin menghapus?');" class="inline-block ml-2">
                                            <input type="hidden" name="action" value="delete"> <input type="hidden" name="type" value="divisi"> <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-900">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($divisi_data)): ?><tr><td colspan="3" class="text-center py-4 text-gray-500">Belum ada data.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
             </div>
        </div>

        <div class="card">
             <div class="card-header"><h3 class="text-lg font-semibold">Departemen</h3></div>
             <div class="card-content">
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <form action="manage_struktur.php" method="POST" class="space-y-3 border-b md:border-b-0 md:border-r md:pr-6 pb-6 md:pb-0">
                        <input type="hidden" name="action" value="add_departemen">
                        <h4 class="text-md font-semibold mb-2">Tambah Departemen Baru</h4>
                        <div>
                            <label for="id_divisi_dept" class="form-label">Dibawah Divisi</label>
                            <select name="id_divisi" id="id_divisi_dept" class="form-input" required>
                                <option value="">-- Pilih Divisi --</option>
                                <?php $divisi_list->data_seek(0); while($row = $divisi_list->fetch_assoc()): ?>
                                    <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['nama_divisi']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label for="nama_departemen" class="form-label">Nama Departemen</label>
                            <input type="text" name="nama_departemen" id="nama_departemen" class="form-input uppercase-input" required>
                        </div>
                        <div>
                            <label for="id_pimpinan_dept" class="form-label">Pimpinan (Manager)</label>
                            <select name="id_pimpinan" id="id_pimpinan_dept" class="form-input">
                                <option value="">-- Pilih Pimpinan --</option>
                                <?php $stmt_pimpinan_dept->data_seek(0); while($row = $stmt_pimpinan_dept->fetch_assoc()): ?>
                                    <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['nama_lengkap']); ?> (<?php echo htmlspecialchars($row['nik']); ?>)</option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn-primary-sm w-full">Tambah</button>
                    </form>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Pimpinan</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($departemen_data as $row): ?>
                                <tr>
                                    <td class="px-3 py-2 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['nama_departemen']); ?><span class="block text-xs text-gray-500">(Div: <?php echo htmlspecialchars($row['nama_divisi'] ?? 'N/A'); ?>)</span></td>
                                    <td class="px-3 py-2 text-sm text-gray-600">
                                         <?php echo $row['nama_pimpinan'] ? htmlspecialchars($row['nama_pimpinan']) : '<span class="text-gray-400 italic">Kosong/Vacant</span>'; ?>
                                    </td>
                                    <td class="px-3 py-2 text-sm whitespace-nowrap">
                                        <button type="button" class="text-blue-600 hover:text-blue-900"
                                                data-id="<?php echo $row['id']; ?>" data-nama="<?php echo htmlspecialchars($row['nama_departemen']); ?>" data-pimpinan="<?php echo $row['id_pimpinan']; ?>" data-type="departemen" onclick="openEditModal(this)">
                                            Edit
                                        </button>
                                        <form action="manage_struktur.php" method="POST" onsubmit="return confirm('Yakin ingin menghapus?');" class="inline-block ml-2">
                                            <input type="hidden" name="action" value="delete"> <input type="hidden" name="type" value="departemen"> <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-900">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($departemen_data)): ?><tr><td colspan="3" class="text-center py-4 text-gray-500">Belum ada data.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
             </div>
        </div>

        <div class="card">
             <div class="card-header"><h3 class="text-lg font-semibold">Section</h3></div>
             <div class="card-content">
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <form action="manage_struktur.php" method="POST" class="space-y-3 border-b md:border-b-0 md:border-r md:pr-6 pb-6 md:pb-0">
                        <input type="hidden" name="action" value="add_section">
                         <h4 class="text-md font-semibold mb-2">Tambah Section Baru</h4>
                        <div>
                            <label for="id_departemen_sec" class="form-label">Dibawah Departemen</label>
                            <select name="id_departemen" id="id_departemen_sec" class="form-input" required>
                                <option value="">-- Pilih Departemen --</option>
                                <?php $departemen_list->data_seek(0); while($row = $departemen_list->fetch_assoc()): ?>
                                    <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['nama_departemen']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label for="nama_section" class="form-label">Nama Section</label>
                            <input type="text" name="nama_section" id="nama_section" class="form-input uppercase-input" required>
                        </div>
                        <div>
                            <label for="id_pimpinan_sec" class="form-label">Pimpinan (Supervisor)</label>
                            <select name="id_pimpinan" id="id_pimpinan_sec" class="form-input">
                                <option value="">-- Pilih Pimpinan --</option>
                                <?php $stmt_pimpinan_sec->data_seek(0); while($row = $stmt_pimpinan_sec->fetch_assoc()): ?>
                                    <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['nama_lengkap']); ?> (<?php echo htmlspecialchars($row['nik']); ?>)</option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn-primary-sm w-full">Tambah</button>
                    </form>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nama</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Pimpinan</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($section_data as $row): ?>
                                <tr>
                                    <td class="px-3 py-2 text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($row['nama_section']); ?>
                                        <span class="block text-xs text-gray-500">(Dept: <?php echo htmlspecialchars($row['nama_departemen'] ?? 'N/A'); ?>)</span>
                                    </td>
                                    <td class="px-3 py-2 text-sm text-gray-600">
                                         <?php echo $row['nama_pimpinan'] ? htmlspecialchars($row['nama_pimpinan']) : '<span class="text-gray-400 italic">Kosong/Vacant</span>'; ?>
                                    </td>
                                    <td class="px-3 py-2 text-sm whitespace-nowrap">
                                        <button type="button" class="text-blue-600 hover:text-blue-900"
                                                data-id="<?php echo $row['id']; ?>" data-nama="<?php echo htmlspecialchars($row['nama_section']); ?>" data-pimpinan="<?php echo $row['id_pimpinan']; ?>" data-type="section" onclick="openEditModal(this)">
                                            Edit
                                        </button>
                                        <form action="manage_struktur.php" method="POST" onsubmit="return confirm('Yakin ingin menghapus?');" class="inline-block ml-2">
                                            <input type="hidden" name="action" value="delete"> <input type="hidden" name="type" value="section"> <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-900">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($section_data)): ?><tr><td colspan="3" class="text-center py-4 text-gray-500">Belum ada data.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                 </div>
            </div>
        </div>

    </div> <div id="editModal" class="modal-overlay hidden">
        <div class="bg-white p-6 rounded-lg shadow-xl max-w-lg w-full mx-4">
             <form id="editForm" action="manage_struktur.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" id="edit_action" value="">
                <input type="hidden" name="edit_id" id="edit_id" value="">

                <div class="flex justify-between items-center border-b pb-3 mb-4">
                    <h3 id="editModalTitle" class="text-xl font-semibold text-gray-800">Edit Data</h3>
                    <button type="button" onclick="closeModal('editModal')" class="text-gray-500 hover:text-gray-800 text-3xl">&times;</button>
                </div>

                <div>
                    <label for="edit_nama" class="form-label">Nama</label>
                    <input type="text" name="edit_nama" id="edit_nama" class="form-input uppercase-input" required>
                </div>

                <div id="wrapper_pimpinan_dir" class="hidden">
                    <label for="edit_id_pimpinan_dir" class="form-label">Pimpinan (Direktur)</label>
                    <select name="edit_id_pimpinan_dir" id="edit_id_pimpinan_dir" class="form-input">
                        <option value="">-- Pilih Pimpinan --</option>
                        <?php $stmt_pimpinan_dir->data_seek(0); while($row = $stmt_pimpinan_dir->fetch_assoc()): ?>
                            <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['nama_lengkap']); ?> (<?php echo htmlspecialchars($row['nik']); ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                 <div id="wrapper_pimpinan_div" class="hidden">
                    <label for="edit_id_pimpinan_div" class="form-label">Pimpinan (GM/Sr. Manager)</label>
                    <select name="edit_id_pimpinan_div" id="edit_id_pimpinan_div" class="form-input">
                         <option value="">-- Pilih Pimpinan --</option>
                         <?php $stmt_pimpinan_div->data_seek(0); while($row = $stmt_pimpinan_div->fetch_assoc()): ?>
                             <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['nama_lengkap']); ?> (<?php echo htmlspecialchars($row['nik']); ?>)</option>
                         <?php endwhile; ?>
                    </select>
                </div>
                 <div id="wrapper_pimpinan_dept" class="hidden">
                    <label for="edit_id_pimpinan_dept" class="form-label">Pimpinan (Manager)</label>
                    <select name="edit_id_pimpinan_dept" id="edit_id_pimpinan_dept" class="form-input">
                         <option value="">-- Pilih Pimpinan --</option>
                         <?php $stmt_pimpinan_dept->data_seek(0); while($row = $stmt_pimpinan_dept->fetch_assoc()): ?>
                             <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['nama_lengkap']); ?> (<?php echo htmlspecialchars($row['nik']); ?>)</option>
                         <?php endwhile; ?>
                    </select>
                </div>
                 <div id="wrapper_pimpinan_sec" class="hidden">
                    <label for="edit_id_pimpinan_sec" class="form-label">Pimpinan (Supervisor)</label>
                    <select name="edit_id_pimpinan_sec" id="edit_id_pimpinan_sec" class="form-input">
                         <option value="">-- Pilih Pimpinan --</option>
                         <?php $stmt_pimpinan_sec->data_seek(0); while($row = $stmt_pimpinan_sec->fetch_assoc()): ?>
                             <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['nama_lengkap']); ?> (<?php echo htmlspecialchars($row['nik']); ?>)</option>
                         <?php endwhile; ?>
                    </select>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                     <button type="button" onclick="closeModal('editModal')" class="btn-primary-sm btn-secondary">Batal</button>
                    <button type="submit" class="btn-primary-sm">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

</main>

<script>
    function openEditModal(button) {
        const data = button.dataset;
        const type = data.type;

        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_nama').value = data.nama;

        ['dir', 'div', 'dept', 'sec'].forEach(suffix => {
             const wrapper = document.getElementById(`wrapper_pimpinan_${suffix}`);
             const select = document.getElementById(`edit_id_pimpinan_${suffix}`);
             if (wrapper) wrapper.classList.add('hidden');
             if (select) select.value = '';
        });

        let title = 'Edit ';
        let action = 'update_';
        let wrapperId = '';
        let selectId = '';

        if (type === 'direktorat') { title += 'Direktorat'; action += 'direktorat'; wrapperId = 'wrapper_pimpinan_dir'; selectId = 'edit_id_pimpinan_dir'; }
        else if (type === 'divisi') { title += 'Divisi'; action += 'divisi'; wrapperId = 'wrapper_pimpinan_div'; selectId = 'edit_id_pimpinan_div'; }
        else if (type === 'departemen') { title += 'Departemen'; action += 'departemen'; wrapperId = 'wrapper_pimpinan_dept'; selectId = 'edit_id_pimpinan_dept'; }
        else if (type === 'section') { title += 'Section'; action += 'section'; wrapperId = 'wrapper_pimpinan_sec'; selectId = 'edit_id_pimpinan_sec'; }

        document.getElementById('editModalTitle').textContent = title;
        document.getElementById('edit_action').value = action;
        const wrapperToShow = document.getElementById(wrapperId);
        const selectToSet = document.getElementById(selectId);
        if (wrapperToShow && selectToSet) {
            wrapperToShow.classList.remove('hidden');
            selectToSet.value = data.pimpinan || '';
        }

        document.getElementById('editModal').classList.remove('hidden');
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.add('hidden');
    }
</script>


<?php
require '../../includes/footer.php';
?>