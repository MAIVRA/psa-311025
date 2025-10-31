<?php
// pranata/modules/hr/payroll_master_gaji.php

// 1. Set variabel khusus untuk halaman ini
$page_title = "Master Data Gaji Karyawan";
$page_active = "hr_master_gaji";

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

// 3. Logika POST (Hanya untuk Simpan/Update Gaji)
$errors = [];
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['simpan_gaji'])) {
    $user_id = $_POST['user_id_gaji'] ?? null;
    $gaji_pokok = (int) str_replace('.', '', $_POST['gaji_pokok'] ?? 0);
    $tunj_jabatan = (int) str_replace('.', '', $_POST['tunj_jabatan'] ?? 0);
    $tunj_kesehatan = (int) str_replace('.', '', $_POST['tunj_kesehatan'] ?? 0);
    $tunj_transport = (int) str_replace('.', '', $_POST['tunj_transport'] ?? 0);
    $tunj_makan = (int) str_replace('.', '', $_POST['tunj_makan'] ?? 0);
    $tunj_rumah = (int) str_replace('.', '', $_POST['tunj_rumah'] ?? 0);
    $tunj_pendidikan = (int) str_replace('.', '', $_POST['tunj_pendidikan'] ?? 0);
    $tunj_komunikasi = (int) str_replace('.', '', $_POST['tunj_komunikasi'] ?? 0);
    // [PERUBAHAN] Ambil status_ptkp
    $status_ptkp = $_POST['status_ptkp'] ?? NULL;
    $pot_pph = isset($_POST['pot_pph']) ? 1 : 0;
    $pot_bpjs_kesehatan = isset($_POST['pot_bpjs_kesehatan']) ? 1 : 0;
    $pot_bpjs_ketenagakerjaan = isset($_POST['pot_bpjs_ketenagakerjaan']) ? 1 : 0;

    if (empty($user_id)) {
        $errors[] = "Karyawan wajib dipilih.";
    } elseif (empty($status_ptkp)) {
         $errors[] = "Status PTKP wajib dipilih.";
    } else {
        // Logika INSERT atau UPDATE (UPSERT)
        // [PERUBAHAN] Tambah status_ptkp di query
        $sql_upsert = "
            INSERT INTO payroll_master_gaji (
                user_id, gaji_pokok, tunj_jabatan, tunj_kesehatan, 
                tunj_transport, tunj_makan, tunj_rumah, tunj_pendidikan, tunj_komunikasi, 
                status_ptkp, pot_pph, pot_bpjs_kesehatan, pot_bpjs_ketenagakerjaan, updated_by_id
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
            ON DUPLICATE KEY UPDATE
                gaji_pokok = VALUES(gaji_pokok),
                tunj_jabatan = VALUES(tunj_jabatan),
                tunj_kesehatan = VALUES(tunj_kesehatan),
                tunj_transport = VALUES(tunj_transport),
                tunj_makan = VALUES(tunj_makan),
                tunj_rumah = VALUES(tunj_rumah),
                tunj_pendidikan = VALUES(tunj_pendidikan),
                tunj_komunikasi = VALUES(tunj_komunikasi),
                status_ptkp = VALUES(status_ptkp),
                pot_pph = VALUES(pot_pph),
                pot_bpjs_kesehatan = VALUES(pot_bpjs_kesehatan),
                pot_bpjs_ketenagakerjaan = VALUES(pot_bpjs_ketenagakerjaan),
                updated_by_id = VALUES(updated_by_id)
        ";
        
        $stmt = $conn->prepare($sql_upsert);
        // [PERUBAHAN] Update bind_param (tambah 's' untuk status_ptkp)
        $stmt->bind_param(
            "iiiiiiiiisiiis",
            $user_id, $gaji_pokok, $tunj_jabatan, $tunj_kesehatan,
            $tunj_transport, $tunj_makan, $tunj_rumah, $tunj_pendidikan, $tunj_komunikasi,
            $status_ptkp, // [BARU]
            $pot_pph, $pot_bpjs_kesehatan, $pot_bpjs_ketenagakerjaan, $admin_id
        );
        
        if ($stmt->execute()) {
            $success_message = "Data gaji karyawan berhasil disimpan.";
        } else {
            $errors[] = "Gagal menyimpan data gaji: ". $stmt->error;
        }
        $stmt->close();
    }
}


// 4. Logika GET (Ambil data untuk tabel utama)
$daftar_karyawan_gaji = [];
// --- [PERUBAHAN] Ambil data rincian gaji dan potongan ---
$sql_get = "
    SELECT 
        u.id as user_id, u.nama_lengkap, u.nama_jabatan,
        d.nama_departemen,
        g.gaji_pokok,
        (g.tunj_jabatan + g.tunj_kesehatan) AS total_tunj_tetap,
        (g.tunj_transport + g.tunj_makan + g.tunj_rumah + g.tunj_pendidikan) AS total_tunj_tidak_tetap_gross, -- Dikeluarkan tunj_komunikasi
        g.pot_pph,
        g.pot_bpjs_kesehatan,
        g.pot_bpjs_ketenagakerjaan,
        g.id AS gaji_id
    FROM users u
    LEFT JOIN departemen d ON u.id_departemen = d.id
    LEFT JOIN payroll_master_gaji g ON u.id = g.user_id
    WHERE u.tier != 'Admin' AND u.status_karyawan IN ('PKWT', 'PKWTT', 'BOD', 'BOC')
    ORDER BY u.nama_lengkap ASC
";
// --- [AKHIR PERUBAHAN] ---
$result_get = $conn->query($sql_get);
if ($result_get) {
    while ($row = $result_get->fetch_assoc()) {
        $daftar_karyawan_gaji[] = $row;
    }
} else {
    $errors[] = "Gagal mengambil data karyawan: " . $conn->error;
}

// Ambil data Periode Payroll untuk Info Box
$payroll_settings = [];
$sql_settings = "SELECT setting_key, setting_value FROM payroll_settings 
                 WHERE setting_key IN ('periode_mulai', 'periode_akhir', 'jumlah_hari_kerja')";
$result_settings = $conn->query($sql_settings);
if ($result_settings) {
    while ($row = $result_settings->fetch_assoc()) {
        $payroll_settings[$row['setting_key']] = $row['setting_value'];
    }
}
$periode_mulai_info = $payroll_settings['periode_mulai'] ?? 'N/A';
$periode_akhir_info = $payroll_settings['periode_akhir'] ?? 'N/A';
$jumlah_hari_kerja_info = $payroll_settings['jumlah_hari_kerja'] ?? 0;

// [PERUBAHAN] Ambil data PTKP untuk dropdown modal
$ptkp_list = [];
$sql_ptkp = "SELECT kode_ptkp, deskripsi FROM payroll_settings_ptkp ORDER BY nilai_ptkp_tahunan ASC";
$result_ptkp = $conn->query($sql_ptkp);
if ($result_ptkp) {
    while ($row_ptkp = $result_ptkp->fetch_assoc()) {
        $ptkp_list[] = $row_ptkp;
    }
}


// 5. Panggil header.php
require '../../includes/header.php';

// 6. Panggil sidebar.php
require '../../includes/sidebar.php';
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    
    <div class="flex flex-wrap justify-between items-center mb-6 gap-3">
        <h1 class="text-3xl font-bold text-gray-800">Master Data Gaji</h1>
        
        <div class="flex flex-wrap items-center gap-3">
            <div class="bg-white p-3 rounded-lg shadow-sm border border-gray-200">
                <h4 class="text-xs font-medium text-gray-500 uppercase">Periode Payroll Aktif</h4>
                <div class="flex items-baseline space-x-2 mt-1">
                    <span class="text-xl font-bold text-blue-600 info-box-hari-kerja"><?php echo $jumlah_hari_kerja_info; ?></span>
                    <span class="text-sm text-gray-600">Hari Kerja</span>
                </div>
                <p class="text-xs text-gray-500 info-box-periode">(Tgl <?php echo $periode_mulai_info; ?> s/d Tgl <?php echo $periode_akhir_info; ?>)</p>
            </div>
            <div class="flex space-x-2">
                <button
                    type="button"
                    onclick="openPeriodeModal()"
                    class="btn-primary-sm bg-gray-500 hover:bg-gray-600 flex items-center">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924-1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    Setting Periode
                </button>
                <button
                    type="button"
                    onclick="openModal('gajiModal', 'Input Gaji Karyawan Baru', null)"
                    class="btn-primary-sm bg-blue-600 hover:bg-blue-700 flex items-center">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    Input Gaji
                </button>
            </div>
        </div>
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
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Master Gaji Karyawan</h3>
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Karyawan</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Departemen</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gaji</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Potongan</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                        </tr>
                        </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($daftar_karyawan_gaji)): ?>
                            <tr>
                                <td colspan="5" class="px-4 py-4 text-center text-sm text-gray-500"> Belum ada data karyawan.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($daftar_karyawan_gaji as $data): ?>
                                <tr>
                                    <td class="px-4 py-4 text-sm text-gray-900">
                                        <div class="font-semibold"><?php echo htmlspecialchars($data['nama_lengkap']); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($data['nama_jabatan'] ?: '-'); ?></div>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-700"><?php echo htmlspecialchars($data['nama_departemen'] ?: '-'); ?></td>
                                    
                                    <?php if (empty($data['gaji_id'])): // Jika data gaji belum di-set ?>
                                        <td colspan="2" class="px-4 py-4 text-sm text-center text-gray-500 italic"> Data gaji belum di-input
                                        </td>
                                    <?php else: ?>
                                        <td class="px-4 py-4 text-xs text-gray-700 align-top">
                                            <div>Gaji Pokok:</div>
                                            <div class="font-bold">Rp <?php echo number_format($data['gaji_pokok'], 0, ',', '.'); ?></div>
                                            <div class="mt-1">Tunjangan Tetap:</div>
                                            <div class="font-bold">Rp <?php echo number_format($data['total_tunj_tetap'], 0, ',', '.'); ?></div>
                                            <div class="mt-1">Tunjangan Tdk Tetap (Rate):</div>
                                            <div class="font-bold">Rp <?php echo number_format($data['total_tunj_tidak_tetap_gross'], 0, ',', '.'); ?></div>
                                        </td>
                                        
                                        <td class="px-4 py-4 text-xs text-gray-700 align-top">
                                            <?php
                                            $potongan_list = [];
                                            if ($data['pot_pph'] == 1) { $potongan_list[] = 'PPh'; }
                                            if ($data['pot_bpjs_kesehatan'] == 1) { $potongan_list[] = 'BPJS Kesehatan'; }
                                            if ($data['pot_bpjs_ketenagakerjaan'] == 1) { $potongan_list[] = 'BPJS Ketenagakerjaan'; }
                                            
                                            if (empty($potongan_list)) {
                                                echo '-';
                                            } else {
                                                echo '<ul class="list-disc list-inside">';
                                                foreach ($potongan_list as $pot) {
                                                    echo '<li>' . $pot . '</li>';
                                                }
                                                echo '</ul>';
                                            }
                                            ?>
                                        </td>
                                    <?php endif; ?>

                                    <td class="px-4 py-4 text-sm align-top">
                                        <button
                                            type="button"
                                            onclick="openRincianModal(<?php echo $data['user_id']; ?>, '<?php echo htmlspecialchars(addslashes($data['nama_lengkap'])); ?>', <?php echo $jumlah_hari_kerja_info; ?>)"
                                            class="btn-primary-sm bg-blue-500 hover:bg-blue-600 text-xs">
                                            Rincian Gaji
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
</main>

