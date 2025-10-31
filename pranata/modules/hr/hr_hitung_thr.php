<?php
// pranata/modules/hr/hr_hitung_thr.php

// 1. Set variabel khusus untuk halaman ini
$page_title = "Penghitungan THR";
$page_active = "hr_hitung_thr"; // Sesuai yang di sidebar.php

// 2. Panggil header
require '../../includes/header.php'; // $conn, $_SESSION, $user_id, $tier, $app_akses tersedia

// 3. Keamanan Halaman
$app_akses = $_SESSION['app_akses'] ?? 'Karyawan';
$is_admin = ($app_akses == 'Admin');
$is_hr = ($app_akses == 'HR');
$admin_id_login = $_SESSION['user_id']; // <-- Ambil ID HR yg login

if (!$is_admin && !$is_hr) {
    $_SESSION['flash_message'] = "Anda tidak memiliki hak akses untuk melihat halaman ini.";
    header("Location: ". BASE_URL. "/dashboard.php");
    exit;
}

// 4. Inisialisasi Variabel
date_default_timezone_set('Asia/Jakarta');
$errors = [];
$success_messages = []; 
$thr_results = [];
$total_thr_dibayarkan = 0;
$total_karyawan_terhitung = 0; 

// === [LOGIKA PERHITUNGAN THR] ===
$tgl_pembayaran_thr_default = '2025-03-24'; // Asumsi H-7 Lebaran 2025

// 5. Logika GET/POST (Filter dan Kalkulasi)
$filter_tahun = (int)($_REQUEST['filter_tahun'] ?? date('Y', strtotime($tgl_pembayaran_thr_default))); // <-- Use REQUEST
$basis_perhitungan = $_POST['basis_perhitungan'] ?? 'GP'; // 'GP' atau 'GP_TUNJAB'
$tgl_pembayaran_thr = $_POST['tgl_pembayaran'] ?? $tgl_pembayaran_thr_default;


