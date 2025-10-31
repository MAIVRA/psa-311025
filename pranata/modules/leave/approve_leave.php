<?php
// modules/leave/approve_leave.php

// 1. Set variabel khusus untuk halaman ini
$page_title = "Persetujuan Pengajuan Cuti";
$page_active = "approve_leave";

// === [PATH DIPERBAIKI] ===
require '../../includes/header.php';
// === [AKHIR PERUBAHAN] ===

// 3. Ambil ID user yang login (approver)
$approver_user_id = $_SESSION['user_id'];

// 4. Inisialisasi variabel
$error_message = '';
$success_message = '';
$pending_requests = [];

// 5. Buka koneksi baru
$conn_db = new mysqli($servername, $username, $password, $dbname);
if ($conn_db->connect_error) {
    die("Koneksi Gagal: " . $conn_db->connect_error);
}
$conn_db->set_charset("utf8mb4");

// 6. Logika saat form persetujuan/penolakan di-submit (method POST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['request_id']) && isset($_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action']; // 'approve' or 'reject'
    $current_year = date('Y');

    // Mulai Transaksi
    $conn_db->begin_transaction();
    try {
        // Ambil detail request yang akan diproses
        // === [PERBAIKAN LOGIKA STATUS] ===
        $stmt_get_req = $conn_db->prepare("
            SELECT lr.user_id, lr.jenis_cuti, lr.jumlah_hari, lr.approved_by_id, u.nama_lengkap
            FROM leave_requests lr
            JOIN users u ON lr.user_id = u.id
            WHERE lr.id = ? AND lr.status = 'Pending'
        "); // Mengganti 'Pending Approval' menjadi 'Pending'
         // === [AKHIR PERBAIKAN] ===

        if (!$stmt_get_req) throw new Exception("Gagal prepare get request: " . $conn_db->error);
        $stmt_get_req->bind_param("i", $request_id);
        $stmt_get_req->execute();
        $result_req = $stmt_get_req->get_result();
        $request_data = $result_req->fetch_assoc();
        $stmt_get_req->close();

        if (!$request_data) {
            throw new Exception("Pengajuan cuti tidak ditemukan atau sudah diproses.");
        }

        // Validasi apakah user yg login berhak approve request ini
        // [PERBAIKAN LOGIKA] Seharusnya mengecek 'approved_by_id' (yang di-set saat pengajuan)
        if ($request_data['approved_by_id'] != $approver_user_id) {
             // Cek tambahan jika approver_id NULL (misal: admin/HR override)
             if ($tier != 'Admin') { // Asumsi Admin boleh override
                 throw new Exception("Anda tidak berhak memproses pengajuan ini.");
             }
        }

        // Tentukan status baru
        $new_status = ($action == 'approve') ? 'Approved' : 'Rejected';

        // LANGKAH 1: Update status di leave_requests
        $stmt_update_status = $conn_db->prepare("UPDATE leave_requests SET status = ?, approved_by_id = ? WHERE id = ?");
        if (!$stmt_update_status) throw new Exception("Gagal prepare update status: " . $conn_db->error);
        $stmt_update_status->bind_param("sii", $new_status, $approver_user_id, $request_id);
        if (!$stmt_update_status->execute()) {
            throw new Exception("Gagal update status pengajuan: " . $stmt_update_status->error);
        }
        $stmt_update_status->close();

        // LANGKAH 2: Jika disetujui DAN jenisnya Cuti Tahunan, kurangi saldo
        if ($new_status == 'Approved' && $request_data['jenis_cuti'] == 'Cuti tahunan') {
            $jumlah_hari_cuti = (int)$request_data['jumlah_hari'];
            $user_id_cuti = (int)$request_data['user_id'];

            // Query update saldo, pastikan tidak minus
            $stmt_update_saldo = $conn_db->prepare("
                UPDATE saldo_cuti_tahunan
                SET sisa_cuti_aktual = GREATEST(0, sisa_cuti_aktual - ?)
                WHERE user_id = ? AND tahun = ?
            ");
             if (!$stmt_update_saldo) throw new Exception("Gagal prepare update saldo: " . $conn_db->error);
             $stmt_update_saldo->bind_param("iis", $jumlah_hari_cuti, $user_id_cuti, $current_year);
             if (!$stmt_update_saldo->execute()) {
                 throw new Exception("Gagal mengurangi saldo cuti: " . $stmt_update_saldo->error);
             }
             if ($stmt_update_saldo->affected_rows === 0) {
                 // Ini BUKAN error, tapi peringatan. Bisa jadi record saldo belum ada.
                 error_log("Peringatan: Gagal mengurangi saldo cuti untuk user ID $user_id_cuti tahun $current_year. Record mungkin belum ada.");
             }
             $stmt_update_saldo->close();
        }

        // Commit Transaksi
        $conn_db->commit();

        // TODO: Tambahkan logika notifikasi di sini nanti

        $success_message = "Pengajuan cuti dari " . htmlspecialchars($request_data['nama_lengkap']) . " berhasil di-" . strtolower($new_status) . ".";

    } catch (Exception $e) {
        $conn_db->rollback();
        $error_message = "Error: " . $e->getMessage();
    }

} // Akhir dari method POST

// 7. Logika GET (Mengambil daftar pengajuan yang perlu diproses)
try {
    // === [PERBAIKAN LOGIKA STATUS] ===
    $sql_get_pending = "
        SELECT lr.*, u.nama_lengkap, u.nik
        FROM leave_requests lr
        JOIN users u ON lr.user_id = u.id
        WHERE lr.status = 'Pending' "; // Mengganti 'Pending Approval' menjadi 'Pending'
    
    // Admin & HR (asumsi) bisa melihat semua yg pending
    // Manager/Supervisor/Direksi hanya melihat yg ditugaskan ke mereka
    if ($tier != 'Admin') { 
        $sql_get_pending .= " AND lr.approved_by_id = ? ";
    }
    
    $sql_get_pending .= " ORDER BY lr.created_at ASC";
    
    $stmt_get_pending = $conn_db->prepare($sql_get_pending);
    if (!$stmt_get_pending) throw new Exception("Gagal query pending requests: " . $conn_db->error);
    
    if ($tier != 'Admin') {
        $stmt_get_pending->bind_param("i", $approver_user_id);
    }
    // === [AKHIR PERBAIKAN] ===

    $stmt_get_pending->execute();
    $result_pending = $stmt_get_pending->get_result();
    while ($row = $result_pending->fetch_assoc()) {
        $pending_requests[] = $row;
    }
    $stmt_get_pending->close();

} catch (Exception $e) {
     $error_message = "Error mengambil daftar pengajuan: " . $e->getMessage();
}

// Tutup koneksi jika masih terbuka
if ($conn_db->ping()) {
    $conn_db->close();
}

// 8. Include template sidebar
// === [PATH DIPERBAIKI] ===
require '../../includes/sidebar.php';
// === [AKHIR PERUBAHAN] ===
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">

    <h1 class="text-3xl font-bold text-gray-800 mb-6"><?php echo $page_title; ?></h1>

     <?php if (!empty($error_message)): ?>
         <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4" role="alert">
             <?php echo htmlspecialchars($error_message); ?>
         </div>
     <?php endif; ?>
     <?php if (!empty($success_message)): ?>
         <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative mb-4" role="alert">
             <?php echo htmlspecialchars($success_message); ?>
         </div>
     <?php endif; ?>


    <div class="card">
         <div class="card-header"><h2 class="text-xl font-semibold text-gray-800">Daftar Pengajuan Cuti Menunggu Persetujuan</h2></div>
        <div class="card-content overflow-x-auto">
            <table class="w-full min-w-max">
                <thead>
                    <tr class="bg-gray-100 text-left text-sm font-semibold text-gray-600 uppercase">
                        <th class="py-3 px-4">Nama Karyawan</th>
                        <th class="py-3 px-4">NIK</th>
                        <th class="py-3 px-4">Jenis Cuti</th>
                        <th class="py-3 px-4">Tanggal</th>
                        <th class="py-3 px-4">Durasi</th>
                        <th class="py-3 px-4">Keterangan</th>
                        <th class="py-3 px-4">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700 text-sm">
                    <?php if (empty($pending_requests)): ?>
                        <tr>
                            <td colspan="7" class="py-4 px-4 text-center text-gray-500">Tidak ada pengajuan cuti yang menunggu persetujuan.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pending_requests as $req): ?>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-3 px-4 font-medium"><?php echo htmlspecialchars($req['nama_lengkap']); ?></td>
                                <td class="py-3 px-4"><?php echo htmlspecialchars($req['nik']); ?></td>
                                <td class="py-3 px-4"><?php echo htmlspecialchars($req['jenis_cuti']); ?></td>
                                <td class="py-3 px-4 whitespace-nowrap"><?php echo date('d M Y', strtotime($req['tanggal_mulai'])); ?> - <?php echo date('d M Y', strtotime($req['tanggal_selesai'])); ?></td>
                                <td class="py-3 px-4"><?php echo $req['jumlah_hari']; ?> Hari</td>
                                <td class="py-3 px-4 text-xs max-w-xs truncate" title="<?php echo htmlspecialchars($req['keterangan']); ?>"><?php echo htmlspecialchars($req['keterangan']); ?></td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    
                                    <form action="<?php echo BASE_URL; ?>/modules/leave/approve_leave.php" method="POST" class="inline-block mr-2" onsubmit="return confirm('Apakah Anda yakin ingin menyetujui pengajuan ini?');">
                                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="text-green-600 hover:text-green-800 font-medium text-xs bg-green-100 px-2 py-1 rounded">Setujui</button>
                                    </form>
                                    
                                    <form action="<?php echo BASE_URL; ?>/modules/leave/approve_leave.php" method="POST" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menolak pengajuan ini?');">
                                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                         <button type="submit" class="text-red-600 hover:text-red-800 font-medium text-xs bg-red-100 px-2 py-1 rounded">Tolak</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>
<?php
// 9. Include template footer
// === [PATH DIPERBAIKI] ===
require '../../includes/footer.php';
// === [AKHIR PERUBAHAN] ===
?>