<?php
// pranata/modules/leave/request_leave.php

// 1. Set variabel khusus untuk halaman ini
$page_title = "Pengajuan Cuti";
$page_active = "request_leave";

// 2. Panggil db.php dan lakukan Pengecekan Hak Akses
require '../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ". BASE_URL. "/index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$errors = [];
$success_message = '';

// Tetapkan tahun berjalan aplikasi secara manual
$current_year = '2025';


// 3. Logika POST (Saat form disubmit)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajukan_cuti'])) {
    $jenis_cuti = $_POST['jenis_cuti'] ?? 'Cuti Tahunan';
    $tanggal_mulai = $_POST['tanggal_mulai'] ?? null;
    $tanggal_selesai = $_POST['tanggal_selesai'] ?? null;
    $keterangan = trim($_POST['keterangan'] ?? '');

    if (empty($tanggal_mulai) || empty($tanggal_selesai) || empty($keterangan)) {
        $errors[] = "Semua field wajib diisi.";
    } elseif ($tanggal_selesai < $tanggal_mulai) {
        $errors[] = "Tanggal selesai tidak boleh sebelum tanggal mulai.";
    } else {
        $tgl1 = new DateTime($tanggal_mulai);
        $tgl2 = new DateTime($tanggal_selesai);
        $diff = $tgl2->diff($tgl1);
        $jumlah_hari = $diff->days + 1;
        
        $current_year_post = $current_year;
        
        $jatah_cuti_tahunan_post = 12;
        $stmt_jatah_post = $conn->prepare("SELECT jumlah_cuti FROM users WHERE id = ?");
        $stmt_jatah_post->bind_param("i", $user_id);
        $stmt_jatah_post->execute();
        $result_jatah_post = $stmt_jatah_post->get_result();
        if($row_jatah_post = $result_jatah_post->fetch_assoc()) {
            $jatah_cuti_tahunan_post = $row_jatah_post['jumlah_cuti'];
        }
        $stmt_jatah_post->close();

        $total_cuti_massal_weekday_post = 0;
        $stmt_massal_post = $conn->prepare("SELECT tanggal_cuti FROM collective_leave WHERE status = 'Approved' AND tahun = ?");
        $stmt_massal_post->bind_param("s", $current_year_post);
        $stmt_massal_post->execute();
        $result_massal_post = $stmt_massal_post->get_result();
        while ($row_massal_post = $result_massal_post->fetch_assoc()) {
            if (date('N', strtotime($row_massal_post['tanggal_cuti'])) < 6) {
                $total_cuti_massal_weekday_post++;
            }
        }
        $stmt_massal_post->close();
        
        $cuti_diambil_post = 0;
        $stmt_diambil_post = $conn->prepare("SELECT SUM(jumlah_hari) as total_diambil FROM leave_requests WHERE user_id = ? AND status = 'Approved' AND YEAR(tanggal_mulai) = ? AND jenis_cuti = 'Cuti Tahunan'");
        $stmt_diambil_post->bind_param("is", $user_id, $current_year_post);
        $stmt_diambil_post->execute();
        $result_diambil_post = $stmt_diambil_post->get_result();
        $row_diambil_post = $result_diambil_post->fetch_assoc();
        $cuti_diambil_post = $row_diambil_post['total_diambil'] ?? 0;
        $stmt_diambil_post->close();

        $jatah_efektif_post = $jatah_cuti_tahunan_post - $total_cuti_massal_weekday_post;
        $sisa_cuti_post = $jatah_efektif_post - $cuti_diambil_post;

        if ($jenis_cuti == 'Cuti Tahunan' && $jumlah_hari > $sisa_cuti_post) {
            $errors[] = "Pengajuan cuti ($jumlah_hari hari) melebihi sisa cuti tahunan Anda ($sisa_cuti_post hari).";
        } else {
            $stmt_insert = $conn->prepare(
                "INSERT INTO leave_requests (user_id, jenis_cuti, tanggal_mulai, tanggal_selesai, jumlah_hari, keterangan, status) 
                 VALUES (?, ?, ?, ?, ?, ?, 'Pending')"
            );
            $stmt_insert->bind_param("isssis", $user_id, $jenis_cuti, $tanggal_mulai, $tanggal_selesai, $jumlah_hari, $keterangan);
            
            if ($stmt_insert->execute()) {
                $success_message = "Pengajuan cuti Anda telah berhasil dikirim dan menunggu persetujuan atasan.";
            } else {
                $errors[] = "Gagal menyimpan pengajuan: ". $stmt_insert->error;
            }
            $stmt_insert->close();
        }
    }
}


// 4. Logic untuk mengambil sisa cuti (UNTUK TAMPILAN)
// $current_year sudah diset di atas (2025)

