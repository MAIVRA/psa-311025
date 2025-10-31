<?php
// pranata/modules/hr/hr_hitung_gaji.php

// 1. Set variabel khusus untuk halaman ini
$page_title = "Penghitungan Gaji";
$page_active = "hr_hitung_gaji"; // Nanti kita tambahkan ke sidebar

// 2. Panggil header
require '../../includes/header.php'; // $conn, $_SESSION, $user_id, $tier, $app_akses tersedia

// 3. Keamanan Halaman
$app_akses = $_SESSION['app_akses'] ?? 'Karyawan';
$is_admin = ($app_akses == 'Admin');
$is_hr = ($app_akses == 'HR');
$admin_id = $_SESSION['user_id']; // ID HR/Admin yg login

if (!$is_admin && !$is_hr) {
    $_SESSION['flash_message'] = "Anda tidak memiliki hak akses untuk melihat halaman ini.";
    header("Location: ". BASE_URL. "/dashboard.php");
    exit;
}

// 4. Include "Mesin" Payroll Engine
// (File ini berisi fungsi runPayrollCalculation() )
require_once 'includes/payroll_engine.php';

// 5. Inisialisasi Variabel
$errors = [];
$success_messages = [];
date_default_timezone_set('Asia/Jakarta');

// 6. Logika POST (Menjalankan Kalkulasi Gaji)
if ($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['run_calculation']) || isset($_POST['run_calculation_single']))) {
    
    $run_tahun = (int)($_POST['filter_tahun'] ?? 0);
    $run_bulan = (int)($_POST['filter_bulan'] ?? 0);
    $metode_pph_pilihan = $_POST['metode_pph_pilihan'] ?? 'TER';
    
    // === [PERUBAHAN BARU] ===
    // Cek apakah checkbox "Sertakan THR" di-tick
    $include_thr = isset($_POST['include_thr']); 
    // === [AKHIR PERUBAHAN] ===

    if (empty($run_tahun) || empty($run_bulan)) {
        $errors[] = "Tahun dan Bulan wajib dipilih untuk menjalankan kalkulasi.";
    } else {
        
        $users_to_run = [];
        
        if (isset($_POST['run_calculation_single'])) {
            // --- SINGLE RUN ---
            $user_id_to_run = (int)($_POST['user_id_to_run'] ?? 0);
            if ($user_id_to_run > 0) {
                 $stmt_single_user = $conn->prepare("SELECT id, nama_lengkap FROM users WHERE id = ?");
                 $stmt_single_user->bind_param("i", $user_id_to_run);
                 $stmt_single_user->execute();
                 $result_single_user = $stmt_single_user->get_result();
                 if ($karyawan = $result_single_user->fetch_assoc()) {
                     $users_to_run[] = $karyawan;
                 }
                 $stmt_single_user->close();
            }
        } else {
            // --- FULL RUN ---
            $stmt_users = $conn->prepare("SELECT id, nama_lengkap FROM users WHERE status_karyawan IN ('PKWT', 'PKWTT', 'BOD', 'BOC') AND tier != 'Admin'");
            if (!$stmt_users) {
                $errors[] = "Gagal prepare mengambil daftar karyawan: " . $conn->error;
            } else {
                $stmt_users->execute();
                $result_users = $stmt_users->get_result();
                if ($result_users->num_rows === 0) {
                     $errors[] = "Tidak ada karyawan aktif yang ditemukan untuk dikalkulasi.";
                }
                while ($karyawan = $result_users->fetch_assoc()) {
                    $users_to_run[] = $karyawan;
                }
                $stmt_users->close();
            }
        }

        // Jalankan kalkulasi untuk user yang terpilih
        foreach ($users_to_run as $karyawan) {
            
            // === [PERUBAHAN BARU] ===
            // Kirim $include_thr (true/false) ke engine
            // Kita akan modifikasi 'payroll_engine.php' di langkah berikutnya
            $result = runPayrollCalculation($conn, $karyawan['id'], $run_tahun, $run_bulan, $admin_id, $metode_pph_pilihan, $include_thr);
            // === [AKHIR PERUBAHAN] ===
            
            if ($result['success']) {
                // Sukses
            } else {
                $errors[] = "Gagal menghitung gaji untuk <strong>" . htmlspecialchars($karyawan['nama_lengkap']) . "</strong>, " . $result['message'];
            }
        }
            
        if (empty($errors)) {
             if (isset($_POST['run_calculation_single'])) {
                 echo "Kalkulasi ulang untuk " . htmlspecialchars($users_to_run[0]['nama_lengkap']) . " berhasil.";
                 exit; 
             } else {
                 $msg_sukses = "Kalkulasi payroll (Metode: $metode_pph_pilihan) untuk periode $run_bulan-$run_tahun berhasil dijalankan untuk " . count($users_to_run) . " karyawan.";
                 // === [PERUBAHAN BARU] ===
                 if ($include_thr) {
                     $msg_sukses .= " Pembayaran THR disertakan.";
                 }
                 $success_messages[] = $msg_sukses;
                 // === [AKHIR PERUBAHAN] ===
             }
        } else {
             if (isset($_POST['run_calculation_single'])) {
                 echo "Error: " . implode(", ", $errors);
                 exit;
             }
        }
    }
}


