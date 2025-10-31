<?php
// pranata/modules/hr/hr_payroll_settings.php

// 1. Set variabel khusus untuk halaman ini
$page_title = "Setting Payroll (BPJS & PPh)";
$page_active = "hr_payroll_settings"; // Halaman baru

// 2. Panggil db.php dan lakukan Pengecekan Hak Akses
require '../../includes/db.php';

// Pengecekan Hak Akses
$app_akses = $_SESSION['app_akses'] ?? 'Karyawan';
$is_admin = ($app_akses == 'Admin');
$is_hr = ($app_akses == 'HR');
$admin_id = $_SESSION['user_id'];

if (!$is_admin && !$is_hr) {
    $_SESSION['flash_message'] = "Anda tidak memiliki hak akses untuk melihat halaman ini.";
    header("Location: ". BASE_URL. "/dashboard.php");
    exit;
}

// 3. Logika POST (Menyimpan data)
$errors = [];
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $conn->begin_transaction();
    try {
        $action = $_POST['action'] ?? '';

        // --- Aksi 1: Simpan Pengaturan BPJS & PPh (Key-Value) ---
        if ($action == 'save_main_settings') {
            $settings_to_save = [
                'bpjs_kes_perusahaan_pct' => $_POST['bpjs_kes_perusahaan_pct'],
                'bpjs_kes_karyawan_pct' => $_POST['bpjs_kes_karyawan_pct'],
                'bpjs_kes_max_upah' => str_replace('.', '', $_POST['bpjs_kes_max_upah']),
                'jht_perusahaan_pct' => $_POST['jht_perusahaan_pct'],
                'jht_karyawan_pct' => $_POST['jht_karyawan_pct'],
                'jp_perusahaan_pct' => $_POST['jp_perusahaan_pct'],
                'jp_karyawan_pct' => $_POST['jp_karyawan_pct'],
                'jp_max_upah' => str_replace('.', '', $_POST['jp_max_upah']),
                'jkk_perusahaan_pct' => $_POST['jkk_perusahaan_pct'],
                'jkm_perusahaan_pct' => $_POST['jkm_perusahaan_pct'],
                'jkp_perusahaan_pct' => $_POST['jkp_perusahaan_pct'],
                'pph21_metode' => $_POST['pph21_metode']
            ];
            
            $stmt_update = $conn->prepare("UPDATE payroll_settings SET setting_value = ? WHERE setting_key = ?");
            if (!$stmt_update) throw new Exception("Gagal prepare update settings: " . $conn->error);
            
            foreach ($settings_to_save as $key => $value) {
                $stmt_update->bind_param("ss", $value, $key);
                $stmt_update->execute();
            }
            $stmt_update->close();
            $success_message = "Pengaturan BPJS & PPh 21 utama berhasil disimpan.";
        }
        
        // --- Aksi 2: Simpan Tabel PTKP ---
        elseif ($action == 'save_ptkp') {
            $ptkp_ids = $_POST['ptkp_id'] ?? [];
            $ptkp_nilais = $_POST['ptkp_nilai'] ?? [];
            
            $stmt_update_ptkp = $conn->prepare("UPDATE payroll_settings_ptkp SET nilai_ptkp_tahunan = ? WHERE id = ?");
            if (!$stmt_update_ptkp) throw new Exception("Gagal prepare update PTKP: " . $conn->error);
            
            foreach ($ptkp_ids as $index => $id) {
                $id = (int)$id;
                $nilai = (int)str_replace('.', '', $ptkp_nilais[$index] ?? 0);
                $stmt_update_ptkp->bind_param("ii", $nilai, $id);
                $stmt_update_ptkp->execute();
            }
            $stmt_update_ptkp->close();
            $success_message = "Tabel PTKP berhasil diperbarui.";
        }
        
        // --- Aksi 3: Tambah Baris TER ---
        elseif ($action == 'add_ter') {
            $kategori = $_POST['ter_kategori'];
            $bruto_min = (int)str_replace('.', '', $_POST['ter_bruto_min']);
            $bruto_max = (int)str_replace('.', '', $_POST['ter_bruto_max']);
            $tarif = (float)str_replace(',', '.', $_POST['ter_tarif']);
            
            if (empty($kategori) || $bruto_max <= 0 || $tarif < 0) {
                throw new Exception("Kategori, Penghasilan Bruto Max, dan Tarif wajib diisi.");
            }
            if ($bruto_max < $bruto_min) {
                throw new Exception("Bruto Max tidak boleh lebih kecil dari Bruto Min.");
            }
            
            $stmt_insert_ter = $conn->prepare(
                "INSERT INTO payroll_settings_ter (kategori, penghasilan_bruto_min, penghasilan_bruto_max, tarif_ter)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt_insert_ter->bind_param("siid", $kategori, $bruto_min, $bruto_max, $tarif);
            $stmt_insert_ter->execute();
            $stmt_insert_ter->close();
            $success_message = "Baris Tarif Efektif Rata-rata (TER) berhasil ditambahkan.";
        }
        
        // --- Aksi 4: Hapus Baris TER ---
        elseif ($action == 'delete_ter') {
            $ter_id = (int)($_POST['ter_id_hapus'] ?? 0);
            if ($ter_id <= 0) throw new Exception("ID TER tidak valid.");
            
            $stmt_delete_ter = $conn->prepare("DELETE FROM payroll_settings_ter WHERE id = ?");
            $stmt_delete_ter->bind_param("i", $ter_id);
            $stmt_delete_ter->execute();
            $stmt_delete_ter->close();
            $success_message = "Baris TER berhasil dihapus.";
        }

        $conn->commit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $errors[] = $e->getMessage();
    }
}

