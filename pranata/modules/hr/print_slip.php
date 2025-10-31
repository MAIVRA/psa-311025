<?php
// pranata/modules/hr/print_slip.php
// Halaman ini diformat khusus untuk dicetak.

// 1. Panggil koneksi DB dan mulai session
// Kita tidak memanggil header.php, jadi panggil db.php secara manual
require '../../includes/db.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Helper Functions
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
$is_admin = ($app_akses == 'Admin');
$is_hr = ($app_akses == 'HR');
$logged_in_user_id = $_SESSION['user_id'] ?? 0; // ID user yang login

// 4. Validasi Input
$history_id = (int)($_GET['id'] ?? 0);
if (empty($history_id)) {
    die("ID Riwayat Gaji tidak valid.");
}

// === [PERBAIKAN KEAMANAN BARU] ===
// Cek apakah user ini adalah pemilik slip, ATAU HR/Admin
$is_owner = false;
$stmt_check = $conn->prepare("SELECT user_id FROM payroll_history WHERE id = ?");
if ($stmt_check) {
    $stmt_check->bind_param("i", $history_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    if ($row_check = $result_check->fetch_assoc()) {
        if ($row_check['user_id'] == $logged_in_user_id) {
            $is_owner = true;
        }
    }
    $stmt_check->close();
} else {
    die("Gagal memverifikasi kepemilikan slip: " . $conn->error);
}

// Terapkan aturan keamanan
if (!$is_admin && !$is_hr && !$is_owner) {
    die("Akses ditolak. Anda tidak memiliki izin untuk melihat data ini.");
}
// === [AKHIR PERBAIKAN] ===


// 5. Query data detail (Sama seperti ajax_get_slip_detail.php)
$header_data = null;
$pendapatan_items = [];
$potongan_items = [];

try {
    // 5.1. Ambil data header
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

    // 5.2. Ambil data rincian
    $sql_detail = "SELECT * FROM payroll_history_detail 
                   WHERE payroll_history_id = ? 
                   ORDER BY tipe ASC, id ASC";
    $stmt_detail = $conn->prepare($sql_detail);
    $stmt_detail->bind_param("i", $history_id);
    $stmt_detail->execute();
    $result_detail = $stmt_detail->get_result();
    
    while ($row = $result_detail->fetch_assoc()) {
        if ($row['tipe'] == 'Pendapatan') {
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
    die("Gagal mengambil data: " . $e->getMessage());
}

$conn->close();

// 6. Hitung Total
$total_potongan_all = $header_data['total_potongan_bpjs'] + 
                      $header_data['total_potongan_pph21'] + 
                      $header_data['total_potongan_lainnya'];
                      
// [PERUBAHAN] Buat teks untuk kotak periode
setlocale(LC_TIME, 'id_ID.UTF-8', 'Indonesian');
$periode_box_text = strtoupper(strftime('%B %Y', strtotime($header_data['tanggal_mulai_periode'])));

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Slip Gaji - <?php echo htmlspecialchars($header_data['nama_lengkap']); ?> - <?php echo htmlspecialchars($header_data['tgl_selesai_fmt']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo BASE_URL; ?>/favicon-16x16.png">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            color: #333;
            background-color: #f3f4f6; /* bg-gray-100 */
        }
        .print-container {
            max-width: 800px;
            margin: 2rem auto;
            background-color: #ffffff;
            padding: 2.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        
        /* [PERUBAHAN] Style header di-update */
        .slip-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            border-bottom: 2px solid #000; 
            padding-bottom: 10px; 
            margin-bottom: 15px; 
        }
        .slip-header-logo {
            display: flex;
            align-items: center;
        }
        .slip-header-logo img {
            height: 3rem; /* 48px */
            width: auto;
            margin-right: 0.75rem; /* 12px */
        }
        .slip-header-title h4 { font-size: 1.5rem; font-weight: bold; margin: 0; }
        .slip-header-title p { font-size: 0.9rem; margin: 0; }
        
        .slip-header-periode {
            border: 2px solid #1f2937; /* gray-900 */
            padding: 0.5rem;
            text-align: center;
            min-width: 150px;
        }
        .slip-header-periode .periode-label { font-size: 0.875rem; font-weight: 600; display: block; }
        .slip-header-periode .periode-value { font-size: 1.125rem; font-weight: 700; display: block; }
        /* [AKHIR PERUBAHAN] */

        .slip-info { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 20px; font-size: 0.9rem; margin-bottom: 15px; }
        .slip-info-label { font-weight: 600; color: #555; }
        .slip-section { margin-top: 15px; }
        .slip-section h5 { font-size: 1.1rem; font-weight: bold; background-color: #f3f4f6; padding: 5px; border-bottom: 1px solid #ddd; }
        .slip-table { width: 100%; font-size: 0.9rem; }
        .slip-table td { padding: 4px 8px; }
        .slip-table .label { width: 60%; }
        .slip-table .amount { text-align: right; font-weight: 500; }
        .slip-total { border-top: 2px solid #ccc; padding-top: 10px; margin-top: 10px; display: flex; justify-content: space-between; font-size: 1.1rem; font-weight: bold; }
        
        .print-button-container {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px dashed #ccc;
        }

        /* Aturan untuk mencetak */
        @media print {
            body {
                background-color: #ffffff;
            }
            .print-container {
                max-width: 100%;
                margin: 0;
                padding: 0;
                box-shadow: none;
                border-radius: 0;
            }
            .print-button-container {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="print-container" id="slipToPrint">
        <div class="slip-header">
            <div class="slip-header-logo">
                <img src="<?php echo BASE_URL; ?>/logo.png" alt="Logo Perusahaan">
                <div class="slip-header-title">
                    <h4>PT PUTRA NATUR UTAMA</h4>
                    <p>SLIP GAJI KARYAWAN (RAHASIA)</p>
                </div>
            </div>
            <div class="slip-header-periode">
                <span class="periode-label">PERIODE</span>
                <span class="periode-value"><?php echo $periode_box_text; ?></span>
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
        
        <div class="print-button-container">
            <button
                type="button"
                onclick="window.print()"
                class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg text-sm font-semibold transition duration-200">
                Cetak Halaman Ini
            </button>
            <button
                type="button"
                onclick="window.close()"
                class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-2 rounded-lg text-sm font-semibold transition duration-200 ml-2">
                Tutup
            </button>
        </div>
    </div>
</body>
</html>