// 7. Logika GET (Menampilkan Data)
$current_db_year = 2025;
$current_db_month = 10; 

$filter_tahun = (int)($_GET['filter_tahun'] ?? $current_db_year);
$filter_bulan = (int)($_GET['filter_bulan'] ?? $current_db_month);

// --- Logika Pagination ---
$limit = 25; 
$page = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
$offset = ($page - 1) * $limit;
$total_results = 0;
$total_pages = 0;

$filter_query_string = http_build_query([
    'filter_tahun' => $filter_tahun,
    'filter_bulan' => $filter_bulan
]);

$sql_where_users = " WHERE u.status_karyawan IN ('PKWT', 'PKWTT', 'BOD', 'BOC') AND u.tier != 'Admin' ";

// --- Query 1: Menghitung TOTAL DATA KARYAWAN (untuk pagination) ---
$sql_count = "SELECT COUNT(u.id) FROM users u" . $sql_where_users;
$stmt_count = $conn->prepare($sql_count);
if ($stmt_count === false) {
    $errors[] = "Gagal mempersiapkan query count: " . $conn->error;
} else {
    $stmt_count->execute();
    $total_results = $stmt_count->get_result()->fetch_row()[0];
    $total_pages = ceil($total_results / $limit);
    $stmt_count->close();
}
// --- Akhir Query COUNT ---


$payroll_results = [];
$sql_display = "SELECT 
                    u.id as user_id, u.nama_lengkap, u.nik, d.nama_departemen,
                    ph.id as history_id, ph.gaji_pokok_final, 
                    ph.total_tunjangan_tetap, ph.total_tunjangan_tidak_tetap, ph.total_tunjangan_lain,
                    ph.total_gross_income, ph.total_potongan_bpjs,
                    ph.total_potongan_pph21, ph.total_potongan_lainnya,
                    ph.take_home_pay, ph.status
                FROM users u
                LEFT JOIN departemen d ON u.id_departemen = d.id
                LEFT JOIN payroll_history ph ON u.id = ph.user_id 
                                              AND ph.periode_tahun = ? 
                                              AND ph.periode_bulan = ?
                $sql_where_users
                ORDER BY u.nama_lengkap ASC
                LIMIT ? OFFSET ?";

$stmt_display = $conn->prepare($sql_display);
if (!$stmt_display) {
     $errors[] = "Gagal prepare query display: " . $conn->error;
} else {
    $stmt_display->bind_param("iiii", $filter_tahun, $filter_bulan, $limit, $offset);
    $stmt_display->execute();
    $result_display = $stmt_display->get_result();
    while ($row = $result_display->fetch_assoc()) {
        $payroll_results[] = $row;
    }
    $stmt_display->close();
}