// === [Logika Status Card] ===
$status_card_info = null;
try {
    // Kueri ini dijalankan saat halaman di-load (GET) atau setelah POST (untuk refresh status)
    $stmt_status = $conn->prepare("SELECT ph.created_at, u.nama_lengkap 
                                  FROM payroll_thr_history ph 
                                  JOIN users u ON ph.calculated_by_id = u.id 
                                  WHERE ph.tahun_thr = ? 
                                  ORDER BY ph.created_at DESC 
                                  LIMIT 1");
    if ($stmt_status) {
        $stmt_status->bind_param("i", $filter_tahun);
        $stmt_status->execute();
        $result_status = $stmt_status->get_result();
        if ($result_status->num_rows > 0) {
            $status_card_info = $result_status->fetch_assoc();
        }
        $stmt_status->close();
    }
} catch (Exception $e) {
    // Biarkan $status_card_info null jika query gagal
    $errors[] = "Gagal memeriksa status kalkulasi: " . $e->getMessage();
}
// === [AKHIR STATUS CARD] ===


// 6. Logika saat tombol "Jalankan Kalkulasi" (POST) ditekan
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['run_calculation'])) {
    
    $conn->begin_transaction();
    
    try {
        // Query: Ambil semua karyawan aktif (non-admin) + data gaji mereka
        $sql_karyawan = "SELECT 
                    u.id, u.nama_lengkap, u.nik, u.tanggal_masuk, u.status_karyawan,
                    pmg.gaji_pokok, pmg.tunj_jabatan
                FROM users u
                LEFT JOIN payroll_master_gaji pmg ON u.id = pmg.user_id
                WHERE u.status_karyawan IN ('PKWT', 'PKWTT', 'BOD', 'BOC') 
                  AND u.tier != 'Admin'
                  AND u.tanggal_masuk <= ?";
        
        $stmt_karyawan = $conn->prepare($sql_karyawan);
        if (!$stmt_karyawan) {
            throw new Exception("Gagal prepare query karyawan: " . $conn->error);
        }
        
        $stmt_karyawan->bind_param("s", $tgl_pembayaran_thr);
        $stmt_karyawan->execute();
        $result_karyawan = $stmt_karyawan->get_result();
        
        if ($result_karyawan->num_rows === 0) {
            throw new Exception("Tidak ada karyawan aktif yang ditemukan pada tanggal acuan.");
        }
        
        // --- Langkah 1: Hapus data THR lama untuk tahun ini ---
        $stmt_delete = $conn->prepare("DELETE FROM payroll_thr_history WHERE tahun_thr = ?");
        if (!$stmt_delete) {
            throw new Exception("Gagal prepare delete data lama: " . $conn->error);
        }
        $stmt_delete->bind_param("i", $filter_tahun);
        $stmt_delete->execute();
        $stmt_delete->close();

        // --- Langkah 2: Siapkan statement INSERT ---
        $sql_insert = "INSERT INTO payroll_thr_history 
                        (user_id, tahun_thr, tanggal_acuan, tanggal_masuk_karyawan, 
                         masa_kerja_bulan, basis_perhitungan_desc, basis_perhitungan_nominal, 
                         keterangan, nominal_thr, status, calculated_by_id)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Calculated', ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        if (!$stmt_insert) {
            throw new Exception("Gagal prepare insert THR: " . $conn->error);
        }

        $date_pembayaran = new DateTime($tgl_pembayaran_thr);
        
        while ($karyawan = $result_karyawan->fetch_assoc()) {
            $date_masuk = new DateTime($karyawan['tanggal_masuk']);
            
            $interval = $date_pembayaran->diff($date_masuk);
            $masa_kerja_bulan = ($interval->y * 12) + $interval->m;
            
            if ($interval->d > 0 && $masa_kerja_bulan < 12) {
                $masa_kerja_bulan += 1;
            }
            if ($masa_kerja_bulan == 0 && $interval->d > 0) {
                 $masa_kerja_bulan = 1;
            }

            $karyawan['masa_kerja_bulan'] = $masa_kerja_bulan;
            
            $nominal_thr = 0;
            $basis_gaji = 0;
            $basis_desc = "";
            
            if ($basis_perhitungan == 'GP') {
                $basis_gaji = $karyawan['gaji_pokok'] ?? 0;
                $basis_desc = "Gaji Pokok";
            } elseif ($basis_perhitungan == 'GP_TUNJAB') {
                $basis_gaji = ($karyawan['gaji_pokok'] ?? 0) + ($karyawan['tunj_jabatan'] ?? 0);
                $basis_desc = "GP + Tunjangan Jabatan";
            }
            
            $karyawan['basis_gaji'] = $basis_gaji;
            $keterangan_thr = "";

            if ($masa_kerja_bulan >= 12) {
                $nominal_thr = $basis_gaji;
                $keterangan_thr = "Penuh (1x Gaji)";
            } 
            elseif ($masa_kerja_bulan >= 1 && $masa_kerja_bulan < 12) {
                $nominal_thr = ($masa_kerja_bulan / 12) * $basis_gaji;
                $keterangan_thr = "Pro-rata ($masa_kerja_bulan/12)";
            } 
            else {
                $nominal_thr = 0;
                $keterangan_thr = "Masa kerja < 1 bulan";
            }
            
            $karyawan['keterangan'] = $keterangan_thr;
            $karyawan['nominal_thr'] = $nominal_thr;
            
            // --- Langkah 3: Bind dan Eksekusi INSERT ---
            $stmt_insert->bind_param(
                "iisssisdsi",
                $karyawan['id'],
                $filter_tahun,
                $tgl_pembayaran_thr,
                $karyawan['tanggal_masuk'],
                $masa_kerja_bulan,
                $basis_desc,
                $basis_gaji,
                $keterangan_thr,
                $nominal_thr,
                $admin_id_login
            );
            if (!$stmt_insert->execute()) {
                throw new Exception("Gagal menyimpan data THR untuk " . $karyawan['nama_lengkap'] . ": " . $stmt_insert->error);
            }
            
            // Simpan ke array untuk ditampilkan di tabel
            $total_thr_dibayarkan += $nominal_thr;
            $thr_results[] = $karyawan;
        }
        
        $total_karyawan_terhitung = $result_karyawan->num_rows;
        $stmt_karyawan->close();
        $stmt_insert->close();
        
        // --- Langkah 4: Commit Transaksi ---
        $conn->commit();
        $success_messages[] = "Kalkulasi THR $filter_tahun berhasil dijalankan dan disimpan untuk $total_karyawan_terhitung karyawan.";
        
        // --- Refresh status card info setelah berhasil kalkulasi ---
        $status_card_info = [
            'created_at' => date('Y-m-d H:i:s'),
            'nama_lengkap' => $_SESSION['nama_lengkap'] // Tampilkan nama admin yg baru saja menekan tombol
        ];

    } catch (Exception $e) {
        // --- Langkah 5: Rollback jika ada error ---
        $conn->rollback();
        $errors[] = $e->getMessage();
    }

