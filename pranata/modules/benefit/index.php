<?php
// pranata/modules/benefit/index.php

// 1. Set variabel khusus untuk halaman ini
$page_title = "Benefit Karyawan";
$page_active = "benefit"; // Sesuai dengan sidebar.php

// 2. Panggil header
require '../../includes/header.php'; // $conn, $_SESSION, $user_id tersedia

// 3. Inisialisasi Variabel
$master_gaji = null;
$daftar_cuti_massal = [];
$daftar_slip_gaji = [];
$daftar_bukti_potong = [];
$errors = [];

// Tetapkan tahun berjalan (sesuai data di DB)
$current_year = '2025';

// Helper function
if (!function_exists('formatRupiah')) {
    function formatRupiah($number, $prefix = 'Rp ') {
        return $prefix . number_format($number, 0, ',', '.');
    }
}

try {
    // --- 4.1. Ambil Data Master Gaji ---
    $stmt_gaji = $conn->prepare("SELECT * FROM payroll_master_gaji WHERE user_id = ?");
    if ($stmt_gaji) {
        $stmt_gaji->bind_param("i", $user_id);
        $stmt_gaji->execute();
        $result_gaji = $stmt_gaji->get_result();
        if ($result_gaji->num_rows > 0) {
            $master_gaji = $result_gaji->fetch_assoc();
        }
        $stmt_gaji->close();
    } else {
        $errors[] = "Gagal mengambil data master gaji: " . $conn->error;
    }

    // --- 4.2. Ambil Daftar Cuti Massal ---
    $stmt_cuti = $conn->prepare("SELECT tanggal_cuti, keterangan FROM collective_leave WHERE status = 'Approved' AND tahun = ? ORDER BY tanggal_cuti ASC");
    if ($stmt_cuti) {
        $stmt_cuti->bind_param("s", $current_year);
        $stmt_cuti->execute();
        $result_cuti = $stmt_cuti->get_result();
        while ($row = $result_cuti->fetch_assoc()) {
            $daftar_cuti_massal[] = $row;
        }
        $stmt_cuti->close();
    } else {
        $errors[] = "Gagal mengambil daftar cuti massal: " . $conn->error;
    }

    // --- 4.3. Ambil Daftar Slip Gaji (Status 'Paid') ---
    // (Logika ini sudah benar, 'Paid' diatur oleh HR)
    $stmt_slip = $conn->prepare("SELECT id, periode_tahun, periode_bulan, take_home_pay, status 
                                FROM payroll_history 
                                WHERE user_id = ? AND status = 'Paid' 
                                ORDER BY periode_tahun DESC, periode_bulan DESC");
    if ($stmt_slip) {
        $stmt_slip->bind_param("i", $user_id);
        $stmt_slip->execute();
        $result_slip = $stmt_slip->get_result();
        while ($row = $result_slip->fetch_assoc()) {
            $daftar_slip_gaji[] = $row;
        }
        $stmt_slip->close();
    } else {
        $errors[] = "Gagal mengambil riwayat slip gaji: " . $conn->error;
    }

    // --- 4.4. Ambil Daftar Bukti Potong (Status 'Sent') ---
    // [PERBAIKAN LOGIKA]
    // Mengkueri tabel payroll_bupot_status yang baru dibuat
    $stmt_bupot_check = $conn->prepare(
        "SELECT tahun, status, sent_at 
         FROM payroll_bupot_status 
         WHERE user_id = ? AND status = 'Sent' 
         ORDER BY tahun DESC"
    );
    if ($stmt_bupot_check) {
        $stmt_bupot_check->bind_param("i", $user_id);
        $stmt_bupot_check->execute();
        $result_bupot = $stmt_bupot_check->get_result();
        while ($row = $result_bupot->fetch_assoc()) {
            $daftar_bukti_potong[] = [
                'tahun' => $row['tahun'],
                'keterangan' => 'Bukti Potong 1721-A1 Tahun ' . $row['tahun'],
                'status' => 'Terkirim' // (Berasal dari status 'Sent')
            ];
        }
        $stmt_bupot_check->close();
    } else {
         $errors[] = "Gagal memeriksa riwayat bukti potong: " . $conn->error;
    }
    // --- [AKHIR PERBAIKAN LOGIKA] ---


} catch (Exception $e) {
    $errors[] = $e->getMessage();
}

// 5. Panggil Sidebar
require '../../includes/sidebar.php';
?>

<style>
    /* Style khusus untuk halaman ini */
    .benefit-grid {
        display: grid;
        grid-template-columns: repeat(1, 1fr);
    }
    .benefit-label {
        font-size: 0.875rem; /* text-sm */
        font-weight: 500; /* font-medium */
        color: #6b7280; /* gray-500 */
    }
    .benefit-data {
        font-size: 1rem; /* text-base */
        font-weight: 600; /* font-semibold */
        color: #1f2937; /* gray-800 */
    }
    .benefit-data-sm {
        font-size: 0.875rem;
        font-weight: 500;
        color: #374151;
    }
    .benefit-card {
        background-color: white;
        border-radius: 0.5rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        overflow: hidden;
    }
    .benefit-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid #e5e7eb;
    }
    .benefit-content {
        padding: 1.5rem;
    }
    .benefit-table {
        min-width: 100%;
        divide-y: divide-gray-200;
    }
    .benefit-table th {
        padding: 0.75rem 1.5rem;
        text-align: left;
        font-size: 0.75rem;
        font-weight: 500;
        color: #6b7280;
        text-transform: uppercase;
        background-color: #f9fafb;
    }
    .benefit-table td {
        padding: 1rem 1.5rem;
        font-size: 0.875rem;
        color: #374151;
        white-space: nowrap;
    }
    .benefit-table tbody tr:nth-child(even) {
        background-color: #f9fafb;
    }
