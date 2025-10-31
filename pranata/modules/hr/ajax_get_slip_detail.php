<?php
// pranata/modules/hr/ajax_get_slip_detail.php
// Endpoint ini HANYA mengembalikan HTML snippet untuk modal slip gaji.

// 1. Panggil koneksi DB dan mulai session
require '../../includes/db.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Helper Functions (Karena file ini tidak me-load header.php)
if (!function_exists('formatAngka')) {
    function formatAngka($angka, $prefix = 'Rp ') {
        return $prefix . number_format($angka, 0, ',', '.');
    }
}
if (!function_exists('formatTanggalIndo')) {
    function formatTanggalIndo($tanggal) {
        if (empty($tanggal) || $tanggal == '0000-00-00') return '-';
        $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $pecahkan = explode('-', $tanggal);
        return $pecahkan[2] . ' ' . $bulan[ (int)$pecahkan[1] ] . ' ' . $pecahkan[0];
    }
}

// 3. Keamanan Endpoint
$app_akses = $_SESSION['app_akses'] ?? 'Karyawan';
if ($app_akses != 'HR' && $app_akses != 'Admin') {
    // Jangan pakai JSON, tapi HTML error agar konsisten
    echo '<p class="text-red-500 p-4">Akses ditolak. Anda tidak memiliki izin untuk melihat data ini.</p>';
    exit;
}

// 4. Validasi Input
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    echo '<p class="text-red-500 p-4">ID Riwayat Gaji tidak valid.</p>';
    exit;
}
$history_id = (int)$_GET['id'];

// 5. Query data detail (Sama seperti ajax_get_slip_detail.php)
$header_data = null;
$pendapatan_items = [];
$potongan_items = [];

try {
    // 5.1. Ambil data header (total) dari payroll_history + data user
    $sql_header = "SELECT 
                        ph.*, 
                        u.nama_lengkap, 
                        u.nik,
                        u.nama_jabatan,
                        d.nama_departemen,
                        ph.tanggal_mulai_periode, -- [PERUBAHAN] Ambil tanggal mulai ASLI
                        DATE_FORMAT(ph.tanggal_mulai_periode, '%d %b %Y') AS tgl_mulai_fmt,
                        DATE_FORMAT(ph.tanggal_selesai_periode, '%d %b %Y') AS tgl_selesai_fmt
                   FROM payroll_history ph
                   JOIN users u ON ph.user_id = u.id
                   LEFT JOIN departemen d ON u.id_departemen = d.id
                   WHERE ph.id = ?";
    $stmt_header = $conn->prepare($sql_header);
    $stmt_header->bind_param("i", $history_id);
    $stmt_header->execute();
    $result_header = $stmt_header->get_result();
    
    if ($result_header->num_rows == 0) {
        throw new Exception("Data riwayat payroll tidak ditemukan.");
    }
    $header_data = $result_header->fetch_assoc();
    $stmt_header->close();

    // 5.2. Ambil data rincian dari payroll_history_detail
    $sql_detail = "SELECT * FROM payroll_history_detail 
                   WHERE payroll_history_id = ? 
                   ORDER BY tipe ASC, id ASC";
    $stmt_detail = $conn->prepare($sql_detail);
    $stmt_detail->bind_param("i", $history_id);
    $stmt_detail->execute();
    $result_detail = $stmt_detail->get_result();
    
    while ($row = $result_detail->fetch_assoc()) {
        // Pisahkan pendapatan dan potongan
        if ($row['tipe'] == 'Pendapatan') {
            // Jangan tampilkan Tunjangan Fasilitas (Komunikasi) di slip gaji
            if (strpos($row['komponen'], 'Fasilitas') === false) {
                 $pendapatan_items[] = $row;
            }
        } else {
            $potongan_items[] = $row;
        }
    }
    $stmt_detail->close();
    
} catch (Exception $e) {
    $conn->close();
    echo '<p class="text-red-500 p-4">Gagal mengambil data: ' . $e->getMessage() . '</p>';
    exit;
}

$conn->close();

// 6. Hitung Total
$total_potongan_all = $header_data['total_potongan_bpjs'] + 
                      $header_data['total_potongan_pph21'] + 
                      $header_data['total_potongan_lainnya'];
                      
// [PERUBAHAN] Buat teks untuk kotak periode
setlocale(LC_TIME, 'id_ID.UTF-8', 'Indonesian');
$periode_box_text = strtoupper(strftime('%B %Y', strtotime($header_data['tanggal_mulai_periode'])));

// 7. Generate HTML Snippet
?>
<div class="slip-header flex justify-between items-center">
    <div class="flex items-center space-x-3 text-left">
        <img src="<?php echo BASE_URL; ?>/logo.png" alt="Logo Perusahaan" class="h-12 w-auto"> <div>
            <h4 class="text-xl font-bold">PT PUTRA NATUR UTAMA</h4>
            <p class="text-sm">SLIP GAJI KARYAWAN (RAHASIA)</p>
        </div>
    </div>
    <div class="border-2 border-gray-900 p-2 text-center" style="min-width: 150px;">
        <span class="text-sm font-semibold block">PERIODE</span>
        <span class="text-lg font-bold block"><?php echo $periode_box_text; ?></span>
    </div>
</div>
<div class="slip-info">
    <div>
        <span class="slip-info-label">Nama Karyawan:</span>
        <span><?php echo htmlspecialchars($header_data['nama_lengkap']); ?></span>
    </div>
    <div>
        <span class="slip-info-label">Periode:</span>
        <span><?php echo htmlspecialchars($header_data['tgl_mulai_fmt']) . ' - ' . htmlspecialchars($header_data['tgl_selesai_fmt']); ?></span>
    </div>
    <div>
        <span class="slip-info-label">NIK:</span>
        <span><?php echo htmlspecialchars($header_data['nik']); ?></span>
    </div>
    <div>
        <span class="slip-info-label">Jabatan:</span>
        <span><?php echo htmlspecialchars($header_data['nama_jabatan'] ?? '-'); ?></span>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-x-6">
    
    <div class="slip-section">
        <h5>PENDAPATAN</h5>
        <table class="slip-table">
            <tbody>
                <?php foreach ($pendapatan_items as $item): ?>
                <tr>
                    <td class="label"><?php echo htmlspecialchars($item['komponen']); ?></td>
                    <td class="amount"><?php echo formatAngka($item['jumlah']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="font-semibold border-t">
                    <td class="label py-2">Total Gross Income (A)</td>
                    <td class="amount py-2"><?php echo formatAngka($header_data['total_gross_income']); ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
    
    <div class="slip-section">
        <h5>POTONGAN</h5>
        <table class="slip-table">
            <tbody>
                <?php foreach ($potongan_items as $item): ?>
                <tr>
                    <td class="label"><?php echo htmlspecialchars($item['komponen']); ?></td>
                    <td class="amount text-red-600"><?php echo formatAngka($item['jumlah'], '(Rp '); ?>)</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="font-semibold border-t">
                    <td class="label py-2">Total Potongan (B)</td>
                    <td class="amount py-2 text-red-600"><?php echo formatAngka($total_potongan_all, '(Rp '); ?>)</td>
                </tr>
            </tfoot>
        </table>
    </div>
    
</div>

<div class="slip-total">
    <span>TAKE HOME PAY (A - B)</span>
    <span><?php echo formatAngka($header_data['take_home_pay']); ?></span>
</div>

<p class="text-xs text-gray-500 mt-4 text-center italic">
    Dicetak menggunakan aplikasi, tidak diperlukan adanya tandatangan.
</p>