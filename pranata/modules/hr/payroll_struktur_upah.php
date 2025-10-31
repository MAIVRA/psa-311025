<?php
// pranata/modules/hr/payroll_struktur_upah.php

// 1. Set variabel khusus untuk halaman ini
$page_title = "Struktur & Skala Upah";
$page_active = "hr_struktur_upah"; // Sesuai dengan sidebar.php

// 2. Panggil db.php dan lakukan Pengecekan Hak Akses
require '../../includes/db.php';

// Pengecekan Hak Akses
$app_akses = $_SESSION['app_akses'] ?? 'Karyawan';
$is_admin = ($app_akses == 'Admin');
$is_hr = ($app_akses == 'HR');

if (!$is_admin && !$is_hr) {
    $_SESSION['flash_message'] = "Anda tidak memiliki hak akses untuk melihat halaman ini.";
    header("Location: ". BASE_URL. "/dashboard.php");
    exit;
}

// 3. Logika POST (Tambah / Edit / Hapus)
$errors = [];
$success_message = '';
$user_id = $_SESSION['user_id'];

// [PERUBAHAN] Daftar Jabatan dari enum tabel users (tier), Pemegang Saham DIHAPUS
$jabatan_enum_list = ['Staf','Supervisor','Manager','Direksi','Komisaris'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- Logika Tambah / Edit ---
    if (isset($_POST['simpan_struktur'])) {
        $struktur_id = $_POST['struktur_id'] ?? null; // ID untuk edit
        $jabatan = $_POST['jabatan'] ?? null;
        $level = trim($_POST['level'] ?? '');
        $grade = trim($_POST['grade'] ?? '');
        $gaji_pokok_min = (int) str_replace('.', '', $_POST['gaji_pokok_min'] ?? 0);
        $gaji_pokok_max = (int) str_replace('.', '', $_POST['gaji_pokok_max'] ?? 0);
        $tunjangan_tetap = (int) str_replace('.', '', $_POST['tunjangan_tetap'] ?? 0);
        $keterangan = trim($_POST['keterangan'] ?? '');

        // Validasi
        if (empty($jabatan) || $gaji_pokok_min < 0 || $gaji_pokok_max < 0 || $tunjangan_tetap < 0) {
            $errors[] = "Jabatan, Gaji Pokok Min/Max, dan Tunjangan Tetap wajib diisi.";
        } elseif ($gaji_pokok_max < $gaji_pokok_min) {
            $errors[] = "Gaji Pokok Max tidak boleh lebih kecil dari Gaji Pokok Min.";
        } elseif (!in_array($jabatan, $jabatan_enum_list)) {
            // Validasi ini sekarang juga akan menolak 'Pemegang Saham'
            $errors[] = "Jabatan tidak valid.";
        } else {
            
            if (empty($struktur_id)) {
                // --- Mode INSERT ---
                $sql = "INSERT INTO payroll_struktur_upah (jabatan, level, grade, gaji_pokok_min, gaji_pokok_max, tunjangan_tetap, keterangan) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssiiis", $jabatan, $level, $grade, $gaji_pokok_min, $gaji_pokok_max, $tunjangan_tetap, $keterangan);
                $action_text = "ditambahkan";
            } else {
                // --- Mode UPDATE ---
                $sql = "UPDATE payroll_struktur_upah 
                        SET jabatan = ?, level = ?, grade = ?, gaji_pokok_min = ?, gaji_pokok_max = ?, tunjangan_tetap = ?, keterangan = ?
                        WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssiiisi", $jabatan, $level, $grade, $gaji_pokok_min, $gaji_pokok_max, $tunjangan_tetap, $keterangan, $struktur_id);
                $action_text = "diperbarui";
            }
            
            if ($stmt->execute()) {
                $success_message = "Struktur upah berhasil ". $action_text. ".";
            } else {
                // Cek error duplikat
                if ($conn->errno == 1062) {
                    $errors[] = "Gagal: Kombinasi Jabatan, Level, dan Grade sudah ada.";
                } else {
                    $errors[] = "Gagal menyimpan data: ". $stmt->error;
                }
            }
            $stmt->close();
        }
    }
    
    // --- Logika Hapus ---
    if (isset($_POST['hapus_struktur'])) {
        $struktur_id = $_POST['struktur_id_hapus'] ?? 0;
        
        $stmt_delete = $conn->prepare("DELETE FROM payroll_struktur_upah WHERE id = ?");
        $stmt_delete->bind_param("i", $struktur_id);
        
        if ($stmt_delete->execute()) {
            if ($stmt_delete->affected_rows > 0) {
                $success_message = "Struktur upah berhasil dihapus.";
            } else {
                $errors[] = "Gagal menghapus: Data tidak ditemukan.";
            }
        } else {
            $errors[] = "Error saat menghapus: ". $stmt_delete->error;
        }
        $stmt_delete->close();
    }
}

