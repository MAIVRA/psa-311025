<?php
// dashboard.php

// 1. Mulai session dan panggil db.php SEKARANG
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require 'includes/db.php'; // $conn, $user_id, $_SESSION, BASE_URL

// 2. Cek login manual (karena header.php belum dipanggil)
if (!isset($_SESSION['user_id'])) {
    header("Location: ". BASE_URL. "/index.php");
    exit;
}
$user_id = $_SESSION['user_id']; // Pastikan user_id ada untuk query

// === [LOGIKA PRESENSI (POST) DIPINDAH KE ATAS] ===
date_default_timezone_set('Asia/Jakarta');
$errors = []; // Error untuk form presensi
$tanggal_hari_ini = date('Y-m-d');
$hari_sekarang = date('N'); // 1 (untuk Senin) sampai 7 (untuk Minggu)
$is_hari_kerja = ($hari_sekarang >= 1 && $hari_sekarang <= 5);

// Cek apakah hari ini Cuti Massal
$cuti_massal_info = null;
$stmt_cuti = $conn->prepare("SELECT keterangan FROM collective_leave WHERE tanggal_cuti = ? AND status = 'Approved'");
$stmt_cuti->bind_param("s", $tanggal_hari_ini);
$stmt_cuti->execute();
$result_cuti = $stmt_cuti->get_result();
if ($result_cuti->num_rows > 0) {
    $cuti_massal_info = $result_cuti->fetch_assoc();
}
$stmt_cuti->close();
$is_cuti_massal = ($cuti_massal_info !== null);


// LOGIKA POST (Saat tombol 'Lakukan Presensi' diklik)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['status_kerja'])) {
    
    $stmt_check_post = $conn->prepare("SELECT id FROM presensi WHERE user_id = ? AND tanggal_presensi = ?");
    $stmt_check_post->bind_param("is", $user_id, $tanggal_hari_ini);
    $stmt_check_post->execute();
    $sudah_presensi = ($stmt_check_post->get_result()->num_rows > 0);
    $stmt_check_post->close();

    if (($is_hari_kerja || $is_cuti_massal) && !$sudah_presensi) {
        $status_kerja = $_POST['status_kerja'] ?? null;
        $lokasi_kerja = null;
        $lokasi_wfh = null;

        if (empty($status_kerja)) { $errors[] = "Silakan pilih Keterangan Kerja."; }
        if ($status_kerja == 'WFO') {
            $lokasi_kerja = $_POST['lokasi_kerja'] ?? null;
            if (empty($lokasi_kerja)) { $errors[] = "Silakan pilih Lokasi Kerja untuk WFO."; }
        } elseif ($status_kerja == 'WFH') {
            $lokasi_wfh = trim($_POST['lokasi_wfh'] ?? '');
            if (empty($lokasi_wfh)) { $errors[] = "Silakan isi Lokasi WFH Anda."; }
        } elseif (!in_array($status_kerja, ['Sakit', 'Dinas'])) {
            $errors[] = "Keterangan Kerja tidak valid.";
        }

        if (empty($errors)) {
            $stmt_insert = $conn->prepare(
                "INSERT INTO presensi (user_id, tanggal_presensi, status_kerja, lokasi_kerja, lokasi_wfh)
                VALUES (?, ?, ?, ?, ?)"
            );
            if ($stmt_insert === false) {
                $errors[] = "Gagal mempersiapkan statement insert: ". $conn->error;
            } else {
                $stmt_insert->bind_param("issss", $user_id, $tanggal_hari_ini, $status_kerja, $lokasi_kerja, $lokasi_wfh);
                if ($stmt_insert->execute()) {
                    $_SESSION['flash_message'] = "Presensi berhasil dicatat!";
                    header("Location: dashboard.php");
                    exit;
                } else {
                    if ($conn->errno == 1062) { $errors[] = "Anda sudah melakukan presensi hari ini."; }
                    else { $errors[] = "Gagal menyimpan presensi: ". $stmt_insert->error; }
                }
                $stmt_insert->close();
            }
        }
    } elseif ($_SERVER["REQUEST_METHOD"] == "POST" && $sudah_presensi) {
        $errors[] = "Anda sudah melakukan presensi hari ini.";
    }
}
// === [AKHIR LOGIKA PRESENSI (POST)] ===


// 3. Set variabel khusus untuk halaman ini
$page_title = "Dashboard";
$page_active = "dashboard";