</style>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Benefit Karyawan</h1>

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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <div class="lg:col-span-2 space-y-6">
            
            <div class="benefit-card">
                <div class="benefit-header">
                    <h3 class="text-xl font-semibold text-gray-800">Informasi Gaji</h3>
                </div>
                <div class="benefit-content">
                    <?php if (!$master_gaji): ?>
                        <p class="text-gray-500 text-center">Data master gaji Anda belum di-input oleh HR.</p>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                            <div class="md:col-span-2 border-b pb-4">
                                <span class="benefit-label">Gaji Pokok</span>
                                <p class="benefit-data text-2xl text-blue-600"><?php echo formatRupiah($master_gaji['gaji_pokok']); ?></p>
                            </div>
                            
                            <div>
                                <span class="benefit-label">Tunjangan Tetap (Bulanan)</span>
                                <div class="mt-2 space-y-1">
                                    <div class="flex justify-between">
                                        <span class="benefit-data-sm">Tunjangan Jabatan</span>
                                        <span class="benefit-data-sm font-medium"><?php echo formatRupiah($master_gaji['tunj_jabatan']); ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="benefit-data-sm">Tunjangan Kesehatan</span>
                                        <span class="benefit-data-sm font-medium"><?php echo formatRupiah($master_gaji['tunj_kesehatan']); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <span class="benefit-label">Tunjangan Tidak Tetap (Rate)</span>
                                <div class="mt-2 space-y-1">
                                    <div class="flex justify-between">
                                        <span class="benefit-data-sm">Tunjangan Transport (per hari WFO)</span>
                                        <span class="benefit-data-sm font-medium"><?php echo formatRupiah($master_gaji['tunj_transport']); ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="benefit-data-sm">Tunjangan Makan (per hari hadir)</span>
                                        <span class="benefit-data-sm font-medium"><?php echo formatRupiah($master_gaji['tunj_makan']); ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="benefit-data-sm">Tunjangan Rumah (per bulan)</span>
                                        <span class="benefit-data-sm font-medium"><?php echo formatRupiah($master_gaji['tunj_rumah']); ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="benefit-data-sm">Tunjangan Pendidikan (per bulan)</span>
                                        <span class="benefit-data-sm font-medium"><?php echo formatRupiah($master_gaji['tunj_pendidikan']); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="md:col-span-2 border-t pt-4 mt-2">
                                <span class="benefit-label">Potongan Wajib</span>
                                <div class="mt-2 space-y-1 text-sm text-gray-700">
                                    <p>Potong PPh 21: <span class="font-semibold"><?php echo $master_gaji['pot_pph'] ? 'Ya' : 'Tidak'; ?></span></p>
                                    <p>Potong BPJS Kesehatan: <span class="font-semibold"><?php echo $master_gaji['pot_bpjs_kesehatan'] ? 'Ya' : 'Tidak'; ?></span></p>
                                    <p>Potong BPJS Ketenagakerjaan: <span class="font-semibold"><?php echo $master_gaji['pot_bpjs_ketenagakerjaan'] ? 'Ya' : 'Tidak'; ?></span></p>
                                </div>
                            </div>

                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="benefit-card">
                <div class="benefit-header">
                    <h3 class="text-xl font-semibold text-gray-800">Daftar Cuti Massal (<?php echo $current_year; ?>)</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="benefit-table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (empty($daftar_cuti_massal)): ?>
                                <tr>
                                    <td colspan="2" class="text-center text-gray-500">
                                        Belum ada data cuti massal yang ditetapkan untuk tahun ini.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($daftar_cuti_massal as $cuti): ?>
                                    <tr>
                                        <td><?php echo date('d F Y', strtotime($cuti['tanggal_cuti'])); ?></td>
                                        <td><?php echo htmlspecialchars($cuti['keterangan']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <div class="lg:col-span-1 space-y-6">

            <div class="benefit-card">
                <div class="benefit-header">
                    <h3 class="text-xl font-semibold text-gray-800">Arsip Slip Gaji</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="benefit-table">
                        <thead>
                            <tr>
                                <th>Periode</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                             <?php if (empty($daftar_slip_gaji)): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-gray-500">
                                        Belum ada slip gaji yang dikirim oleh HR.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($daftar_slip_gaji as $slip): ?>
                                    <tr>
                                        <td class="font-medium"><?php echo date('F Y', mktime(0, 0, 0, $slip['periode_bulan'], 1, $slip['periode_tahun'])); ?></td>
                                        <td>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                Terkirim
                                            </span>
                                        </td>
                                        <td>
                                            <a href="<?php echo BASE_URL; ?>/modules/hr/print_slip.php?id=<?php echo $slip['id']; ?>" target="_blank" class="btn-primary-sm bg-blue-500 hover:bg-blue-600 text-xs no-underline">Lihat</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="benefit-card">
                <div class="benefit-header">
                    <h3 class="text-xl font-semibold text-gray-800">Arsip Bukti Potong Pajak</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="benefit-table">
                        <thead>
                            <tr>
                                <th>Tahun</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php if (empty($daftar_bukti_potong)): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-gray-500">
                                        Belum ada bukti potong yang dikirim oleh HR.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($daftar_bukti_potong as $bupot): ?>
                                    <tr>
                                        <td class="font-medium"><?php echo $bupot['tahun']; ?></td>
                                        <td>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                Terkirim
                                            </span>
                                        </td>
                                        <td>
                                            <a href="<?php echo BASE_URL; ?>/modules/hr/print_bukti_potong.php?karyawan_id=<?php echo $user_id; ?>&tahun=<?php echo $bupot['tahun']; ?>" target="_blank"
                                               class="btn-primary-sm bg-blue-500 hover:bg-blue-600 text-xs no-underline">
                                                Lihat
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </div>
</main>

<?php
// 6. Panggil footer
require '../../includes/footer.php';
?>