// 4. Logika GET (Ambil data untuk tampilan)
// 4.1. Ambil Pengaturan Utama
$settings = [];
$sql_settings = "SELECT setting_key, setting_value FROM payroll_settings";
$result_settings = $conn->query($sql_settings);
if ($result_settings) {
    while ($row = $result_settings->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// 4.2. Ambil Data PTKP
$ptkp_data = [];
$sql_ptkp = "SELECT * FROM payroll_settings_ptkp ORDER BY nilai_ptkp_tahunan ASC";
$result_ptkp = $conn->query($sql_ptkp);
if ($result_ptkp) {
    $ptkp_data = $result_ptkp->fetch_all(MYSQLI_ASSOC);
}

// 4.3. Ambil Data TER
$ter_data_a = [];
$ter_data_b = [];
$ter_data_c = [];
$sql_ter = "SELECT * FROM payroll_settings_ter ORDER BY kategori, penghasilan_bruto_min ASC";
$result_ter = $conn->query($sql_ter);
if ($result_ter) {
    while ($row = $result_ter->fetch_assoc()) {
        if ($row['kategori'] == 'A') $ter_data_a[] = $row;
        elseif ($row['kategori'] == 'B') $ter_data_b[] = $row;
        elseif ($row['kategori'] == 'C') $ter_data_c[] = $row;
    }
}


// 5. Panggil header.php
require '../../includes/header.php';

// 6. Panggil sidebar.php
require '../../includes/sidebar.php';
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Setting Payroll (BPJS & PPh 21)</h1>

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

    <div class="card mb-6">
        <form action="hr_payroll_settings.php" method="POST" class="card-content space-y-5">
            <input type="hidden" name="action" value="save_main_settings">
            <h3 class="text-xl font-semibold text-gray-800 border-b pb-2">Pengaturan BPJS & PPh 21</h3>
            
            <div class="p-4 border rounded-md">
                <h4 class="font-semibold text-gray-700">BPJS Kesehatan</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-2">
                    <div>
                        <label for="bpjs_kes_perusahaan_pct" class="form-label">Tanggungan Perusahaan (%)</label>
                        <input type="number" step="0.01" id="bpjs_kes_perusahaan_pct" name="bpjs_kes_perusahaan_pct" class="form-input" value="<?php echo htmlspecialchars($settings['bpjs_kes_perusahaan_pct'] ?? '4'); ?>">
                    </div>
                    <div>
                        <label for="bpjs_kes_karyawan_pct" class="form-label">Tanggungan Karyawan (%)</label>
                        <input type="number" step="0.01" id="bpjs_kes_karyawan_pct" name="bpjs_kes_karyawan_pct" class="form-input" value="<?php echo htmlspecialchars($settings['bpjs_kes_karyawan_pct'] ?? '1'); ?>">
                    </div>
                    <div>
                        <label for="bpjs_kes_max_upah" class="form-label">Batas Upah Maksimal (Rp)</label>
                        <input type="text" id="bpjs_kes_max_upah" name="bpjs_kes_max_upah" class="form-input input-rupiah" value="<?php echo htmlspecialchars(number_format($settings['bpjs_kes_max_upah'] ?? 12000000, 0, ',', '.')); ?>">
                    </div>
                </div>
            </div>

            <div class="p-4 border rounded-md">
                <h4 class="font-semibold text-gray-700">BPJS Ketenagakerjaan</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4 mt-2">
                    <div>
                        <label for="jht_perusahaan_pct" class="form-label">JHT Perusahaan (%)</label>
                        <input type="number" step="0.01" id="jht_perusahaan_pct" name="jht_perusahaan_pct" class="form-input" value="<?php echo htmlspecialchars($settings['jht_perusahaan_pct'] ?? '3.7'); ?>">
                    </div>
                    <div>
                        <label for="jht_karyawan_pct" class="form-label">JHT Karyawan (%)</label>
                        <input type="number" step="0.01" id="jht_karyawan_pct" name="jht_karyawan_pct" class="form-input" value="<?php echo htmlspecialchars($settings['jht_karyawan_pct'] ?? '2'); ?>">
                    </div>
                    <div>
                        <label for="jp_perusahaan_pct" class="form-label">JP Perusahaan (%)</label>
                        <input type="number" step="0.01" id="jp_perusahaan_pct" name="jp_perusahaan_pct" class="form-input" value="<?php echo htmlspecialchars($settings['jp_perusahaan_pct'] ?? '2'); ?>">
                    </div>
                    <div>
                        <label for="jp_karyawan_pct" class="form-label">JP Karyawan (%)</label>
                        <input type="number" step="0.01" id="jp_karyawan_pct" name="jp_karyawan_pct" class="form-input" value="<?php echo htmlspecialchars($settings['jp_karyawan_pct'] ?? '1'); ?>">
                    </div>
                    <div>
                        <label for="jp_max_upah" class="form-label">Batas Upah JP (Rp)</label>
                        <input type="text" id="jp_max_upah" name="jp_max_upah" class="form-input input-rupiah" value="<?php echo htmlspecialchars(number_format($settings['jp_max_upah'] ?? 6000000, 0, ',', '.')); ?>">
                    </div>
                    <div>
                        <label for="jkk_perusahaan_pct" class="form-label">JKK Perusahaan (%)</label>
                        <input type="number" step="0.01" id="jkk_perusahaan_pct" name="jkk_perusahaan_pct" class="form-input" value="<?php echo htmlspecialchars($settings['jkk_perusahaan_pct'] ?? '0.24'); ?>">
                    </div>
                    <div>
                        <label for="jkm_perusahaan_pct" class="form-label">JKM Perusahaan (%)</label>
                        <input type="number" step="0.01" id="jkm_perusahaan_pct" name="jkm_perusahaan_pct" class="form-input" value="<?php echo htmlspecialchars($settings['jkm_perusahaan_pct'] ?? '0.3'); ?>">
                    </div>
                    <div>
                        <label for="jkp_perusahaan_pct" class="form-label">JKP Perusahaan (%)</label>
                        <input type="number" step="0.01" id="jkp_perusahaan_pct" name="jkp_perusahaan_pct" class="form-input" value="<?php echo htmlspecialchars($settings['jkp_perusahaan_pct'] ?? '0.46'); ?>">
                    </div>
                </div>
            </div>
            
            <div class="p-4 border rounded-md">
                <h4 class="font-semibold text-gray-700">PPh 21</h4>
                <div>
                    <label for="pph21_metode" class="form-label">Metode Perhitungan</label>
                    <select id="pph21_metode" name="pph21_metode" class="form-input">
                        <option value="GROSS" <?php if (($settings['pph21_metode'] ?? 'GROSS') == 'GROSS') echo 'selected'; ?>>GROSS (Dipotong dari Gaji)</option>
                        <option value="GROSS_UP" <?php if (($settings['pph21_metode'] ?? '') == 'GROSS_UP') echo 'selected'; ?>>GROSS-UP (Ditunjang Perusahaan)</option>
                        <option value="NETT" <?php if (($settings['pph21_metode'] ?? '') == 'NETT') echo 'selected'; ?>>NETT (Ditanggung Perusahaan)</option>
                    </select>
                </div>
            </div>

            <div class="pt-4 border-t flex justify-end">
                <button type="submit" class="btn-primary-sm bg-blue-600 hover:bg-blue-700">
                    Simpan Pengaturan Utama
                </button>
            </div>
        </form>
    </div>

    <div class="card mb-6">
        <form action="hr_payroll_settings.php" method="POST">
            <input type="hidden" name="action" value="save_ptkp">
            <div class="card-header">
                <h3 class="text-xl font-semibold text-gray-800">Pengaturan PTKP (Penghasilan Tidak Kena Pajak)</h3>
            </div>
            <div class="card-content">
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Kode PTKP</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Deskripsi</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nilai PTKP Tahunan (Rp)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($ptkp_data as $ptkp): ?>
                            <tr>
                                <td class="px-4 py-2 font-medium"><?php echo htmlspecialchars($ptkp['kode_ptkp']); ?></td>
                                <td class="px-4 py-2"><?php echo htmlspecialchars($ptkp['deskripsi']); ?></td>
                                <td class="px-4 py-2">
                                    <input type="hidden" name="ptkp_id[]" value="<?php echo $ptkp['id']; ?>">
                                    <input type="text" name="ptkp_nilai[]" class="form-input input-rupiah" value="<?php echo htmlspecialchars(number_format($ptkp['nilai_ptkp_tahunan'], 0, ',', '.')); ?>">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-gray-50 px-6 py-4 flex justify-end">
                <button type="submit" class="btn-primary-sm bg-blue-600 hover:bg-blue-700">
                    Simpan Perubahan PTKP
                </button>
            </div>
        </form>
    </div>

    <div class="card mb-6">
        <div class="card-header">
            <h3 class="text-xl font-semibold text-gray-800">Pengaturan Tarif Efektif Rata-rata (TER) PPh 21</h3>
        </div>
        <div class="card-content">
            <form action="hr_payroll_settings.php" method="POST" class="mb-6 p-4 border rounded-md bg-gray-50 space-y-3">
                <input type="hidden" name="action" value="add_ter">
                <h4 class="font-semibold text-gray-700">Tambah Baris Tarif TER Baru</h4>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="ter_kategori" class="form-label">Kategori <span class="text-red-500">*</span></label>
                        <select id="ter_kategori" name="ter_kategori" class="form-input" required>
                            <option value="A">Kategori A (PTKP TK/0, TK/1, K/0)</option>
                            <option value="B">Kategori B (PTKP TK/2, TK/3, K/1, K/2)</option>
                            <option value="C">Kategori C (PTKP K/3)</option>
                        </select>
                    </div>
                    <div>
                        <label for="ter_bruto_min" class="form-label">Bruto Min (Rp) <span class="text-red-500">*</span></label>
                        <input type="text" id="ter_bruto_min" name="ter_bruto_min" class="form-input input-rupiah" value="0" required>
                    </div>
                    <div>
                        <label for="ter_bruto_max" class="form-label">Bruto Max (Rp) <span class="text-red-500">*</span></label>
                        <input type="text" id="ter_bruto_max" name="ter_bruto_max" class="form-input input-rupiah" required>
                    </div>
                    <div>
                        <label for="ter_tarif" class="form-label">Tarif (%) <span class="text-red-500">*</span></label>
                        <input type="text" id="ter_tarif" name="ter_tarif" class="form-input" placeholder="Contoh: 1.25" required>
                    </div>
                </div>
                <div class="text-right">
                    <button type="submit" class="btn-primary-sm bg-green-600 hover:bg-green-700">
                        + Tambah Baris
                    </button>
                </div>
            </form>

            <div class="border rounded-md">
                <div class="flex border-b">
                    <button type="button" class="tab-button active-tab" onclick="openTab(event, 'TER-A')">Kategori A</button>
                    <button type="button" class="tab-button" onclick="openTab(event, 'TER-B')">Kategori B</button>
                    <button type="button" class="tab-button" onclick="openTab(event, 'TER-C')">Kategori C</button>
                </div>
                
                <div id="TER-A" class="tab-content p-4">
                    <?php echo renderTERTable($ter_data_a); ?>
                </div>
                <div id="TER-B" class="tab-content p-4 hidden">
                    <?php echo renderTERTable($ter_data_b); ?>
                </div>
                <div id="TER-C" class="tab-content p-4 hidden">
                    <?php echo renderTERTable($ter_data_c); ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
// Helper function untuk render tabel TER (agar tidak duplikat kode)
function renderTERTable($data) {
    if (empty($data)) {
        return '<p class="text-gray-500 text-center py-4">Belum ada data tarif untuk kategori ini.</p>';
    }
    
    $html = '<div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200">';
    $html .= '<thead class="bg-gray-50"><tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Penghasilan Bruto Min (Rp)</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Penghasilan Bruto Max (Rp)</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tarif Efektif (%)</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
              </tr></thead><tbody class="bg-white divide-y divide-gray-200">';
              
    foreach ($data as $ter) {
        $html .= '<tr>';
        $html .= '<td class="px-4 py-2">' . htmlspecialchars(number_format($ter['penghasilan_bruto_min'], 0, ',', '.')) . '</td>';
        $html .= '<td class="px-4 py-2">' . htmlspecialchars(number_format($ter['penghasilan_bruto_max'], 0, ',', '.')) . '</td>';
        $html .= '<td class="px-4 py-2">' . htmlspecialchars(number_format($ter['tarif_ter'], 2, ',', '.')) . ' %</td>';
        $html .= '<td class="px-4 py-2">
                    <form action="hr_payroll_settings.php" method="POST" onsubmit="return confirm(\'Yakin ingin menghapus baris ini?\');">
                        <input type="hidden" name="action" value="delete_ter">
                        <input type="hidden" name="ter_id_hapus" value="' . $ter['id'] . '">
                        <button type="submit" class="text-red-600 hover:text-red-800 text-xs">Hapus</button>
                    </form>
                  </td>';
        $html .= '</tr>';
    }
    
    $html .= '</tbody></table></div>';
    return $html;
}
?>

<?php
// 8. Panggil footer
require '../../includes/footer.php';
?>

<style>
    .tab-button {
        padding: 0.75rem 1.25rem;
        font-weight: 500;
        color: #6b7280; /* gray-500 */
        border-bottom: 2px solid transparent;
        margin-bottom: -1px; /* Agar border-bottom menimpa border-b */
    }
    .tab-button:hover {
        color: #1f2937; /* gray-800 */
    }
    .tab-button.active-tab {
        color: #3b82f6; /* blue-600 */
        border-color: #3b82f6; /* blue-600 */
    }
</style>

<script>
    // --- Script untuk Format Rupiah ---
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
    
    document.querySelectorAll('.input-rupiah').forEach(input => {
        input.addEventListener('keyup', function() { formatRupiah(this); });
        formatRupiah(input); // Format saat load
    });
    
    // --- Script untuk Tabs ---
    function openTab(event, tabID) {
        // Sembunyikan semua konten tab
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.add('hidden');
        });
        
        // Hapus kelas 'active-tab' dari semua tombol
        document.querySelectorAll('.tab-button').forEach(button => {
            button.classList.remove('active-tab');
        });
        
        // Tampilkan tab yang diklik dan tandai tombolnya
        document.getElementById(tabID).classList.remove('hidden');
        event.currentTarget.classList.add('active-tab');
    }
</script>