// 4. Panggil header
require 'includes/header.php'; 

// 5. Panggil Sidebar (PENTING: untuk $is_admin, $is_hr)
require 'includes/sidebar.php'; 

// === [LOGIKA GET - UNTUK TAMPILAN] ===

// Ambil data presensi hari ini
$presensi_hari_ini = null;
$waktu_sekarang = date('H:i:s');
$stmt_check = $conn->prepare("SELECT * FROM presensi WHERE user_id = ? AND tanggal_presensi = ?");
if ($stmt_check) {
    $stmt_check->bind_param("is", $user_id, $tanggal_hari_ini);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    if ($result_check->num_rows > 0) {
        $presensi_hari_ini = $result_check->fetch_assoc();
    }
    $stmt_check->close();
}

// Data statis untuk form presensi
$list_status_kerja = ['WFO', 'Sakit', 'WFH', 'Dinas'];
$list_lokasi_wfo = [
    'PRANATA/Rutan Office (Ikado Surabaya)',
    'TRD Bambe',
    'TRD/BMS Shipyard Lamongan',
    'TRD/Rutan Wringinanom',
    'Terra Office'
];

// Helper functions
if (!function_exists('showData')) {
    function showData($data) { return !empty($data) ? htmlspecialchars($data) : '-'; }
}
if (!function_exists('formatIndonesianDate')) {
    function formatIndonesianDate($dateStr) {
        if (empty($dateStr)) return '-'; if (strtotime($dateStr) === false) return '-';
        $currentLocale = setlocale(LC_TIME, 0);
        setlocale(LC_TIME, 'id_ID.UTF-8', 'Indonesian_Indonesia.1252', 'Indonesian');
        $formattedDate = strftime('%A, %d %B %Y', strtotime($dateStr));
        setlocale(LC_TIME, $currentLocale);
        return $formattedDate;
    }
}
// === [AKHIR LOGIKA GET] ===


// === [LOGIKA BARU: Ambil data personal user] ===
$user_detail = null;
$foto_display = BASE_URL . '/logo.png'; // Default
$stmt_user = $conn->prepare("SELECT nama_jabatan, foto_profile_path FROM users WHERE id = ?");
if($stmt_user) {
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $user_detail = $stmt_user->get_result()->fetch_assoc();
    $stmt_user->close();
    
    if (!empty($user_detail['foto_profile_path']) && file_exists(BASE_PATH . '/' . $user_detail['foto_profile_path'])) {
        $foto_display = BASE_URL . '/' . htmlspecialchars($user_detail['foto_profile_path']) . '?v=' . time();
    }
}
$nama_jabatan = $user_detail['nama_jabatan'] ?? 'Karyawan';


// === [LOGIKA KARTU STATISTIK] ===
// Inisialisasi variabel statistik
$sisa_cuti = 0;
$user_stats_wfo = 0;
$user_stats_wfh = 0;
$user_stats_absen = 0;

$total_karyawan = 0; 
$total_perusahaan = 0; 
$total_cuti_pending = 0; 
$total_dokumen_bulan_ini = 0;