// === [PERUBAHAN BARU: Logika GET - Tampilkan Data Lama] ===
// Jika ini adalah request GET (bukan POST) dan status card menemukan data
} else if ($_SERVER["REQUEST_METHOD"] == "GET" && $status_card_info) {
    
    try {
        // Ambil data yang sudah ada di database untuk ditampilkan
        $sql_get = "SELECT 
                        u.nama_lengkap, u.nik,
                        ph.tanggal_masuk_karyawan as tanggal_masuk,
                        ph.masa_kerja_bulan,
                        ph.basis_perhitungan_nominal as basis_gaji,
                        ph.keterangan,
                        ph.nominal_thr
                    FROM payroll_thr_history ph
                    JOIN users u ON ph.user_id = u.id
                    WHERE ph.tahun_thr = ?
                    ORDER BY u.nama_lengkap ASC";
        
        $stmt_get = $conn->prepare($sql_get);
        if (!$stmt_get) {
            throw new Exception("Gagal prepare query ambil data THR: " . $conn->error);
        }
        
        $stmt_get->bind_param("i", $filter_tahun);
        $stmt_get->execute();
        $result_get = $stmt_get->get_result();
        
        while ($row = $result_get->fetch_assoc()) {
            $total_thr_dibayarkan += $row['nominal_thr'];
            $thr_results[] = $row;
        }
        
        $total_karyawan_terhitung = $result_get->num_rows;
        $stmt_get->close();

    } catch (Exception $e) {
        $errors[] = "Gagal mengambil data kalkulasi sebelumnya: " . $e->getMessage();
    }
}
// === [AKHIR PERUBAHAN] ===