<div id="gajiModal" class="modal-overlay hidden">
    <div class="bg-white p-6 rounded-lg shadow-xl max-w-3xl w-full mx-4 overflow-y-auto" style="max-height: 90vh;">
        <div class="flex justify-between items-center border-b pb-3 mb-4 sticky top-0 bg-white z-10">
            <h3 class="text-xl font-semibold text-gray-800" id="modalTitle">Input Gaji Karyawan</h3>
            <button onclick="closeModal('gajiModal')" class="text-gray-500 hover:text-gray-800">&times;</button>
        </div>
        
        <form id="modalForm" action="payroll_master_gaji.php" method="POST" class="space-y-6">
            
            <div>
                <label for="user_id_gaji" class="form-label">Nama Karyawan <span class="text-red-500">*</span></label>
                <select id="user_id_gaji" name="user_id_gaji" class="form-input" required>
                    <option value="">-- Pilih Karyawan --</option>
                    <?php foreach ($daftar_karyawan_gaji as $karyawan): ?>
                        <option value="<?php echo $karyawan['user_id']; ?>">
                            <?php echo htmlspecialchars($karyawan['nama_lengkap']); ?> (<?php echo htmlspecialchars($karyawan['nama_jabatan'] ?: 'N/A'); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p id="karyawanWarning" class="text-xs text-yellow-700 mt-1 hidden">
                    Karyawan ini sudah memiliki data gaji. Form ini akan meng-update data yang ada.
                </p>
            </div>
            
            <div>
                <label for="gaji_pokok" class="form-label">Gaji Pokok <span class="text-red-500">*</span></label>
                <input type="text" id="gaji_pokok" name="gaji_pokok" class="form-input" placeholder="Contoh: 5.000.000" onkeyup="formatRupiah(this)" required>
            </div>
            <hr>
            <h4 class="text-md font-semibold text-gray-700">Tunjangan Tetap (Bulanan)</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="tunj_jabatan" class="form-label">Tunjangan Jabatan</label>
                    <input type="text" id="tunj_jabatan" name="tunj_jabatan" class="form-input" onkeyup="formatRupiah(this)" value="0">
                </div>
                <div>
                    <label for="tunj_kesehatan" class="form-label">Tunjangan Kesehatan (Asuransi)</label>
                    <input type="text" id="tunj_kesehatan" name="tunj_kesehatan" class="form-input" onkeyup="formatRupiah(this)" value="0">
                </div>
            </div>
            <hr>
            <h4 class="text-md font-semibold text-gray-700">Tunjangan Tidak Tetap (Rate per Hari / Lainnya)</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="tunj_transport" class="form-label">Tunjangan Transportasi (per hari)</label>
                    <input type="text" id="tunj_transport" name="tunj_transport" class="form-input" onkeyup="formatRupiah(this)" value="0" placeholder="Rate per hari">
                </div>
                <div>
                    <label for="tunj_makan" class="form-label">Tunjangan Makan (per hari)</label>
                    <input type="text" id="tunj_makan" name="tunj_makan" class="form-input" onkeyup="formatRupiah(this)" value="0" placeholder="Rate per hari">
                </div>
                <div>
                    <label for="tunj_rumah" class="form-label">Tunjangan Rumah (Bulanan)</label>
                    <input type="text" id="tunj_rumah" name="tunj_rumah" class="form-input" onkeyup="formatRupiah(this)" value="0">
                </div>
                <div>
                    <label for="tunj_pendidikan" class="form-label">Tunjangan Pendidikan (Bulanan)</label>
                    <input type="text" id="tunj_pendidikan" name="tunj_pendidikan" class="form-input" onkeyup="formatRupiah(this)" value="0">
                </div>
                <div>
                    <label for="tunj_komunikasi" class="form-label">Tunjangan Komunikasi (Reimbursement)</label>
                    <input type="text" id="tunj_komunikasi" name="tunj_komunikasi" class="form-input" onkeyup="formatRupiah(this)" value="0">
                </div>
            </div>
            
            <hr>
            <h4 class="text-md font-semibold text-gray-700">Pajak (PPh 21)</h4>
            <div>
                <label for="status_ptkp" class="form-label">Status PTKP <span class="text-red-500">*</span></label>
                <select id="status_ptkp" name="status_ptkp" class="form-input" required>
                    <option value="">-- Pilih Status PTKP --</option>
                    <?php foreach ($ptkp_list as $ptkp): ?>
                        <option value="<?php echo htmlspecialchars($ptkp['kode_ptkp']); ?>">
                            <?php echo htmlspecialchars($ptkp['kode_ptkp']); ?> (<?php echo htmlspecialchars($ptkp['deskripsi']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">Penting untuk perhitungan PPh 21.</p>
            </div>
            <hr>
            <h4 class="text-md font-semibold text-gray-700">Potongan Wajib</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <label class="flex items-center space-x-2 p-2 border rounded-md hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" id="pot_pph" name="pot_pph" value="1" class="form-checkbox h-5 w-5 text-blue-600 rounded">
                    <span class="text-gray-700">Potong PPh</span>
                </label>
                <label class="flex items-center space-x-2 p-2 border rounded-md hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" id="pot_bpjs_kesehatan" name="pot_bpjs_kesehatan" value="1" class="form-checkbox h-5 w-5 text-blue-600 rounded">
                    <span class="text-gray-700">Potong BPJS Kesehatan</span>
                </label>
                <label class="flex items-center space-x-2 p-2 border rounded-md hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" id="pot_bpjs_ketenagakerjaan" name="pot_bpjs_ketenagakerjaan" value="1" class="form-checkbox h-5 w-5 text-blue-600 rounded">
                    <span class="text-gray-700">Potong BPJS Ketenagakerjaan</span>
                </label>
            </div>

            <div class="mt-8 flex justify-end space-x-3 border-t pt-4 sticky bottom-0 bg-white py-4 -m-6 px-6">
                <button
                    type="button"
                    onclick="closeModal('gajiModal')"
                    class="btn-primary-sm btn-secondary">
                    Batal
                </button>
                <button
                    type="submit"
                    name="simpan_gaji"
                    class="btn-primary-sm bg-blue-600 hover:bg-blue-700">
                    Simpan Data Gaji
                </button>
            </div>
        </form>
    </div>
</div>


<div id="rincianModal" class="modal-overlay hidden">
    <div class="bg-white p-6 rounded-lg shadow-xl max-w-2xl w-full mx-4 overflow-y-auto" style="max-height: 90vh;">
        <div class="flex justify-between items-center border-b pb-3 mb-4 sticky top-0 bg-white z-10">
            <h3 class="text-xl font-semibold text-gray-800" id="rincianModalTitle">Rincian Gaji</h3>
            <button onclick="closeModal('rincianModal')" class="text-gray-500 hover:text-gray-800">&times;</button>
        </div>
        
        <div class="space-y-4" id="rincianModalContent">
            <p class="text-gray-600 text-center">Memuat data...</p> 
        </div>

        <div class="mt-6 flex justify-between border-t pt-4 sticky bottom-0 bg-white py-4 -m-6 px-6">
            <button
                type="button"
                id="rincianEditButton"
                onclick="editGajiFromRincian()"
                class="btn-primary-sm bg-yellow-500 hover:bg-yellow-600">
                Edit Gaji
            </button>
            <button
                type="button"
                onclick="closeModal('rincianModal')"
                class="btn-primary-sm btn-secondary">
                Tutup
            </button>
        </div>
    </div>
</div>

<div id="periodeModal" class="modal-overlay hidden">
    <div class="bg-white p-6 rounded-lg shadow-xl max-w-lg w-full mx-4">
        <div class="flex justify-between items-center border-b pb-3 mb-4">
            <h3 class="text-xl font-semibold text-gray-800" id="periodeModalTitle">Setting Periode Payroll</h3>
            <button onclick="closeModal('periodeModal')" class="text-gray-500 hover:text-gray-800">&times;</button>
        </div>
        
        <div id="periodeModalMessage" class="hidden mb-4"></div>
        
        <form id="periodeForm" onsubmit="submitPeriodeForm(event)" class="space-y-4">
            <p class="text-sm text-gray-600">Atur tanggal mulai dan akhir periode penggajian (angka 1-31). Tanggal ini akan digunakan untuk menghitung jumlah hari kerja (Senin-Jumat) pada periode berjalan.</p>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="periode_mulai" class="form-label">Tanggal Mulai Periode <span class="text-red-500">*</span></label>
                    <input type="number" id="periode_mulai" name="periode_mulai" class="form-input" min="1" max="31" placeholder="Contoh: 26" required>
                </div>
                <div>
                    <label for="periode_akhir" class="form-label">Tanggal Akhir Periode <span class="text-red-500">*</span></label>
                    <input type="number" id="periode_akhir" name="periode_akhir" class="form-input" min="1" max="31" placeholder="Contoh: 25" required>
                </div>
            </div>
            
            <div class="bg-gray-50 p-3 rounded border">
                <h4 class="text-sm font-medium text-gray-700">Info Periode Saat Ini</h4>
                <p class="text-sm text-gray-600 mt-1">
                    Jumlah Hari Kerja (Periode Berjalan): 
                    <strong id="info_hari_kerja" class="text-gray-900">...</strong>
                </p>
                <p class="text-xs text-gray-500 mt-1" id="info_rentang_tanggal">Memuat...</p>
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" onclick="closeModal('periodeModal')" class="btn-primary-sm btn-secondary">
                    Tutup
                </button>
                <button type="submit" id="periodeSubmitButton" class="btn-primary-sm bg-blue-600 hover:bg-blue-700">
                    Simpan Pengaturan
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
    // --- Variabel Global untuk Modal ---
    const gajiModal = document.getElementById('gajiModal');
    const gajiModalTitle = document.getElementById('modalTitle');
    const gajiModalForm = document.getElementById('modalForm');
    const userSelect = document.getElementById('user_id_gaji');
    const warningText = document.getElementById('karyawanWarning');

    const rincianModal = document.getElementById('rincianModal');
    const rincianModalTitle = document.getElementById('rincianModalTitle');
    const rincianModalContent = document.getElementById('rincianModalContent');
    const rincianEditButton = document.getElementById('rincianEditButton');
    
    const periodeModal = document.getElementById('periodeModal');
    const periodeForm = document.getElementById('periodeForm');
    const periodeModalMessage = document.getElementById('periodeModalMessage');
    const infoHariKerja = document.getElementById('info_hari_kerja');
    const infoRentangTanggal = document.getElementById('info_rentang_tanggal');
    const periodeSubmitButton = document.getElementById('periodeSubmitButton');
    
    let currentEditUserId = null;
    let currentEditUserName = '';

    // --- Fungsi Helper ---
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
        input.value = rupiah || '0';
    }

    function cleanRupiah(value) {
        return value.replace(/[^,\d]/g, '').toString().replace(/\./g, '');
    }

    function formatAngka(angka) {
        return 'Rp ' + (angka || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    // --- Fungsi Modal Utama ---
    function openModal(modalId, title, userId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            gajiModalForm.reset();
            gajiModalTitle.innerText = title;
            currentEditUserId = userId;
            
            if (userId) {
                // --- Mode Edit ---
                userSelect.value = userId;
                userSelect.disabled = true;
                warningText.classList.remove('hidden');
                
                fetch('ajax_get_karyawan_gaji.php?user_id=' + userId)
                    .then(response => response.json())
                    .then(data => {
                        if(data.success && data.gaji) {
                            fillForm(data.gaji);
                        } else {
                            fillForm({});
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching gaji details:', error);
                        fillForm({});
                    });
                
            } else {
                // --- Mode Tambah Baru ---
                userSelect.value = '';
                userSelect.disabled = false;
                warningText.classList.add('hidden');
                fillForm({});
            }
            
            modal.classList.remove('hidden');
        }
    }

    function closeModal(modalId) {
        const modalToClose = document.getElementById(modalId);
        if (modalToClose) {
            modalToClose.classList.add('hidden');
        }
    }

    // --- Fungsi Modal Rincian (Read-Only) ---
    function openRincianModal(userId, nama, hariKerja) { // Tambah parameter hariKerja
        if (!rincianModal) return;
        
        rincianModalTitle.innerText = 'Rincian Gaji: ' + nama;
        rincianModalContent.innerHTML = '<p class="text-gray-600 text-center">Memuat data...</p>';
        rincianModal.classList.remove('hidden');
        
        currentEditUserId = userId;
        currentEditUserName = nama;

        fetch('ajax_get_karyawan_gaji.php?user_id=' + userId)
            .then(response => response.json())
            .then(data => {
                if(data.success && data.gaji) {
                    renderRincianContent(data.gaji, hariKerja || 0);
                } else {
                    rincianModalContent.innerHTML = '<p class="text-gray-600 text-center">Data gaji untuk karyawan ini belum di-input.</p>';
                }
            })
            .catch(error => {
                console.error('Error fetching gaji details:', error);
                rincianModalContent.innerHTML = '<p class="text-red-600 text-center">Gagal memuat data.</p>';
            });
    }
    
    // --- [PERBAIKAN] Kalkulasi Gross Income & Tunjangan Harian ---
    function renderRincianContent(data, hariKerja) {
        const pph = data.pot_pph == 1 ? 'Ya' : 'Tidak';
        const bpjsKes = data.pot_bpjs_kesehatan == 1 ? 'Ya' : 'Tidak';
        const bpjsTk = data.pot_bpjs_ketenagakerjaan == 1 ? 'Ya' : 'Tidak';

        const gp = parseInt(data.gaji_pokok) || 0;
        const t_jab = parseInt(data.tunj_jabatan) || 0;
        const t_kes = parseInt(data.tunj_kesehatan) || 0;
        const t_trans_rate = parseInt(data.tunj_transport) || 0;
        const t_makan_rate = parseInt(data.tunj_makan) || 0;
        const t_rumah = parseInt(data.tunj_rumah) || 0;
        const t_didik = parseInt(data.tunj_pendidikan) || 0;
        const t_kom = parseInt(data.tunj_komunikasi) || 0; // Reimbursement
        
        const t_trans_total = t_trans_rate * hariKerja;
        const t_makan_total = t_makan_rate * hariKerja;

        const total_tunjangan_tetap = t_jab + t_kes;
        const total_tunjangan_tidak_tetap = t_trans_total + t_makan_total + t_rumah + t_didik;
        
        // [PERBAIKAN] Gross Income tidak termasuk t_kom
        const gross_income = gp + total_tunjangan_tetap + total_tunjangan_tidak_tetap;

        rincianModalContent.innerHTML = `
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                
                <div class="space-y-4">
                    <div>
                        <dt class="profile-label">Gaji Pokok</dt>
                        <dd class="profile-data text-lg font-semibold">${formatAngka(gp)}</dd>
                    </div>
                    
                    <hr>
                    <dt class="profile-label font-semibold">Tunjangan Tetap</dt>
                    <dl class="ml-4 grid grid-cols-2 gap-x-2 gap-y-1">
                        <dt class="profile-label">Jabatan</dt>
                        <dd class="profile-data text-right">${formatAngka(t_jab)}</dd>
                        <dt class="profile-label">Kesehatan</dt>
                        <dd class="profile-data text-right">${formatAngka(t_kes)}</dd>
                        <dt class="profile-label col-span-2 border-t pt-1 mt-1 font-medium">Subtotal Tunj. Tetap</dt>
                        <dd class="profile-data col-span-2 text-right font-medium border-t pt-1 mt-1">${formatAngka(total_tunjangan_tetap)}</dd>
                    </dl>
                    
                    <hr>
                    <dl class="grid grid-cols-2 gap-x-2 gap-y-1">
                        <dt class="profile-label col-span-2 font-bold text-blue-600">TOTAL GROSS INCOME</dt>
                        <dd class="profile-data col-span-2 text-right font-bold text-xl text-blue-600">${formatAngka(gross_income)}</dd>
                        <dt class="profile-label col-span-2 text-xs">(Gaji Pokok + Tunj. Tetap + Tunj. Tdk Tetap)</dt>
                    </dl>
                </div>
                
                <div class="space-y-4">
                    <dt class="profile-label font-semibold">Tunjangan Tidak Tetap</dt>
                    <dl class="ml-4 grid grid-cols-2 gap-x-2 gap-y-1">
                        <dt class="profile-label">Transportasi</dt>
                        <dd class="profile-data text-right">${formatAngka(t_trans_total)}</dd>
                        <dt class="profile-label text-xs text-gray-500 -mt-1">(Rate: ${formatAngka(t_trans_rate)} x ${hariKerja} hari)</dt>
                        <dd></dd>
                        
                        <dt class="profile-label">Makan</dt>
                        <dd class="profile-data text-right">${formatAngka(t_makan_total)}</dd>
                        <dt class="profile-label text-xs text-gray-500 -mt-1">(Rate: ${formatAngka(t_makan_rate)} x ${hariKerja} hari)</dt>
                        <dd></dd>
                        
                        <dt class="profile-label">Rumah</dt>
                        <dd class="profile-data text-right">${formatAngka(t_rumah)}</dd>
                        <dt class="profile-label">Pendidikan</dt>
                        <dd class="profile-data text-right">${formatAngka(t_didik)}</dd>
                        
                        <dt class="profile-label col-span-2 border-t pt-1 mt-1 font-medium">Subtotal Tunj. Tdk Tetap</dt>
                        <dd class="profile-data col-span-2 text-right font-medium border-t pt-1 mt-1">${formatAngka(total_tunjangan_tidak_tetap)}</dd>
                        
                        <dt class="profile-label col-span-2 border-t pt-1 mt-1 text-gray-500">Tunj. Komunikasi (Reimburse)</dt>
                        <dd class="profile-data col-span-2 text-right text-gray-500 border-t pt-1 mt-1">${formatAngka(t_kom)}</dd>
                    </dl>
                    
                    <hr>
                    <dt class="profile-label font-semibold">Potongan Wajib</dt>
                    <dl class="ml-4 grid grid-cols-2 gap-x-2 gap-y-1">
                        <dt class="profile-label">PPh</dt>
                        <dd class="profile-data">${pph}</dd>
                        <dt class="profile-label">BPJS Kesehatan</dt>
                        <dd class="profile-data">${bpjsKes}</dd>
                        <dt class="profile-label">BPJS Ketenagakerjaan</dt>
                        <dd class="profile-data">${bpjsTk}</dd>
                    </dl>
                </div>
            </dl>
        `;
    }

    function editGajiFromRincian() {
        if (currentEditUserId && currentEditUserName) {
            closeModal('rincianModal');
            openModal('gajiModal', 'Edit Gaji: ' + currentEditUserName, currentEditUserId);
        }
    }

    // [PERUBAHAN] Update fungsi fillForm
    function fillForm(data) {
        document.getElementById('gaji_pokok').value = data.gaji_pokok || 0;
        document.getElementById('tunj_jabatan').value = data.tunj_jabatan || 0;
        document.getElementById('tunj_kesehatan').value = data.tunj_kesehatan || 0;
        document.getElementById('tunj_transport').value = data.tunj_transport || 0;
        document.getElementById('tunj_makan').value = data.tunj_makan || 0;
        document.getElementById('tunj_rumah').value = data.tunj_rumah || 0;
        document.getElementById('tunj_pendidikan').value = data.tunj_pendidikan || 0;
        document.getElementById('tunj_komunikasi').value = data.tunj_komunikasi || 0;
        
        // [BARU] Mengisi nilai PTKP
        document.getElementById('status_ptkp').value = data.status_ptkp || '';
        
        document.getElementById('pot_pph').checked = (data.pot_pph == 1);
        document.getElementById('pot_bpjs_kesehatan').checked = (data.pot_bpjs_kesehatan == 1);
        document.getElementById('pot_bpjs_ketenagakerjaan').checked = (data.pot_bpjs_ketenagakerjaan == 1);
        
        // Format Rupiah untuk semua input yang relevan
        document.querySelectorAll('#modalForm input[type="text"][onkeyup="formatRupiah(this)"]').forEach(input => formatRupiah(input));
    }
    
    // Hapus event listener klik di luar modal (jika ada)
    // ...
    
    gajiModalForm.addEventListener('submit', function(e) {
        document.querySelectorAll('#modalForm input[type="text"][onkeyup="formatRupiah(this)"]').forEach(input => {
            input.value = cleanRupiah(input.value) || '0';
        });
        userSelect.disabled = false;
    });
    
    
    // --- [FUNGSI BARU] Untuk Modal Periode Payroll ---
    function openPeriodeModal() {
        if (!periodeModal) return;
        
        periodeModalMessage.innerHTML = '';
        periodeModalMessage.className = 'hidden mb-4';
        infoHariKerja.innerText = 'Memuat...';
        infoRentangTanggal.innerText = 'Memuat...';
        document.getElementById('periode_mulai').value = '';
        document.getElementById('periode_akhir').value = '';
        
        fetch('ajax_get_payroll_settings.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.settings) {
                    document.getElementById('periode_mulai').value = data.settings.periode_mulai || '';
                    document.getElementById('periode_akhir').value = data.settings.periode_akhir || '';
                    infoHariKerja.innerText = (data.settings.jumlah_hari_kerja || 0) + ' Hari';
                    
                    const today = new Date(2025, 9, 30); // 9 = Oktober (Basis data 2025)
                    const currentDay = today.getDate();
                    const startDay = parseInt(data.settings.periode_mulai) || currentDay;
                    const endDay = parseInt(data.settings.periode_akhir) || currentDay;

                    let startDate, endDate;
                    if (currentDay >= startDay) {
                        startDate = new Date(2025, today.getMonth(), startDay);
                        endDate = new Date(2025, today.getMonth() + 1, endDay);
                    } else {
                        endDate = new Date(2025, today.getMonth(), endDay);
                        startDate = new Date(2025, today.getMonth() - 1, startDay);
                    }
                    infoRentangTanggal.innerText = `Periode berjalan: ${startDate.toLocaleDateString('id-ID', {day: '2-digit', month: 'short', year: 'numeric'})} - ${endDate.toLocaleDateString('id-ID', {day: '2-digit', month: 'short', year: 'numeric'})}`;
                }
            })
            .catch(err => {
                infoHariKerja.innerText = 'Gagal memuat';
                infoRentangTanggal.innerText = 'Error: ' + err.message;
            });
        
        periodeModal.classList.remove('hidden');
    }

    function submitPeriodeForm(event) {
        event.preventDefault();
        periodeSubmitButton.disabled = true;
        periodeSubmitButton.innerText = 'Menyimpan...';

        const formData = new FormData(periodeForm);
        
        fetch('ajax_get_payroll_settings.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                periodeModalMessage.className = 'p-3 rounded bg-green-100 border border-green-400 text-green-700 mb-4';
                periodeModalMessage.innerHTML = `<strong class="font-bold">Sukses!</strong><br>${data.message}`;
                
                infoHariKerja.innerText = (data.data.jumlah_hari_kerja || 0) + ' Hari';
                infoRentangTanggal.innerText = `Periode berjalan: ${data.data.rentang_tanggal}`;
                
                document.querySelector('.info-box-hari-kerja').innerText = (data.data.jumlah_hari_kerja || 0);
                document.querySelector('.info-box-periode').innerText = `(Tgl ${data.data.periode_mulai} s/d Tgl ${data.data.periode_akhir})`;
                
                setTimeout(() => {
                    location.reload(); 
                }, 1500);
                
            } else {
                periodeModalMessage.className = 'p-3 rounded bg-red-100 border border-red-400 text-red-700 mb-4';
                periodeModalMessage.innerHTML = `<strong class="font-bold">Error!</strong><br>${data.message}`;
            }
        })
        .catch(error => {
            periodeModalMessage.className = 'p-3 rounded bg-red-100 border border-red-400 text-red-700 mb-4';
            periodeModalMessage.innerHTML = `<strong class="font-bold">Error!</strong><br>${error.message}`;
        })
        .finally(() => {
            periodeSubmitButton.disabled = false;
            periodeSubmitButton.innerText = 'Simpan Pengaturan';
        });
    }
</script>