if ($is_admin || $is_hr) {
    // --- Jika Admin/HR, hitung statistik global ---
    $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE status_karyawan IN ('PKWT', 'PKWTT', 'BOD', 'BOC')");
    if ($result) { $total_karyawan = $result->fetch_assoc()['total']; }
    $result = $conn->query("SELECT COUNT(*) as total FROM companies");
    if ($result) { $total_perusahaan = $result->fetch_assoc()['total']; }
    $result = $conn->query("SELECT COUNT(*) as total FROM leave_requests WHERE status = 'Pending'");
    if ($result) { $total_cuti_pending = $result->fetch_assoc()['total']; }
    $result = $conn->query("SELECT COUNT(*) as total FROM document_registry WHERE MONTH(tanggal_surat) = MONTH(NOW()) AND YEAR(tanggal_surat) = YEAR(NOW())");
    if ($result) { $total_dokumen_bulan_ini = $result->fetch_assoc()['total']; }

} else {
    // --- Jika Karyawan Biasa, hitung statistik personal ---
    
    // 1. Hitung Sisa Cuti (logika dari request_leave.php)
    $current_year = date('Y');
    $jatah_cuti_tahunan = 12; // Default
    $stmt_jatah = $conn->prepare("SELECT jumlah_cuti FROM users WHERE id = ?");
    $stmt_jatah->bind_param("i", $user_id);
    if ($stmt_jatah->execute()) {
        $result_jatah = $stmt_jatah->get_result();
        if($row_jatah = $result_jatah->fetch_assoc()) { $jatah_cuti_tahunan = $row_jatah['jumlah_cuti']; }
    }
    $stmt_jatah->close();

    $total_cuti_massal_weekday = 0;
    $stmt_massal = $conn->prepare("SELECT tanggal_cuti FROM collective_leave WHERE status = 'Approved' AND tahun = ?");
    $stmt_massal->bind_param("s", $current_year);
    if ($stmt_massal->execute()) {
        $result_massal = $stmt_massal->get_result();
        while ($row_massal = $result_massal->fetch_assoc()) {
            if (date('N', strtotime($row_massal['tanggal_cuti'])) < 6) { $total_cuti_massal_weekday++; }
        }
    }
    $stmt_massal->close();

    $cuti_diambil = 0;
    $sql_diambil = "SELECT SUM(jumlah_hari) as total_diambil FROM leave_requests WHERE user_id = ? AND status = 'Approved' AND YEAR(tanggal_mulai) = ? AND jenis_cuti = 'Cuti Tahunan'";
    $stmt_diambil = $conn->prepare($sql_diambil);
    $stmt_diambil->bind_param("is", $user_id, $current_year);
    if ($stmt_diambil->execute()) {
        $result_diambil = $stmt_diambil->get_result();
        $row_diambil = $result_diambil->fetch_assoc();
        $cuti_diambil = $row_diambil['total_diambil'] ?? 0;
    }
    $stmt_diambil->close();

    $jatah_efektif = $jatah_cuti_tahunan - $total_cuti_massal_weekday;
    $sisa_cuti = $jatah_efektif - $cuti_diambil;

    // 2. Hitung Statistik Presensi Bulan Ini
    $tgl_awal_bulan = date('Y-m-01');
    $tgl_akhir_bulan = date('Y-m-t');
    $stmt_stats = $conn->prepare("SELECT status_kerja, COUNT(id) as total 
                                 FROM presensi 
                                 WHERE user_id = ? AND tanggal_presensi BETWEEN ? AND ?
                                 GROUP BY status_kerja");
    if($stmt_stats) {
        $stmt_stats->bind_param("iss", $user_id, $tgl_awal_bulan, $tgl_akhir_bulan);
        $stmt_stats->execute();
        $result_stats = $stmt_stats->get_result();
        while($row_stats = $result_stats->fetch_assoc()) {
            if ($row_stats['status_kerja'] == 'WFO') $user_stats_wfo = $row_stats['total'];
            if ($row_stats['status_kerja'] == 'WFH') $user_stats_wfh = $row_stats['total'];
            if ($row_stats['status_kerja'] == 'Sakit' || $row_stats['status_kerja'] == 'Dinas') {
                $user_stats_absen += $row_stats['total']; // Gabungkan sakit + dinas
            }
        }
        $stmt_stats->close();
    }
}
// === [AKHIR LOGIKA KARTU STATISTIK] ===


$flash_message = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}
?>

<div id="flashModal" class="modal-overlay <?php echo empty($flash_message) ? 'hidden' : ''; ?>">
    <div class="bg-white p-6 rounded-lg shadow-xl max-w-sm w-full mx-4">
        <div class="flex items-center">
            <div class="flex-shrink-0 bg-green-100 rounded-full p-2">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            </div>
            <div class="ml-4">
                <h3 class="text-lg font-semibold text-gray-800">Sukses!</h3>
                <p id="flashModalMessage" class="mt-1 text-gray-600"><?php echo htmlspecialchars($flash_message); ?></p>
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
document.addEventListener('DOMContentLoaded', (event) => {
    if ('<?php echo !empty($flash_message); ?>' == '1') {
        const modal = document.getElementById('flashModal');
        if(modal) modal.classList.remove('hidden');
    }
});
</script>