// 4. Logika GET (Ambil data untuk tabel)
$daftar_struktur = [];
$sql_get = "SELECT * FROM payroll_struktur_upah ORDER BY jabatan, level, grade ASC";
$result_get = $conn->query($sql_get);
if ($result_get) {
    while ($row = $result_get->fetch_assoc()) {
        $daftar_struktur[] = $row;
    }
} else {
    $errors[] = "Gagal mengambil data: " . $conn->error;
}

// 5. Panggil header.php
require '../../includes/header.php';

// 6. Panggil sidebar.php
require '../../includes/sidebar.php';
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Struktur & Skala Upah</h1>
        <button
            type="button"
            onclick="openModal('strukturModal', 'Tambah Struktur & Skala Upah')"
            class="btn-primary-sm bg-blue-600 hover:bg-blue-700 flex items-center">
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Tambah Struktur
        </button>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4 max-w-full" role="alert">
            <strong class="font-bold">Error!</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($success_message)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative mb-4 max-w-full" role="alert">
            <strong class="font-bold">Sukses!</strong>
            <span class="block sm:inline"><?php echo htmlspecialchars($success_message); ?></span>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-content">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Master Struktur & Skala Upah</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jabatan</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Level</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Gaji Pokok Min</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Gaji Pokok Max</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Tunjangan Tetap</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Keterangan</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($daftar_struktur)): ?>
                            <tr>
                                <td colspan="8" class="px-4 py-4 text-center text-sm text-gray-500">
                                    Belum ada data struktur upah. Silakan tambahkan.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($daftar_struktur as $data): ?>
                                <tr>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($data['jabatan']); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($data['level'] ?: '-'); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($data['grade'] ?: '-'); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700 text-right">Rp <?php echo number_format($data['gaji_pokok_min'], 0, ',', '.'); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700 text-right">Rp <?php echo number_format($data['gaji_pokok_max'], 0, ',', '.'); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700 text-right">Rp <?php echo number_format($data['tunjangan_tetap'], 0, ',', '.'); ?></td>
                                    <td class="px-4 py-4 text-sm text-gray-700 max-w-xs truncate"><?php echo htmlspecialchars($data['keterangan'] ?: '-'); ?></td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm space-x-2">
                                        <button
                                            type="button"
                                            onclick="editModal(this)"
                                            data-json="<?php echo htmlspecialchars(json_encode($data), ENT_QUOTES, 'UTF-8'); ?>"
                                            class="text-blue-600 hover:text-blue-900 text-xs">
                                            Edit
                                        </button>
                                        <form action="payroll_struktur_upah.php" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data ini?');" class="inline">
                                            <input type="hidden" name="struktur_id_hapus" value="<?php echo $data['id']; ?>">
                                            <button type="submit" name="hapus_struktur" class="text-red-600 hover:text-red-900 text-xs">
                                                Hapus
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<div id="strukturModal" class="modal-overlay hidden">
    <div class="bg-white p-6 rounded-lg shadow-xl max-w-2xl w-full mx-4">
        <div class="flex justify-between items-center border-b pb-3 mb-4">
            <h3 class="text-xl font-semibold text-gray-800" id="modalTitle">Tambah Struktur & Skala Upah</h3>
            <button onclick="closeModal('strukturModal')" class="text-gray-500 hover:text-gray-800">&times;</button>
        </div>
        
        <form id="modalForm" action="payroll_struktur_upah.php" method="POST" class="space-y-4">
            <input type="hidden" name="struktur_id" id="struktur_id">
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="jabatan" class="form-label">Jabatan (Tier) <span class="text-red-500">*</span></label>
                    <select id="jabatan" name="jabatan" class="form-input" required>
                        <option value="">-- Pilih Jabatan --</option>
                        <?php foreach ($jabatan_enum_list as $jabatan_opt): ?>
                            <option value="<?php echo $jabatan_opt; ?>"><?php echo $jabatan_opt; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="level" class="form-label">Level</label>
                    <input type="text" id="level" name="level" class="form-input" placeholder="Contoh: III">
                </div>
                <div>
                    <label for="grade" class="form-label">Grade</label>
                    <input type="text" id="grade" name="grade" class="form-input" placeholder="Contoh: A">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                 <div>
                    <label for="gaji_pokok_min" class="form-label">Gaji Pokok Min <span class="text-red-500">*</span></label>
                    <input type="text" id="gaji_pokok_min" name="gaji_pokok_min" class="form-input" placeholder="Contoh: 5.000.000" onkeyup="formatRupiah(this)" required>
                </div>
                 <div>
                    <label for="gaji_pokok_max" class="form-label">Gaji Pokok Max <span class="text-red-500">*</span></label>
                    <input type="text" id="gaji_pokok_max" name="gaji_pokok_max" class="form-input" placeholder="Contoh: 8.000.000" onkeyup="formatRupiah(this)" required>
                </div>
            </div>
            
            <div>
                <label for="tunjangan_tetap" class="form-label">Tunjangan Tetap <span class="text-red-500">*</span></label>
                <input type="text" id="tunjangan_tetap" name="tunjangan_tetap" class="form-input" placeholder="Contoh: 1.000.000" onkeyup="formatRupiah(this)" required>
            </div>
            
            <div>
                 <label for="keterangan" class="form-label">Keterangan</label>
                 <textarea id="keterangan" name="keterangan" rows="3" class="form-input" placeholder="Keterangan tambahan..."></textarea>
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <button
                    type="button"
                    onclick="closeModal('strukturModal')"
                    class="btn-primary-sm btn-secondary">
                    Batal
                </button>
                <button
                    type="submit"
                    name="simpan_struktur"
                    class="btn-primary-sm bg-blue-600 hover:bg-blue-700">
                    Simpan Data
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// 8. Panggil footer
require '../../includes/footer.php';
?>