// 7. Panggil Sidebar
require '../../includes/sidebar.php';
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Penghitungan Tunjangan Hari Raya (THR)</h1>

    <?php if (!empty($success_messages)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative mb-4 max-w-full" role="alert">
            <strong class="font-bold">Sukses!</strong>
            <ul>
                <?php foreach ($success_messages as $msg): ?>
                    <li><?php echo htmlspecialchars($msg); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4 max-w-full" role="alert">
            <strong class="font-bold">Error!</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="card mb-6 <?php echo $status_card_info ? 'bg-green-50 border-green-300' : 'bg-yellow-50 border-yellow-300'; ?>">
        <div class="card-content">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <?php if ($status_card_info): ?>
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <?php else: ?>
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <?php endif; ?>
                </div>
                <div class="ml-3">
                    <h3 class="text-lg font-semibold <?php echo $status_card_info ? 'text-green-800' : 'text-yellow-800'; ?>">
                        Status Kalkulasi THR Tahun <?php echo $filter_tahun; ?>
                    </h3>
                    <?php if ($status_card_info): ?>
                        <p class="mt-1 text-green-700">
                            Kalkulasi **sudah pernah dijalankan** terakhir pada 
                            <strong><?php echo date('d M Y H:i', strtotime($status_card_info['created_at'])); ?></strong> 
                            oleh <strong><?php echo htmlspecialchars($status_card_info['nama_lengkap']); ?></strong>.
                        </p>
                    <?php else: ?>
                        <p class="mt-1 text-yellow-700">
                            Kalkulasi untuk tahun <?php echo $filter_tahun; ?> **belum pernah dijalankan**. 
                            Silakan isi form di bawah ini dan tekan "Jalankan Kalkulasi".
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-6">
        <div class="card-header">
            <h2 class="text-xl font-semibold text-gray-800">
                <?php echo $status_card_info ? "Kalkulasi Ulang THR $filter_tahun" : "Form Kalkulasi THR $filter_tahun"; ?>
            </h2>
        </div>
        <div class="card-content">
            <form action="hr_hitung_thr.php" method="POST" class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
                <div class="flex items-end gap-4">
                    <div>
                        <label for="filter_tahun" class="form-label">Tahun THR</label>
                        <select id="filter_tahun" name="filter_tahun" class="form-input" onchange="window.location.href='hr_hitung_thr.php?filter_tahun='+this.value">
                            <?php for ($y = 2024; $y <= 2026; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php if ($y == $filter_tahun) echo 'selected'; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                     <div>
                        <label for="tgl_pembayaran" class="form-label">Tgl Acuan Pembayaran</label>
                        <input type="date" id="tgl_pembayaran" name="tgl_pembayaran" class="form-input" value="<?php echo htmlspecialchars($tgl_pembayaran_thr); ?>">
                    </div>
                    <div>
                        <label for="basis_perhitungan" class="form-label">Basis Perhitungan</label>
                        <select id="basis_perhitungan" name="basis_perhitungan" class="form-input">
                            <option value="GP" <?php if ($basis_perhitungan == 'GP') echo 'selected'; ?>>Gaji Pokok (GP)</option>
                            <option value="GP_TUNJAB" <?php if ($basis_perhitungan == 'GP_TUNJAB') echo 'selected'; ?>>GP + Tunjangan Jabatan</option>
                        </select>
                    </div>
                </div>
                
                <button type="submit" 
                        name="run_calculation"
                        class="btn-primary-sm bg-green-600 hover:bg-green-700 h-10 text-base px-4 py-2">
                    <?php echo $status_card_info ? "Jalankan & Timpa Kalkulasi" : "Jalankan & Simpan Kalkulasi"; ?>
                </button>
            </form>
        </div>
    </div>

    <?php if (!empty($thr_results)): ?>
    <div class="card">
        <div class="card-header flex justify-between items-center">
             <h3 class="text-xl font-semibold text-gray-800">
                Hasil Kalkulasi THR <?php echo $filter_tahun; ?> 
                <?php if ($_SERVER["REQUEST_METHOD"] == "POST") echo "(Tersimpan)"; ?>
            </h3>
            <div class="text-right">
                <span class="text-sm text-gray-500 block">Total THR (<?php echo $total_karyawan_terhitung; ?> Karyawan)</span>
                <span class="text-2xl font-bold text-green-700">Rp <?php echo number_format($total_thr_dibayarkan, 0, ',', '.'); ?></span>
            </div>
        </div>
        <div class="card-content">
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama Karyawan</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tgl Masuk</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Masa Kerja (Bulan)</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Basis Gaji (Rp)</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Keterangan</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Nominal THR (Rp)</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($thr_results as $row): ?>
                            <tr>
                                <td class="px-4 py-4 text-sm text-gray-900">
                                    <div class="font-semibold"><?php echo htmlspecialchars($row['nama_lengkap']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['nik']); ?></div>
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-700"><?php echo date('d M Y', strtotime($row['tanggal_masuk'])); ?></td>
                                <td class="px-4 py-4 text-sm text-gray-700 text-center"><?php echo $row['masa_kerja_bulan']; ?> bulan</td>
                                <td class="px-4 py-4 text-sm text-gray-700 text-right"><?php echo number_format($row['basis_gaji'], 0, ',', '.'); ?></td>
                                <td class="px-4 py-4 text-sm text-gray-700"><?php echo htmlspecialchars($row['keterangan']); ?></td>
                                <td class="px-4 py-4 text-sm text-green-700 font-bold text-right">
                                    <?php echo number_format($row['nominal_thr'], 0, ',', '.'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php elseif ($_SERVER["REQUEST_METHOD"] == "POST" && empty($errors)): ?>
         <div class="card">
            <div class="card-content text-center">
                <p class="text-gray-500">Tidak ada data karyawan yang ditemukan untuk kalkulasi.</p>
            </div>
         </div>
    <?php endif; ?>

</main>

<?php
// 8. Panggil footer
require '../../includes/footer.php';
?>