<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    
    <div class="card mb-6 bg-white shadow-md">
        <div class="card-content">
            <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
                
                <div class="flex items-center space-x-5">
                    <img src="<?php echo $foto_display; ?>" alt="Foto Profil" class="w-16 h-16 md:w-20 md:h-20 rounded-full object-cover border-4 border-gray-200 flex-shrink-0">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-bold text-gray-800">
                            Selamat Datang, <?php echo htmlspecialchars($nama_lengkap); ?>!
                        </h1>
                        <p class="text-gray-600 text-base md:text-lg"><?php echo htmlspecialchars($nama_jabatan); ?></p>
                    </div>
                </div>

                <div class="flex-shrink-0 text-left md:text-right">
                    <p class="text-sm md:text-base text-gray-600 font-medium">
                        <?php echo formatIndonesianDate($tanggal_hari_ini); ?>
                    </p>
                    <p class="text-lg md:text-2xl font-bold text-gray-800" id="waktu-live">
                        <?php echo date('H:i:s'); ?> WIB
                    </p>
                </div>

            </div>
        </div>
    </div>
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
    
    <div class="mb-8">
    
    <?php if ($presensi_hari_ini): ?>
        <div class="card bg-green-50 border-green-300 mb-6">
            <div class="card-content">
                <h3 class="text-xl font-semibold text-green-800 mb-4 text-center">Anda Sudah Melakukan Presensi Hari Ini</h3>
                <?php if ($is_cuti_massal): ?>
                    <p class="text-center text-sm text-green-700 mb-4">(Presensi Anda dicatat sebagai <strong>Lembur Cuti Massal</strong>)</p>
                <?php endif; ?>
                
                <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-3">
                    <div class="sm:col-span-1">
                        <dt class="profile-label">Waktu Presensi</dt>
                        <dd class="profile-data"><?php echo date('H:i:s', strtotime($presensi_hari_ini['waktu_presensi'])); ?> WIB</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="profile-label">Keterangan Kerja</dt>
                        <dd class="profile-data"><?php echo htmlspecialchars($presensi_hari_ini['status_kerja']); ?></dd>
                    </div>
                    <?php if ($presensi_hari_ini['status_kerja'] == 'WFO'): ?>
                    <div class="sm:col-span-3">
                        <dt class="profile-label">Lokasi Kerja</dt>
                        <dd class="profile-data"><?php echo htmlspecialchars($presensi_hari_ini['lokasi_kerja']); ?></dd>
                    </div>
                    <?php elseif ($presensi_hari_ini['status_kerja'] == 'WFH'): ?>
                     <div class="sm:col-span-3">
                        <dt class="profile-label">Lokasi WFH</dt>
                        <dd class="profile-data"><?php echo nl2br(htmlspecialchars($presensi_hari_ini['lokasi_wfh'])); ?></dd>
                    </div>
                    <?php elseif ($presensi_hari_ini['status_kerja'] == 'Sakit'): ?>
                        <div class="sm:col-span-3 mt-2 p-3 bg-blue-50 border border-blue-200 rounded-md">
                            <p class="text-sm text-blue-700">
                                <strong class="font-semibold">Perhatian:</strong> Jangan lupa untuk mengunggah Surat Keterangan Sakit Anda.
                                </p>
                        </div>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

    <?php elseif ($is_cuti_massal): ?>
        <div class="card bg-blue-50 border-blue-300 mb-6">
            <div class="card-content text-center">
                <h3 class="text-xl font-semibold text-blue-800">Cuti Massal</h3>
                <p class="text-blue-700 mt-2">
                    Hari ini adalah <strong><?php echo htmlspecialchars($cuti_massal_info['keterangan']); ?></strong>.
                    Anda tidak perlu melakukan presensi. Selamat beristirahat.
                </p>
                <p class="text-blue-700 mt-2 text-sm">
                    (Jika Anda diharuskan bekerja/lembur, silakan isi form presensi di bawah ini.)
                </p>
            </div>
        </div>
        <div class="card mb-6">
            <form action="dashboard.php" method="POST" class="card-content space-y-5">
                <div>
                    <label class="form-label block mb-3">Keterangan Kerja <span class="text-red-500">*</span></label>
                    <div class="flex flex-wrap gap-4">
                        <?php foreach ($list_status_kerja as $status): ?>
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="radio" name="status_kerja" value="<?php echo $status; ?>" class="form-radio h-5 w-5 text-blue-600" required>
                                <span class="ml-2 text-gray-700"><?php echo $status; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div id="lokasi_wfo_section" class="hidden">
                    <label for="lokasi_kerja" class="form-label">Lokasi Kerja (WFO) <span class="text-red-500">*</span></label>
                    <select id="lokasi_kerja" name="lokasi_kerja" class="form-input">
                        <option value="">-- Pilih Lokasi --</option>
                        <?php foreach ($list_lokasi_wfo as $lokasi): ?>
                            <option value="<?php echo htmlspecialchars($lokasi); ?>"><?php echo htmlspecialchars($lokasi); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="lokasi_wfh_section" class="hidden">
                     <label for="lokasi_wfh" class="form-label">Lokasi WFH <span class="text-red-500">*</span></label>
                     <textarea id="lokasi_wfh" name="lokasi_wfh" rows="3" class="form-input" placeholder="Contoh: Rumah, Jl. Contoh No. 1, Surabaya"></textarea>
                </div>
                <div id="keterangan_sakit" class="hidden p-3 bg-blue-50 border border-blue-200 rounded-md text-sm text-blue-700">
                    Anda diwajibkan untuk memberikan Surat Keterangan Sakit selambatnya setelah Anda masuk kembali. Tombol unggah akan tersedia di daftar riwayat presensi Anda.
                </div>
                <div id="keterangan_dinas" class="hidden p-3 bg-yellow-50 border border-yellow-200 rounded-md text-sm text-yellow-700">
                     Pastikan Surat Perintah Tugas/Dinas Anda telah disetujui dan buat laporan setelah selesai melaksanakan dinas.
                </div>
                <div class="pt-4 border-t">
                    <button type="submit" class="w-full btn-primary-sm bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-semibold text-lg transition duration-200 flex items-center justify-center">
                        <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        Lakukan Presensi (Lembur)
                    </button>
                </div>
            </form>
        </div>


    <?php elseif (!$is_hari_kerja): ?>
        <div class="card bg-yellow-50 border-yellow-300 mb-6">
            <div class="card-content text-center">
                <h3 class="text-xl font-semibold text-yellow-800">Hari Libur</h3>
                <p class="text-yellow-700 mt-2">Presensi hanya dapat dilakukan pada hari Senin - Jumat.</p>
            </div>
        </div>

    <?php else: ?>
        <div class="card mb-6">
            <form action="dashboard.php" method="POST" class="card-content space-y-5">
                <div>
                    <label class="form-label block mb-3">Keterangan Kerja <span class="text-red-500">*</span></label>
                    <div class="flex flex-wrap gap-4">
                        <?php foreach ($list_status_kerja as $status): ?>
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="radio" name="status_kerja" value="<?php echo $status; ?>" class="form-radio h-5 w-5 text-blue-600" required>
                                <span class="ml-2 text-gray-700"><?php echo $status; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div id="lokasi_wfo_section" class="hidden">
                    <label for="lokasi_kerja" class="form-label">Lokasi Kerja (WFO) <span class="text-red-500">*</span></label>
                    <select id="lokasi_kerja" name="lokasi_kerja" class="form-input">
                        <option value="">-- Pilih Lokasi --</option>
                        <?php foreach ($list_lokasi_wfo as $lokasi): ?>
                            <option value="<?php echo htmlspecialchars($lokasi); ?>"><?php echo htmlspecialchars($lokasi); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="lokasi_wfh_section" class="hidden">
                     <label for="lokasi_wfh" class="form-label">Lokasi WFH <span class="text-red-500">*</span></label>
                     <textarea id="lokasi_wfh" name="lokasi_wfh" rows="3" class="form-input" placeholder="Contoh: Rumah, Jl. Contoh No. 1, Surabaya"></textarea>
                </div>
                <div id="keterangan_sakit" class="hidden p-3 bg-blue-50 border border-blue-200 rounded-md text-sm text-blue-700">
                    Anda diwajibkan untuk memberikan Surat Keterangan Sakit selambatnya setelah Anda masuk kembali. Tombol unggah akan tersedia di daftar riwayat presensi Anda.
                </div>
                <div id="keterangan_dinas" class="hidden p-3 bg-yellow-50 border border-yellow-200 rounded-md text-sm text-yellow-700">
                     Pastikan Surat Perintah Tugas/Dinas Anda telah disetujui dan buat laporan setelah selesai melaksanakan dinas.
                </div>
                <div class="pt-4 border-t">
                    <button type="submit" class="w-full btn-primary-sm bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-semibold text-lg transition duration-200 flex items-center justify-center">
                        <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                Lakukan Presensi Sekarang
            </button>
                </div>
            </form>
        </div>
    
    <?php endif; ?>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        
        <?php if ($is_admin || $is_hr): // Tampilan untuk Admin/HR ?>
            <div class="bg-white p-5 rounded-lg shadow">
                <h3 class="text-sm font-medium text-gray-500 uppercase">Total Karyawan Aktif</h3>
                <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $total_karyawan; ?></p>
            </div>
             <div class="bg-white p-5 rounded-lg shadow">
                <h3 class="text-sm font-medium text-gray-500 uppercase">Total Perusahaan</h3>
                <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $total_perusahaan; ?></p>
            </div>
             <div class="bg-white p-5 rounded-lg shadow">
                <h3 class="text-sm font-medium text-gray-500 uppercase">Cuti Menunggu Persetujuan</h3>
                <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $total_cuti_pending; ?></p>
            </div>
             <div class="bg-white p-5 rounded-lg shadow">
                <h3 class="text-sm font-medium text-gray-500 uppercase">Dokumen Terbit (Bulan Ini)</h3>
                <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $total_dokumen_bulan_ini; ?></p>
            </div>
        
        <?php else: // Tampilan untuk Karyawan Biasa ?>
            <div class="bg-white p-5 rounded-lg shadow">
                <h3 class="text-sm font-medium text-gray-500 uppercase">Sisa Cuti Tahunan <?php echo $current_year; ?></h3>
                <p class="text-3xl font-bold text-blue-600 mt-2"><?php echo $sisa_cuti; ?> <span class="text-lg">Hari</span></p>
            </div>
             <div class="bg-white p-5 rounded-lg shadow">
                <h3 class="text-sm font-medium text-gray-500 uppercase">WFO Bulan Ini</h3>
                <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $user_stats_wfo; ?> <span class="text-lg">Hari</span></p>
            </div>
             <div class="bg-white p-5 rounded-lg shadow">
                <h3 class="text-sm font-medium text-gray-500 uppercase">WFH Bulan Ini</h3>
                <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $user_stats_wfh; ?> <span class="text-lg">Hari</span></p>
            </div>
             <div class="bg-white p-5 rounded-lg shadow">
                <h3 class="text-sm font-medium text-gray-500 uppercase">Sakit/Dinas Bulan Ini</h3>
                <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $user_stats_absen; ?> <span class="text-lg">Hari</span></p>
            </div>
        <?php endif; ?>

    </div>
    </main>