<script>
    const modal = document.getElementById('strukturModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalForm = document.getElementById('modalForm');
    
    function formatRupiah(input) {
        let value = input.value.replace(/[^,\d]/g, '').toString();
        let split = value.split(',');
        let sisa = split[0].length % 3;
        let rupiah = split[0].substr(0, sisa);
        let ribuan = split[0].substr(sisa).match(/\d{3}/gi);

        if (ribuan) {
            let separator = sisa ? '.' : '';
            rupiah += separator + ribuan.join('.');
        }
        
        rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
        input.value = rupiah;
    }

    function resetForm() {
        modalForm.reset();
        document.getElementById('struktur_id').value = '';
    }

    function openModal(modalId, title) {
        const modal = document.getElementById(modalId);
        if (modal) {
            resetForm();
            modalTitle.innerText = title;
            modal.classList.remove('hidden');
        }
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('hidden');
            resetForm();
        }
    }
    
    function editModal(button) {
        const data = JSON.parse(button.dataset.json);
        
        resetForm();
        modalTitle.innerText = 'Edit Struktur & Skala Upah';
        
        document.getElementById('struktur_id').value = data.id;
        document.getElementById('jabatan').value = data.jabatan;
        document.getElementById('level').value = data.level;
        document.getElementById('grade').value = data.grade;
        document.getElementById('keterangan').value = data.keterangan;
        
        const gajiMinInput = document.getElementById('gaji_pokok_min');
        gajiMinInput.value = data.gaji_pokok_min;
        formatRupiah(gajiMinInput);
        
        const gajiMaxInput = document.getElementById('gaji_pokok_max');
        gajiMaxInput.value = data.gaji_pokok_max;
        formatRupiah(gajiMaxInput);
        
        const tunjanganInput = document.getElementById('tunjangan_tetap');
        tunjanganInput.value = data.tunjangan_tetap;
        formatRupiah(tunjanganInput);
        
        const modal = document.getElementById('strukturModal');
        modal.classList.remove('hidden');
    }
    
    document.querySelectorAll('.modal-overlay').forEach(modalEl => {
        modalEl.addEventListener('click', function(event) {
            if (event.target === modalEl) {
                closeModal(modalEl.id);
            }
        });
    });
</script>