// 8. Panggil Sidebar
require '../../includes/sidebar.php';
?>

<style>
    /* Style untuk status di tabel */
    .status-badge {
        padding: 0.25rem 0.625rem;
        font-size: 0.75rem;
        font-weight: 500;
        border-radius: 9999px;
    }
    .status-calculated { background-color: #E0E7FF; color: #3730A3; } /* indigo */
    .status-locked { background-color: #FEF3C7; color: #92400E; } /* amber */
    .status-paid { background-color: #D1FAE5; color: #065F46; } /* green */
    .status-pending { background-color: #F3F4F6; color: #4B5563; } /* gray */
</style>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Penghitungan Gaji Karyawan</h1>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4 max-w-full" role="alert">
            <strong class="font-bold">Error!</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; /* Pesan error sudah di-format di PHP */ ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

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

    <div class="card mb-6">
        <div class="card-content">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
                <form action="hr_hitung_gaji.php" method="GET" class="flex items-end gap-4">
                    <div>
                        <label for="filter_tahun" class="form-label">Tahun</label>
                        <select id="filter_tahun" name="filter_tahun" class="form-input">
                            <?php for ($y = 2024; $y <= 2026; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php if ($y == $filter_tahun) echo 'selected'; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label for="filter_bulan" class="form-label">Bulan</label>
                        <select id="filter_bulan" name="filter_bulan" class="form-input">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php if ($m == $filter_bulan) echo 'selected'; ?>><?php echo date('F', mktime(0, 0, 0, $m, 10)); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary-sm bg-blue-600 hover:bg-blue-700 h-10">
                        Tampilkan Data
                    </button>
                </form>
                
                <form id="runCalculationForm" action="hr_hitung_gaji.php?filter_tahun=<?php echo $filter_tahun; ?>&filter_bulan=<?php echo $filter_bulan; ?>" method="POST" class="flex items-end gap-3">
                    <input type="hidden" name="filter_tahun" value="<?php echo $filter_tahun; ?>">
                    <input type="hidden" name="filter_bulan" value="<?php echo $filter_bulan; ?>">
                    <input type="hidden" name="run_calculation" value="1">
                    
                    <div>
                        <label for="metode_pph_pilihan" class="form-label">Metode Hitung PPh 21</label>
                        <select id="metode_pph_pilihan" name="metode_pph_pilihan" class="form-input h-10">
                            <option value="TER">Metode TER (Aturan Baru PMK 168)</option>
                            <option value="REGULER">Metode REGULER (Prorata Tahunan)</option>
                        </select>
                    </div>
                    
                    <div class="flex items-center h-10 ml-2">
                        <input id="include_thr" name="include_thr" type="checkbox" value="1" class="h-5 w-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <label for="include_thr" class="ml-2 block text-sm font-medium text-gray-700">
                            Sertakan Pembayaran THR
                        </label>
                    </div>
                    
                    <button type="button" 
                            onclick="openConfirmRunModal()"
                            class="btn-primary-sm bg-green-600 hover:bg-green-700 h-10 text-base px-4 py-2">
                        Jalankan Kalkulasi
                    </button>
                </form>
                </div>
        </div>
    </div>

    <div class="card">
        <div class="card-content">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">
                Hasil Gaji Periode: <?php echo date('F', mktime(0, 0, 0, $filter_bulan, 10)) . " " . $filter_tahun; ?>
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama Karyawan</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Departemen</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Gross Income (Rp)</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Potongan (Rp)</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Take Home Pay (Rp)</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($payroll_results)): ?>
                            <tr>
                                <td colspan="7" class="px-4 py-4 text-center text-sm text-gray-500">
                                    Tidak ada data karyawan.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payroll_results as $row): ?>
                                <tr>
                                    <td class="px-4 py-4 text-sm text-gray-900">
                                        <div class="font-semibold"><?php echo htmlspecialchars($row['nama_lengkap']); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['nik']); ?></div>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-700"><?php echo htmlspecialchars($row['nama_departemen'] ?? '-'); ?></td>
                                    
                                    <?php if (empty($row['history_id'])): // Jika data NULL (belum dihitung) ?>
                                        <td colspan="3" class="px-4 py-4 text-center text-sm text-gray-500 italic">
                                            Belum dikalkulasi
                                        </td>
                                        <td class="px-4 py-4 text-center">
                                            <span class="status-badge status-pending">Pending</span>
                                        </td>
                                        <td class="px-4 py-4 text-sm">
                                            <button type="button" class="btn-primary-sm btn-secondary text-xs" disabled>Detail</button>
                                            <button type="button" 
                                                    onclick="openPotonganModal(<?php echo $row['user_id']; ?>, '<?php echo htmlspecialchars(addslashes($row['nama_lengkap'])); ?>')"
                                                    class="btn-primary-sm bg-yellow-500 hover:bg-yellow-600 text-xs mt-1">
                                                Pot. Lain
                                            </button>
                                        </td>
                                    <?php else: 
                                        $total_potongan = $row['total_potongan_bpjs'] + $row['total_potongan_pph21'] + $row['total_potongan_lainnya'];
                                    ?>
                                        <td class="px-4 py-4 text-sm text-gray-700 text-right"><?php echo number_format($row['total_gross_income'], 0, ',', '.'); ?></td>
                                        <td class="px-4 py-4 text-sm text-red-600 text-right"><?php echo number_format($total_potongan, 0, ',', '.'); ?></td>
                                        <td class="px-4 py-4 text-sm text-green-700 font-bold text-right"><?php echo number_format($row['take_home_pay'], 0, ',', '.'); ?></td>
                                        <td class="px-4 py-4 text-center">
                                            <?php 
                                            $status = $row['status'];
                                            $status_class = 'status-pending';
                                            if ($status == 'Calculated') $status_class = 'status-calculated';
                                            if ($status == 'Locked') $status_class = 'status-locked';
                                            if ($status == 'Paid') $status_class = 'status-paid';
                                            echo "<span class='status-badge $status_class'>$status</span>";
                                            ?>
                                        </td>
                                        <td class="px-4 py-4 text-sm space-x-1">
                                            <button type="button" 
                                                    onclick="openDetailModal(<?php echo $row['history_id']; ?>, '<?php echo htmlspecialchars(addslashes($row['nama_lengkap'])); ?>')"
                                                    class="btn-primary-sm bg-blue-500 hover:bg-blue-600 text-xs">
                                                Detail
                                            </button>
                                            <button type="button" 
                                                    onclick="openPotonganModal(<?php echo $row['user_id']; ?>, '<?php echo htmlspecialchars(addslashes($row['nama_lengkap'])); ?>')"
                                                    class="btn-primary-sm bg-yellow-500 hover:bg-yellow-600 text-xs">
                                                Pot. Lain
                                            </button>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
                <div class="px-4 py-4 border-t border-gray-200">
                    <nav class="flex justify-between items-center">
                        <div>
                            <p class="text-sm text-gray-700">
                                Menampilkan
                                <span class="font-medium"><?php echo $offset + 1; ?></span>
                                -
                                <span class="font-medium"><?php echo min($offset + $limit, $total_results); ?></span>
                                dari
                                <span class="font-medium"><?php echo $total_results; ?></span>
                                hasil
                            </p>
                        </div>
                        <div class="flex space-x-1">
                            <a href="?halaman=<?php echo max(1, $page - 1); ?>&<?php echo $filter_query_string; ?>" 
                               class="px-3 py-1 rounded-md text-sm font-medium <?php echo ($page <= 1) ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'bg-white text-gray-600 hover:bg-gray-50 border'; ?> no-underline">
                                Sebelumnya
                            </a>
                            <a href="?halaman=<?php echo min($total_pages, $page + 1); ?>&<?php echo $filter_query_string; ?>" 
                               class="px-3 py-1 rounded-md text-sm font-medium <?php echo ($page >= $total_pages) ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'bg-white text-gray-600 hover:bg-gray-50 border'; ?> no-underline">
                                Berikutnya
                            </a>
                        </div>
                    </nav>
                </div>
            <?php endif; ?>
            </div>
    </div>
</main>

<div id="confirmRunModal" class="modal-overlay hidden">
    <div class="bg-white p-6 rounded-lg shadow-xl max-w-lg w-full mx-4">
        <div class="flex items-center">
            <div class="flex-shrink-0 bg-yellow-100 rounded-full p-2">
                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </div>
            <div class="ml-4">
                <h3 class="text-lg font-semibold text-gray-800">Konfirmasi Kalkulasi</h3>
                <p class="mt-1 text-gray-600">
                    Anda akan menjalankan kalkulasi gaji untuk periode <strong><?php echo date('F Y', mktime(0, 0, 0, $filter_bulan, 10, $filter_tahun)); ?></strong>.
                </p>
                <p class="mt-2 text-gray-600">
                    Metode PPh 21: <strong id="confirmMetodePph" class="text-gray-900"></strong>
                </p>
                <p id="confirmThrText" class="mt-2 text-blue-600 font-semibold hidden">
                    Pembayaran THR akan disertakan dalam kalkulasi ini.
                </p>
                <p class="mt-2 text-sm text-red-600">Tindakan ini akan menimpa data kalkulasi sebelumnya untuk periode ini. Lanjutkan?</p>
            </div>
        </div>
        <div class="mt-6 flex justify-end space-x-3">
            <button
                type="button"
                onclick="closeModal('confirmRunModal')"
                class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg text-sm font-semibold transition duration-200">
                Batal
            </button>
            <button
                type="button"
                id="confirmRunButton"
                onclick="submitRunForm()"
                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition duration-200">
                Ya, Jalankan
            </button>
        </div>
    </div>
</div>


<div id="potonganModal" class="modal-overlay hidden">
    <div class="bg-white p-6 rounded-lg shadow-xl max-w-lg w-full mx-4">
        <div class="flex justify-between items-center border-b pb-3 mb-4">
            <h3 class="text-xl font-semibold text-gray-800" id="potonganModalTitle">Input Potongan Lain</h3>
            <button onclick="closeModal('potonganModal')" class="text-gray-500 hover:text-gray-800">&times;</button>
        </div>
        
        <form id="potonganForm" onsubmit="submitPotongan(event)" class="space-y-4">
            <input type="hidden" id="potongan_user_id" name="user_id">
            <input type="hidden" id="potongan_tahun" name="tahun" value="<?php echo $filter_tahun; ?>">
            <input type="hidden" id="potongan_bulan" name="bulan" value="<?php echo $filter_bulan; ?>">
            
            <p class="text-sm">Karyawan: <strong id="potonganNamaKaryawan"></strong></p>
            <p class="text-sm">Periode: <strong id="potonganPeriode"></strong></p>
            
            <div id="potonganMessage" class="hidden"></div>
            
            <div id="existingPotonganList" class="space-y-2 max-h-40 overflow-y-auto p-3 bg-gray-50 border rounded-md">
                <p class="text-center text-gray-500">Memuat potongan...</p>
            </div>
            
            <hr>
            <h4 class="font-semibold">Tambah / Edit Potongan</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                 <div>
                    <label for="potongan_jenis" class="form-label">Jenis Potongan</label>
                    <input type="text" id="potongan_jenis" class="form-input" placeholder="Misal: Koperasi">
                    <p class="text-xs text-gray-500 mt-1">Jika jenis sama, akan me-replace.</p>
                </div>
                 <div>
                    <label for="potongan_jumlah" class="form-label">Jumlah (Rp)</label>
                    <input type="text" id="potongan_jumlah" class="form-input input-rupiah" placeholder="50.000">
                </div>
            </div>

            <div class="mt-6 flex justify-between items-center">
                <button type="submit" class="btn-primary-sm bg-blue-600 hover:bg-blue-700">
                    Simpan Potongan
                </button>
                <button type="button" onclick="closeModal('potonganModal')" class="btn-primary-sm btn-secondary">
                    Tutup
                </button>
            </div>
        </form>
    </div>
</div>

<div id="detailModal" class="modal-overlay hidden">
    <div class="bg-white p-6 rounded-lg shadow-xl max-w-2xl w-full mx-4 overflow-y-auto" style="max-height: 90vh;">
        <div class="flex justify-between items-center border-b pb-3 mb-4 sticky top-0 bg-white">
            <h3 class="text-xl font-semibold text-gray-800" id="detailModalTitle">Detail Perhitungan Gaji</h3>
            <button onclick="closeModal('detailModal')" class="text-gray-500 hover:text-gray-800">&times;</button>
        </div>
        
        <div id="detailModalContent" class="space-y-4">
            <p class="text-center text-gray-500">Memuat detail...</p>
        </div>

        <div class="mt-6 flex justify-end border-t pt-4 sticky bottom-0 bg-white">
            <button type="button" onclick="closeModal('detailModal')" class="btn-primary-sm btn-secondary">
                Tutup
            </button>
        </div>
    </div>
</div>

<?php
// 9. Panggil footer
require '../../includes/footer.php';
?>

<script>
    // --- Modal Konfirmasi Run ---
    const confirmRunModal = document.getElementById('confirmRunModal');
    const runCalculationForm = document.getElementById('runCalculationForm');
    const confirmMetodePph = document.getElementById('confirmMetodePph');
    const selectMetodePph = document.getElementById('metode_pph_pilihan');
    const confirmRunButton = document.getElementById('confirmRunButton');
    // === [PERUBAHAN BARU] ===
    const confirmThrText = document.getElementById('confirmThrText');
    const includeThrCheckbox = document.getElementById('include_thr');
    // === [AKHIR PERUBAHAN] ===

    function openConfirmRunModal() {
        if (confirmRunModal && selectMetodePph && confirmThrText && includeThrCheckbox) {
            // Set Teks Metode PPh
            const selectedText = selectMetodePph.options[selectMetodePph.selectedIndex].text;
            confirmMetodePph.innerText = selectedText;
            
            // === [PERUBAHAN BARU] ===
            // Tampilkan atau sembunyikan teks konfirmasi THR
            if (includeThrCheckbox.checked) {
                confirmThrText.classList.remove('hidden');
            } else {
                confirmThrText.classList.add('hidden');
            }
            // === [AKHIR PERUBAHAN] ===
            
            confirmRunModal.classList.remove('hidden');
        }
    }
    
    function submitRunForm() {
        if (runCalculationForm && confirmRunButton) {
            confirmRunButton.disabled = true;
            confirmRunButton.innerText = 'Memproses...';
            runCalculationForm.submit();
        }
    }
    
    // --- Format Rupiah Helper ---
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
        input.value = rupiah || '';
    }
    
    function cleanRupiah(value) {
        return value.replace(/[^,\d]/g, '').toString().replace(/\./g, '');
    }
    
    document.querySelectorAll('.input-rupiah').forEach(input => {
        input.addEventListener('keyup', function() { formatRupiah(this); });
    });

    // --- Modal Open/Close ---
    function closeModal(modalId) {
        document.getElementById(modalId).classList.add('hidden');
    }
    
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal-overlay')) {
            closeModal(event.target.id);
        }
    });

    // --- Logika Modal Detail (AJAX) ---
    const detailModal = document.getElementById('detailModal');
    const detailModalTitle = document.getElementById('detailModalTitle');
    const detailModalContent = document.getElementById('detailModalContent');

    function openDetailModal(historyId, nama) {
        detailModalTitle.innerText = 'Detail Gaji: ' + nama;
        detailModalContent.innerHTML = '<p class="text-center text-gray-500">Memuat detail...</p>';
        detailModal.classList.remove('hidden');
        
        fetch(`ajax_get_payroll_detail.php?history_id=${historyId}`)
            .then(response => response.text()) 
            .then(html => {
                detailModalContent.innerHTML = html;
            })
            .catch(err => {
                detailModalContent.innerHTML = `<p class="text-red-500 p-4">Gagal memuat data: ${err.message}</p>`;
            });
    }

    // --- Logika Modal Potongan (AJAX) ---
    const potonganModal = document.getElementById('potonganModal');
    const potonganNamaKaryawan = document.getElementById('potonganNamaKaryawan');
    const potonganPeriode = document.getElementById('potonganPeriode');
    const potonganUserId = document.getElementById('potongan_user_id');
    const potonganList = document.getElementById('existingPotonganList');
    const potonganMessage = document.getElementById('potonganMessage');
    const inputJenisPotongan = document.getElementById('potongan_jenis');
    const inputJumlahPotongan = document.getElementById('potongan_jumlah');
    
    const bulanIni = <?php echo $filter_bulan; ?>;
    const tahunIni = <?php echo $filter_tahun; ?>;

    function openPotonganModal(userId, nama) {
        potonganNamaKaryawan.innerText = nama;
        potonganPeriode.innerText = `<?php echo date('F', mktime(0, 0, 0, $filter_bulan, 10)); ?> ${tahunIni}`;
        potonganUserId.value = userId;
        potonganMessage.classList.add('hidden');
        inputJenisPotongan.value = '';
        inputJumlahPotongan.value = '';
        
        loadExistingPotongan(userId, tahunIni, bulanIni);
        potonganModal.classList.remove('hidden');
    }

    function loadExistingPotongan(userId, tahun, bulan) {
        potonganList.innerHTML = '<p class="text-center text-gray-500">Memuat...</p>';
        
        fetch(`ajax_get_potongan.php?user_id=${userId}&tahun=${tahun}&bulan=${bulan}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.potongan.length === 0) {
                        potonganList.innerHTML = '<p class="text-center text-gray-500 text-sm">Belum ada potongan lain untuk periode ini.</p>';
                        return;
                    }
                    
                    let html = '';
                    data.potongan.forEach(item => {
                        html += `
                            <div class="flex justify-between items-center text-sm p-2 bg-white border rounded">
                                <div>
                                    <span class="font-semibold">${item.jenis_potongan}</span>
                                    <span class="text-gray-600 ml-2">(Rp ${Number(item.jumlah).toLocaleString('id-ID')})</span>
                                </div>
                                <button type="button" class="text-red-500 hover:text-red-700 text-xs font-medium"
                                        onclick="deletePotongan(${item.id}, ${userId}, ${tahun}, ${bulan})">
                                    HAPUS
                                </button>
                            </div>
                        `;
                    });
                    potonganList.innerHTML = html;
                    
                } else {
                    potonganList.innerHTML = `<p class="text-red-500 text-center">${data.message}</p>`;
                }
            })
            .catch(err => {
                potonganList.innerHTML = `<p class="text-red-500 text-center">Error: ${err.message}</p>`;
            });
    }
    
    function submitPotongan(event) {
        event.preventDefault();
        
        const formData = new FormData();
        formData.append('action', 'add_potongan');
        formData.append('user_id', potonganUserId.value);
        formData.append('tahun', tahunIni);
        formData.append('bulan', bulanIni);
        formData.append('jenis_potongan', inputJenisPotongan.value);
        formData.append('jumlah', cleanRupiah(inputJumlahPotongan.value));

        potonganMessage.classList.remove('hidden', 'bg-red-100', 'text-red-700', 'bg-green-100', 'text-green-700');
        potonganMessage.classList.add('bg-blue-100', 'text-blue-700', 'p-3', 'rounded-md', 'text-sm');
        potonganMessage.innerText = 'Menyimpan...';

        fetch('ajax_save_potongan.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                potonganMessage.classList.remove('bg-blue-100', 'text-blue-700');
                potonganMessage.classList.add('bg-green-100', 'text-green-700');
                potonganMessage.innerText = data.message;
                
                inputJenisPotongan.value = '';
                inputJumlahPotongan.value = '';
                loadExistingPotongan(potonganUserId.value, tahunIni, bulanIni);
                recalculateSingleUser(potonganUserId.value);
            } else {
                potonganMessage.classList.remove('bg-blue-100', 'text-blue-700');
                potonganMessage.classList.add('bg-red-100', 'text-red-700');
                potonganMessage.innerText = 'Error: ' + data.message;
            }
        })
        .catch(err => {
            potonganMessage.classList.remove('bg-blue-100', 'text-blue-700');
            potonganMessage.classList.add('bg-red-100', 'text-red-700');
            potonganMessage.innerText = 'Error: ' + err.message;
        });
    }

    function deletePotongan(potonganId, userId, tahun, bulan) {
        if (!confirm('Apakah Anda yakin ingin menghapus potongan ini?')) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'delete_potongan');
        formData.append('potongan_id', potonganId);
        
        potonganMessage.classList.remove('hidden', 'bg-red-100', 'text-red-700', 'bg-green-100', 'text-green-700');
        potonganMessage.classList.add('bg-blue-100', 'text-blue-700', 'p-3', 'rounded-md', 'text-sm');
        potonganMessage.innerText = 'Menghapus...';
        
        fetch('ajax_save_potongan.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                potonganMessage.classList.remove('bg-blue-100', 'text-blue-700');
                potonganMessage.classList.add('bg-green-100', 'text-green-700');
                potonganMessage.innerText = data.message;
                loadExistingPotongan(userId, tahun, bulan);
                recalculateSingleUser(userId);
            } else {
                potonganMessage.classList.remove('bg-blue-100', 'text-blue-700');
                potonganMessage.classList.add('bg-red-100', 'text-red-700');
                potonganMessage.innerText = 'Error: ' + data.message;
            }
        })
        .catch(err => {
            potonganMessage.classList.remove('bg-blue-100', 'text-blue-700');
            potonganMessage.classList.add('bg-red-100', 'text-red-700');
            potonganMessage.innerText = 'Error: ' + err.message;
        });
    }
    
    function recalculateSingleUser(userId) {
        const runForm = new FormData(runCalculationForm); 
        runForm.append('run_calculation_single', '1'); 
        runForm.append('user_id_to_run', userId);
        runForm.delete('run_calculation'); 
        
        fetch('hr_hitung_gaji.php', {
            method: 'POST',
            body: runForm
        })
        .then(response => response.text())
        .then(text => {
            if (text.includes('Gagal menghitung gaji untuk')) {
                 potonganMessage.innerText = 'Potongan disimpan, tapi gagal kalkulasi ulang otomatis.';
                 console.error("Recalculate Error:", text);
            } else {
                 potonganMessage.innerText = 'Potongan disimpan dan gaji telah dikalkulasi ulang.';
                 setTimeout(() => {
                    window.location.href = `hr_hitung_gaji.php?filter_tahun=${tahunIni}&filter_bulan=${bulanIni}`;
                 }, 1500);
            }
        })
        .catch(err => {
             potonganMessage.innerText = 'Potongan disimpan, tapi gagal trigger kalkulasi ulang.';
        });
    }
    
</script>