// 1. Ambil jatah cuti default
$jatah_cuti_tahunan = 12;
$stmt_jatah = $conn->prepare("SELECT jumlah_cuti FROM users WHERE id = ?");
$stmt_jatah->bind_param("i", $user_id);
if ($stmt_jatah->execute()) {
    $result_jatah = $stmt_jatah->get_result();
    if($row_jatah = $result_jatah->fetch_assoc()) {
        $jatah_cuti_tahunan = $row_jatah['jumlah_cuti'];
    }
}
$stmt_jatah->close();

// 2. Hitung total Cuti Massal
$total_cuti_massal_weekday = 0;
$stmt_massal = $conn->prepare("SELECT tanggal_cuti FROM collective_leave WHERE status = 'Approved' AND tahun = ?");
$stmt_massal->bind_param("s", $current_year);
if ($stmt_massal->execute()) {
    $result_massal = $stmt_massal->get_result();
    while ($row_massal = $result_massal->fetch_assoc()) {
        if (date('N', strtotime($row_massal['tanggal_cuti'])) < 6) {
            $total_cuti_massal_weekday++;
        }
    }
}
$stmt_massal->close();

// 3. Hitung cuti yang sudah diambil
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

// 4. Hitung Jatah Efektif dan Sisa Cuti
$jatah_efektif = $jatah_cuti_tahunan - $total_cuti_massal_weekday;
$sisa_cuti = $jatah_efektif - $cuti_diambil;

// 5. Ambil riwayat pengajuan cuti (5 terakhir)
$riwayat_cuti = [];
$sql_riwayat = "SELECT * FROM leave_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
$stmt_riwayat = $conn->prepare($sql_riwayat);
$stmt_riwayat->bind_param("i", $user_id);
if ($stmt_riwayat->execute()) {
    $result_riwayat = $stmt_riwayat->get_result();
    while($row = $result_riwayat->fetch_assoc()) {
        $riwayat_cuti[] = $row;
    }
}
$stmt_riwayat->close();

// 6. Ambil daftar Cuti Massal untuk Modal
$daftar_cuti_massal = [];
$sql_list_massal = "SELECT tanggal_cuti, keterangan FROM collective_leave WHERE status = 'Approved' AND tahun = ? ORDER BY tanggal_cuti ASC";
$stmt_list_massal = $conn->prepare($sql_list_massal);
$stmt_list_massal->bind_param("s", $current_year);
if ($stmt_list_massal->execute()) {
    $result_list_massal = $stmt_list_massal->get_result();
    while($row = $result_list_massal->fetch_assoc()) {
        $daftar_cuti_massal[] = $row;
    }
}
$stmt_list_massal->close();


// 7. Panggil header.php
require '../../includes/header.php';