<script>
    // Script untuk update jam live
    function updateWaktu() {
        const waktuElement = document.getElementById('waktu-live');
        if (waktuElement) {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            waktuElement.textContent = `${hours}:${minutes}:${seconds} WIB`;
        }
    }
    setInterval(updateWaktu, 1000);

    // Script untuk show/hide input lokasi (tidak berubah)
    const radioStatusKerja = document.querySelectorAll('input[name="status_kerja"]');
    const sectionWfo = document.getElementById('lokasi_wfo_section');
    const inputWfo = document.getElementById('lokasi_kerja');
    const sectionWfh = document.getElementById('lokasi_wfh_section');
    const inputWfh = document.getElementById('lokasi_wfh');
    const ketSakit = document.getElementById('keterangan_sakit');
    const ketDinas = document.getElementById('keterangan_dinas');

    function handleStatusChange() {
        const selectedStatus = document.querySelector('input[name="status_kerja"]:checked')?.value;
        if (sectionWfo) sectionWfo.classList.add('hidden');
        if (inputWfo) inputWfo.required = false;
        if (sectionWfh) sectionWfh.classList.add('hidden');
        if (inputWfh) inputWfh.required = false;
        if (ketSakit) ketSakit.classList.add('hidden');
        if (ketDinas) ketDinas.classList.add('hidden');

        if (selectedStatus === 'WFO') {
            if (sectionWfo) sectionWfo.classList.remove('hidden');
            if (inputWfo) inputWfo.required = true;
        } else if (selectedStatus === 'WFH') {
            if (sectionWfh) sectionWfh.classList.remove('hidden');
            if (inputWfh) inputWfh.required = true;
        } else if (selectedStatus === 'Sakit') {
            if (ketSakit) ketSakit.classList.remove('hidden');
        } else if (selectedStatus === 'Dinas') {
            if (ketDinas) ketDinas.classList.remove('hidden');
        }
    }
    
    if (radioStatusKerja.length > 0) {
        radioStatusKerja.forEach(radio => {
            radio.addEventListener('change', handleStatusChange);
        });
        // Panggil saat load untuk memastikan state awal benar (jika ada value yg sudah ter-check)
        handleStatusChange(); 
    }
</script>

<?php
require 'includes/footer.php';
?>