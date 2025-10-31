<?php
// pranata/modules/hr/hr_slip_gaji.php

// 1. Panggil DB dan Session DULUAN
require '../../includes/db.php'; 
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Inisialisasi Variabel Awal
$errors = [];
$success_message = '';
date_default_timezone_set('Asia/Jakarta');
$admin_id_login = $_SESSION['user_id'] ?? 0; // Ambil ID HR

// 3. Tentukan Filter (Gunakan $_REQUEST agar bisa menangkap GET dan POST)
$current_db_year = 2025;
$current_db_month = 10;
$filter_tahun = (int)($_REQUEST['filter_tahun'] ?? $current_db_year);
$filter_bulan = (int)($_REQUEST['filter_bulan'] ?? $current_db_month);
$filter_user_id = (int)($_REQUEST['user_id'] ?? 0);

// === [LOGIKA POST: DIPINDAHKAN KE ATAS] ===
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'kirim_slip') {
    
    // Keamanan: Cek hak akses sebelum memproses POST
    $app_akses_post = $_SESSION['app_akses'] ?? 'Karyawan';
    if ($app_akses_post != 'Admin' && $app_akses_post != 'HR') {
         $_SESSION['flash_error'] = "Akses ditolak.";
         header("Location: hr_slip_gaji.php?filter_tahun=$filter_tahun&filter_bulan=$filter_bulan&user_id=$filter_user_id");
         exit;
    }

    $history_id_to_send = (int)($_POST['history_id'] ?? 0);
    
    if ($history_id_to_send > 0) {
        // Update status ke 'Paid'. Ini menandakan slip "terkirim" dan bisa dilihat karyawan
        $stmt_send = $conn->prepare("UPDATE payroll_history SET status = 'Paid' 
                                    WHERE id = ? AND status != 'Paid'");
        if ($stmt_send) {
            $stmt_send->bind_param("i", $history_id_to_send);
            if ($stmt_send->execute()) {
                if ($stmt_send->affected_rows > 0) {
                    $_SESSION['flash_message'] = "Slip gaji berhasil dikirim (status diubah menjadi 'Paid').";
                } else {
                    $_SESSION['flash_message'] = "Slip gaji ini statusnya sudah 'Paid'.";
                }
            } else {
                $_SESSION['flash_error'] = "Gagal mengupdate status: " . $stmt_send->error;
            }
            $stmt_send->close();
        } else {
            $_SESSION['flash_error'] = "Gagal prepare statement: " . $conn->error;
        }
    } else {
        $_SESSION['flash_error'] = "ID Slip Gaji tidak valid.";
    }
    
    // Redirect kembali ke halaman yang sama dengan filter yang aktif
    header("Location: hr_slip_gaji.php?filter_tahun=$filter_tahun&filter_bulan=$filter_bulan&user_id=$filter_user_id");
    exit; // Wajib exit setelah redirect
}
// === [AKHIR LOGIKA POST] ===


// 4. Set variabel halaman (SETELAH LOGIKA POST)
$page_title = "Cetak Slip Gaji Karyawan";
$page_active = "hr_slip_gaji";

// 5. Panggil header (SEKARANG BARU AMAN)
require '../../includes/header.php'; 

// 6. Keamanan Halaman (GET Request)
$app_akses = $_SESSION['app_akses'] ?? 'Karyawan';
$is_admin = ($app_akses == 'Admin');
$is_hr = ($app_akses == 'HR');

if (!$is_admin && !$is_hr) {
    $_SESSION['flash_message'] = "Anda tidak memiliki hak akses untuk melihat halaman ini.";
    header("Location: ". BASE_URL. "/dashboard.php");
    exit;
}

// 7. Ambil flash messages dari session (setelah redirect)
if (isset($_SESSION['flash_message'])) {
    $success_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}
if (isset($_SESSION['flash_error'])) {
    $errors[] = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// 8. Ambil data untuk Tampilan (Logika GET)
// Ambil daftar karyawan untuk dropdown filter
$users_list = [];
$sql_users = "SELECT id, nama_lengkap, nik FROM users 
              WHERE status_karyawan IN ('PKWT', 'PKWTT', 'BOD', 'BOC') AND tier != 'Admin'
              ORDER BY nama_lengkap ASC";
$result_users = $conn->query($sql_users);
if ($result_users) {
    while ($row = $result_users->fetch_assoc()) {
        $users_list[] = $row;
    }
}

// Ambil data Gaji yang SUDAH DIHITUNG dari payroll_history
$slip_list = [];
$sql_data = "SELECT 
                ph.id as history_id,
                ph.status,
                ph.take_home_pay,
                u.id as user_id,
                u.nama_lengkap,
                u.nik,
                d.nama_departemen
             FROM payroll_history ph
             JOIN users u ON ph.user_id = u.id
             LEFT JOIN departemen d ON u.id_departemen = d.id
             WHERE ph.periode_tahun = ? AND ph.periode_bulan = ?";

$params = [$filter_tahun, $filter_bulan];
$types = "ii";

if (!empty($filter_user_id)) {
    $sql_data .= " AND ph.user_id = ?";
    $params[] = $filter_user_id;
    $types .= "i";
}
$sql_data .= " ORDER BY u.nama_lengkap ASC";

$stmt_data = $conn->prepare($sql_data);
if (!$stmt_data) {
     $errors[] = "Gagal prepare query: " . $conn->error;
} else {
    $stmt_data->bind_param($types, ...$params);
    $stmt_data->execute();
    $result_data = $stmt_data->get_result();
    while ($row = $result_data->fetch_assoc()) {
        $slip_list[] = $row;
    }
    $stmt_data->close();
}

// 9. Panggil Sidebar
require '../../includes/sidebar.php';
?>

<style>
    /* Style untuk status di tabel (sama seperti hr_hitung_gaji) */
    .status-badge { padding: 0.25rem 0.625rem; font-size: 0.75rem; font-weight: 500; border-radius: 9999px; }
    .status-calculated { background-color: #E0E7FF; color: #3730A3; } /* indigo */
    .status-locked { background-color: #FEF3C7; color: #92400E; } /* amber */
    .status-paid { background-color: #D1FAE5; color: #065F46; } /* green */
    
    /* Style untuk Modal Slip Gaji */
    #slipModalContent {
        font-family: 'Arial', sans-serif;
        color: #333;
    }
    .slip-header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 15px; }
    .slip-header h4 { font-size: 1.5rem; font-weight: bold; margin: 0; }
    .slip-header p { font-size: 0.9rem; margin: 0; }
    .slip-info { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 20px; font-size: 0.9rem; margin-bottom: 15px; }
    .slip-info-label { font-weight: 600; color: #555; }
    .slip-section { margin-top: 15px; }
    .slip-section h5 { font-size: 1.1rem; font-weight: bold; background-color: #f3f4f6; padding: 5px; border-bottom: 1px solid #ddd; }
    .slip-table { width: 100%; font-size: 0.9rem; }
    .slip-table td { padding: 4px 8px; }
    .slip-table .label { width: 60%; }
    .slip-table .amount { text-align: right; font-weight: 500; }
    .slip-total { border-top: 2px solid #ccc; padding-top: 10px; margin-top: 10px; display: flex; justify-content: space-between; font-size: 1.1rem; font-weight: bold; }
</style>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Slip Gaji Karyawan</h1>

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
        <div class="card-content">
            <form action="hr_slip_gaji.php" method="GET" class="flex flex-col md:flex-row items-end gap-4">
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
                <div class="flex-grow">
                    <label for="user_id" class="form-label">Karyawan</label>
                    <select id="user_id" name="user_id" class="form-input">
                        <option value="0">-- Semua Karyawan --</option>
                        <?php foreach ($users_list as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php if ($user['id'] == $filter_user_id) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($user['nama_lengkap'] . ' (' . $user['nik'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-primary-sm bg-blue-600 hover:bg-blue-700 h-10">
                    Tampilkan Data
                </button>
                <a href="hr_slip_gaji.php" class="btn-primary-sm btn-secondary no-underline h-10 flex items-center">Reset</a>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-content">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">
                Daftar Slip Gaji (<?php echo count($slip_list); ?> Karyawan Ditemukan)
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama Karyawan</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Departemen</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Take Home Pay (Rp)</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($slip_list)): ?>
                            <tr>
                                <td colspan="5" class="px-4 py-4 text-center text-sm text-gray-500">
                                    Tidak ada data gaji yang telah dikalkulasi untuk periode ini.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($slip_list as $row): ?>
                                <tr>
                                    <td class="px-4 py-4 text-sm text-gray-900">
                                        <div class="font-semibold"><?php echo htmlspecialchars($row['nama_lengkap']); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['nik']); ?></div>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-700"><?php echo htmlspecialchars($row['nama_departemen'] ?? '-'); ?></td>
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
                                    <td class="px-4 py-4 text-sm space-x-1 whitespace-nowrap">
                                        <button type="button" 
                                                onclick="openSlipModal(<?php echo $row['history_id']; ?>)"
                                                class="btn-primary-sm bg-blue-500 hover:bg-blue-600 text-xs">
                                            Lihat Slip
                                        </button>
                                        <a href="<?php echo BASE_URL; ?>/modules/hr/print_slip.php?id=<?php echo $row['history_id']; ?>" target="_blank"
                                           class="btn-primary-sm bg-green-600 hover:bg-green-700 text-xs no-underline">
                                            Cetak
                                        </a>
                                        
                                        <?php if ($row['status'] != 'Paid'): ?>
                                            <form id="form_kirim_<?php echo $row['history_id']; ?>" action="hr_slip_gaji.php" method="POST" class="inline-block">
                                                <input type="hidden" name="action" value="kirim_slip">
                                                <input type="hidden" name="history_id" value="<?php echo $row['history_id']; ?>">
                                                
                                                <input type="hidden" name="filter_tahun" value="<?php echo $filter_tahun; ?>">
                                                <input type="hidden" name="filter_bulan" value="<?php echo $filter_bulan; ?>">
                                                <input type="hidden" name="user_id" value="<?php echo $filter_user_id; ?>">
                                                
                                                <button type="button" 
                                                        onclick="openKirimModal(<?php echo $row['history_id']; ?>, '<?php echo htmlspecialchars(addslashes($row['nama_lengkap'])); ?>')"
                                                        class="btn-primary-sm bg-purple-600 hover:bg-purple-700 text-xs">
                                                    Kirim
                                                </button>
                                            </form>
                                        <?php endif; ?>
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

<div id="kirimModal" class="modal-overlay hidden">
    <div class="bg-white p-6 rounded-lg shadow-xl max-w-lg w-full mx-4">
        <h3 class="text-xl font-semibold text-gray-800">Konfirmasi Pengiriman Slip Gaji</h3>
        <div class="my-4 text-gray-700">
            <p>Anda akan mengirim slip gaji periode <strong class="text-gray-900"><?php echo date('F Y', mktime(0, 0, 0, $filter_bulan, 10, $filter_tahun)); ?></strong> kepada:</p>
            <p class="text-lg font-bold text-blue-600 mt-2" id="kirimModalNamaKaryawan"></p>
            <p class="mt-4">Status slip akan diubah menjadi 'Paid' dan akan terlihat oleh karyawan. Lanjutkan?</p>
        </div>
        <div class="mt-6 flex justify-end space-x-3">
            <button type="button" onclick="closeModal('kirimModal')" class="btn-primary-sm btn-secondary">
                Batal
            </button>
            <button type="button" id="kirimModalConfirmButton" onclick="submitKirimForm()" class="btn-primary-sm bg-purple-600 hover:bg-purple-700">
                Ya, Kirim
            </button>
        </div>
    </div>
</div>

<div id="slipModal" class="modal-overlay hidden">
    <div class="bg-white p-0 rounded-lg shadow-xl max-w-2xl w-full mx-4 overflow-y-auto" style="max-height: 90vh;">
        <div class="flex justify-between items-center border-b p-4 sticky top-0 bg-white">
            <h3 class="text-xl font-semibold text-gray-800">Slip Gaji Karyawan</h3>
            <button onclick="closeModal('slipModal')" class="text-gray-500 hover:text-gray-800">&times;</button>
        </div>
        
        <div id="slipModalContent" class="p-6">
            <p class="text-center text-gray-500">Memuat slip gaji...</p>
        </div>

        <div class="mt-6 flex justify-end border-t p-4 sticky bottom-0 bg-white space-x-2">
            <button type"button" onclick="printSlip()" class="btn-primary-sm bg-green-600 hover:bg-green-700">
                Cetak/Simpan PDF
            </button>
            <button type="button" onclick="closeModal('slipModal')" class="btn-primary-sm btn-secondary">
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
    // --- [PERUBAHAN SCRIPT DI SINI] ---

    // Variabel global untuk menyimpan ID slip yang sedang dilihat
    let currentSlipHistoryId = 0;
    // Variabel global untuk menyimpan ID form yang akan disubmit
    let formToSubmitId = null;

    // --- Modal Open/Close ---
    function closeModal(modalId) {
        document.getElementById(modalId).classList.add('hidden');
        if (modalId === 'slipModal') {
            currentSlipHistoryId = 0; // Reset ID saat modal ditutup
        }
        if (modalId === 'kirimModal') {
            formToSubmitId = null; // Reset ID form
        }
    }
    
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal-overlay')) {
            closeModal(event.target.id);
        }
    });

    // --- Logika Modal Konfirmasi Kirim (BARU) ---
    function openKirimModal(historyId, namaKaryawan) {
        const modal = document.getElementById('kirimModal');
        if (!modal) return;
        
        document.getElementById('kirimModalNamaKaryawan').innerText = namaKaryawan;
        formToSubmitId = 'form_kirim_' + historyId; // Simpan ID form yang akan di-submit
        
        modal.classList.remove('hidden');
    }

    function submitKirimForm() {
        if (formToSubmitId) {
            const form = document.getElementById(formToSubmitId);
            if (form) {
                // Tambahkan indikator loading
                const confirmButton = document.getElementById('kirimModalConfirmButton');
                confirmButton.disabled = true;
                confirmButton.innerText = 'Mengirim...';
                form.submit();
            }
        }
    }

    // --- Logika Modal Slip Gaji ---
    const slipModal = document.getElementById('slipModal');
    const slipModalContent = document.getElementById('slipModalContent');

    function openSlipModal(historyId) {
        // Simpan historyId ke variabel global
        currentSlipHistoryId = historyId; 
        
        slipModalContent.innerHTML = '<p class="text-center text-gray-500">Memuat slip gaji...</p>';
        slipModal.classList.remove('hidden');
        
        // Panggil file AJAX yang baru kita buat
        fetch(`ajax_get_slip_detail.php?id=${historyId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Gagal mengambil data slip gaji. Status: ' + response.status);
                }
                return response.text(); // Ambil sebagai HTML
            })
            .then(html => {
                // Masukkan HTML ke dalam modal
                slipModalContent.innerHTML = html;
            })
            .catch(err => {
                slipModalContent.innerHTML = `<p class="text-red-500 p-4"><b>Error:</b> ${err.message}</p>`;
                currentSlipHistoryId = 0; // Reset jika gagal
            });
    }
    
    function printSlip() {
        if (currentSlipHistoryId > 0) {
            // Buka halaman print_slip.php di tab baru menggunakan ID yang tersimpan
            const printUrl = `<?php echo BASE_URL; ?>/modules/hr/print_slip.php?id=${currentSlipHistoryId}`;
            window.open(printUrl, '_blank');
        } else {
            alert('Gagal mencetak. ID Slip Gaji tidak ditemukan.');
        }
    }
    // --- [AKHIR PERUBAHAN SCRIPT] ---
</script>