// 8. Panggil sidebar.php
require '../../includes/sidebar.php';
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Pengajuan Cuti</h1>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4 max-w-lg" role="alert">
            <strong class="font-bold">Error!</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($success_message)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative mb-4 max-w-lg" role="alert">
            <strong class="font-bold">Sukses!</strong>
            <span class="block sm:inline"><?php echo htmlspecialchars($success_message); ?></span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <div class="lg:col-span-2">
            <div class="card">
                <form action="request_leave.php" method="POST" class="card-content space-y-5">
                    
                    <h3 class="text-xl font-semibold text-gray-800 border-b pb-2">Form Pengajuan Cuti</h3>
                    
                    <div>
                        <label for="jenis_cuti" class="form-label">Jenis Cuti <span class="text-red-500">*</span></label>
                        <select id="jenis_cuti" name="jenis_cuti" class="form-input">
                            <option value="Cuti Tahunan">Cuti Tahunan</option>
                            <option value="Cuti Sakit">Cuti Sakit (Dengan Surat Dokter)</option>
                            <option value="Cuti Melahirkan">Cuti Melahirkan</option>
                            <option value="Cuti Alasan Penting">Cuti Alasan Penting</option>
                            <option value="Izin">Izin (Potong Gaji)</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="tanggal_mulai" class="form-label">Tanggal Mulai <span class="text-red-500">*</span></label>
                            <input type="date" id="tanggal_mulai" name="tanggal_mulai" class="form-input" required>
                        </div>
                        <div>
                            <label for="tanggal_selesai" class="form-label">Tanggal Selesai <span class="text-red-500">*</span></label>
                            <input type="date" id="tanggal_selesai" name="tanggal_selesai" class="form-input" required>
                        </div>
                    </div>

                    <div>
                         <label for="keterangan" class="form-label">Keterangan / Alasan Cuti <span class="text-red-500">*</span></label>
                         <textarea id="keterangan" name="keterangan" rows="4" class="form-input" placeholder="Contoh: Keperluan keluarga / Liburan" required></textarea>
                    </div>

                    <div class="pt-4 border-t">
                        <button type="submit" name="ajukan_cuti" class="w-full btn-primary-sm bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold text-lg transition duration-200">
                            Ajukan Cuti Sekarang
                        </button>
                    </div>

                </form>
            </div>
        </div>

        <div class="lg:col-span-1 space-y-6">
            
            <div class="card">
                <div class="card-header flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-800">Sisa Cuti Tahunan <?php echo $current_year; ?></h3>
                    <button type="button" onclick="openModal('cutiMassalModal')" class="btn-primary-sm bg-gray-500 hover:bg-gray-600 text-xs">
                        Cek Cuti Massal
                    </button>
                </div>
                <div class="card-content space-y-3">
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600">Jatah Cuti Tahunan Awal</span>
                        <span class="font-semibold text-gray-800"><?php echo $jatah_cuti_tahunan; ?> Hari</span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600">Dikurangi Cuti Massal</span>
                        <span class="font-semibold text-red-600">- <?php echo $total_cuti_massal_weekday; ?> Hari</span>
                    </div>
                    <hr>
                    <div class="flex justify-between items-center text-sm">
                        <span class="font-bold text-gray-700">Jatah Cuti Efektif</span>
                        <span class="font-bold text-gray-900"><?php echo $jatah_efektif; ?> Hari</span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600">Cuti Telah Diambil</span>
                        <span class="font-semibold text-red-600">- <?php echo $cuti_diambil; ?> Hari</span>
                    </div>
                    <hr>
                    <div class="flex justify-between items-center text-lg mt-2">
                        <span class="font-bold text-gray-900">SISA CUTI ANDA</span>
                        <span class="font-bold text-blue-600"><?php echo $sisa_cuti; ?> Hari</span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="text-lg font-semibold text-gray-800">Riwayat 5 Pengajuan Terakhir</h3>
                </div>
                <div class="card-content divide-y divide-gray-200">
                    <?php if (empty($riwayat_cuti)): ?>
                        <p class="text-sm text-gray-500 text-center py-4">Belum ada riwayat pengajuan.</p>
                    <?php else: ?>
                        <?php foreach($riwayat_cuti as $riwayat): ?>
                        <div class="py-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($riwayat['jenis_cuti']); ?> (<?php echo $riwayat['jumlah_hari']; ?> hari)</span>
                                <?php 
                                $status = htmlspecialchars($riwayat['status']);
                                $color = 'bg-gray-100 text-gray-800'; // Default
                                if ($status == 'Approved') $color = 'bg-green-100 text-green-800';
                                if ($status == 'Rejected') $color = 'bg-red-100 text-red-800';
                                if ($status == 'Pending') $color = 'bg-yellow-100 text-yellow-800';
                                echo "<span class='px-2 inline-flex text-xs leading-5 font-semibold rounded-full $color'>$status</span>";
                                ?>
                            </div>
                            <p class="text-sm text-gray-600 mt-1"><?php echo date('d M Y', strtotime($riwayat['tanggal_mulai'])); ?> s/d <?php echo date('d M Y', strtotime($riwayat['tanggal_selesai'])); ?></p>
                            <p class="text-xs text-gray-500 mt-1">Diajukan: <?php echo date('d M Y H:i', strtotime($riwayat['created_at'])); ?></p>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</main>

<div id="cutiMassalModal" class="modal-overlay hidden">
    <div class="bg-white p-6 rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="flex justify-between items-center border-b pb-3 mb-4">
            <h3 class="text-xl font-semibold text-gray-800">Daftar Cuti Massal <?php echo $current_year; ?></h3>
            <button onclick="closeModal('cutiMassalModal')" class="text-gray-500 hover:text-gray-800">&times;</button>
        </div>
        
        <div class="max-h-60 overflow-y-auto">
            <?php if (empty($daftar_cuti_massal)): ?>
                <p class="text-gray-600 text-center py-4">Belum ada cuti massal yang ditetapkan untuk tahun ini.</p>
            <?php else: ?>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($daftar_cuti_massal as $cuti): ?>
                        <tr>
                            <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo date('d F Y', strtotime($cuti['tanggal_cuti'])); ?></td>
                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($cuti['keterangan']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="mt-6 flex justify-end">
            <button type="button" onclick="closeModal('cutiMassalModal')" class="btn-primary-sm btn-secondary">
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
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('hidden');
        }
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('hidden');
        }
    }
    
    // Tutup modal jika klik di luar area modal
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeModal(modal.id);
            }
        });
    });
</script>