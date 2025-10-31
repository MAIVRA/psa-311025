<?php
// modules/companies/view_company.php

$page_title = "Detail Perusahaan";
$page_active = "manage_companies";

require '../../includes/header.php'; // Path relatif ke header.php
if ($tier != 'Admin') {
    // Jika bukan Admin, tendang ke dashboard
    $_SESSION['flash_message'] = "Anda tidak memiliki hak akses untuk halaman ini!";
    header("Location: " . BASE_URL . "/dashboard.php");
    exit;
}

// Ambil company_id dari URL
$company_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($company_id == 0) {
    $_SESSION['flash_message'] = "ID Perusahaan tidak valid!";
    header("Location: manage_companies.php");
    exit;
}

// 1. Ambil Data Utama Perusahaan
$stmt_company = $conn->prepare("SELECT * FROM companies WHERE id = ?");
$stmt_company->bind_param("i", $company_id);
$stmt_company->execute();
$result_company = $stmt_company->get_result();

if ($result_company->num_rows == 0) {
    $_SESSION['flash_message'] = "Data perusahaan tidak ditemukan!";
    header("Location: manage_companies.php");
    exit;
}
$company = $result_company->fetch_assoc();

// 2. Ambil Data Board Members (BOD/BOC)
// [PERUBAHAN] Query b.* sekarang mencakup file_ktp_path dan file_npwp_path
$stmt_board = $conn->prepare("
    SELECT b.*, 
           d.nomor_akta as akta_pengangkatan_nomor, 
           d.tanggal_akta as akta_pengangkatan_tgl, 
           d.nomor_sk_ahu as akta_pengangkatan_sk, 
           d.nama_notaris as akta_pengangkatan_notaris
    FROM board_members b 
    LEFT JOIN deeds d ON b.deed_id_pengangkatan = d.id 
    WHERE b.company_id = ? 
    ORDER BY FIELD(b.jabatan, 'Komisaris Utama', 'Komisaris', 'Direktur Utama', 'Direktur'), b.nama_lengkap
");
$stmt_board->bind_param("i", $company_id);
$stmt_board->execute();
$result_board = $stmt_board->get_result();

// 3. Ambil Data Pemegang Saham
// [PERUBAHAN] Query SELECT * sekarang mencakup file_identitas_path dan file_npwp_path
$stmt_shareholders = $conn->prepare("SELECT * FROM shareholders WHERE company_id = ? ORDER BY persentase_kepemilikan DESC");
$stmt_shareholders->bind_param("i", $company_id);
$stmt_shareholders->execute();
$result_shareholders = $stmt_shareholders->get_result();

// 4. Ambil Data Akta (Deeds)
$stmt_deeds = $conn->prepare("SELECT * FROM deeds WHERE company_id = ? ORDER BY tanggal_akta DESC");
$stmt_deeds->bind_param("i", $company_id);
$stmt_deeds->execute();
$result_deeds = $stmt_deeds->get_result();

// 5. Ambil Data Perizinan (Licenses)
$stmt_licenses = $conn->prepare("SELECT * FROM company_licenses WHERE company_id = ? ORDER BY tanggal_izin DESC");
$stmt_licenses->bind_param("i", $company_id);
$stmt_licenses->execute();
$result_licenses = $stmt_licenses->get_result();

// 6. Ambil Data KBLI
$stmt_kbli = $conn->prepare("SELECT * FROM company_kbli WHERE company_id = ? ORDER BY kode_kbli");
$stmt_kbli->bind_param("i", $company_id);
$stmt_kbli->execute();
$result_kbli = $stmt_kbli->get_result();

// Format helper
function formatRupiah($number) {
    return 'Rp ' . number_format($number, 0, ',', '.');
}

// Fungsi helper format tanggal
function formatTanggalIndo($tanggal) {
    if (empty($tanggal) || $tanggal == '0000-00-00') {
        return '-';
    }
    $bulan = array (
        1 =>   'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    );
    $pecahkan = explode('-', $tanggal);
    
    if (count($pecahkan) != 3) {
        return '-';
    }
    
    return $pecahkan[2] . ' ' . $bulan[ (int)$pecahkan[1] ] . ' ' . $pecahkan[0];
}


require '../../includes/sidebar.php'; // Path relatif ke sidebar.php
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    
    <a href="manage_companies.php" class="btn-primary-sm btn-secondary mb-4 inline-block no-underline">
        &larr; Kembali ke Daftar Perusahaan
    </a>

    <h1 class="text-3xl font-bold text-gray-800 mb-6">
        Detail: <?php echo htmlspecialchars($company['nama_perusahaan']); ?>
    </h1>

    <div class="container mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6">

        <div class="lg:col-span-1 space-y-6">
            <div class="card">
                <div class="card-header flex justify-between items-center">
                    <h3 class="text-lg font-semibold">Informasi Utama Perusahaan</h3>
                    <a href="edit_company.php?id=<?php echo $company_id; ?>" class="btn-primary-sm no-underline">Edit</a>
                </div>
                <div class="card-content space-y-4">
                    <?php if ($company['logo_path']): ?>
                        <div class="flex justify-center mb-4">
                            <img src="<?php echo BASE_URL . '/' . htmlspecialchars($company['logo_path']); ?>" alt="Logo" class="max-h-24">
                        </div>
                    <?php endif; ?>
                    
                    <div>
                        <span class="form-label">Nama Perusahaan</span>
                        <p class="form-input bg-gray-100"><?php echo htmlspecialchars($company['nama_perusahaan']); ?></p>
                    </div>
                    <div>
                        <span class="form-label">Tempat Kedudukan</span>
                        <p class="form-input bg-gray-100"><?php echo htmlspecialchars($company['tempat_kedudukan']); ?></p>
                    </div>
                    <div>
                        <span class="form-label">Alamat Lengkap</span>
                        <p class="form-input bg-gray-100 h-auto min-h-[40px]"><?php echo nl2br(htmlspecialchars($company['alamat'])); ?></p>
                    </div>
                    <div>
                        <span class="form-label">NIB</span>
                        <p class="form-input bg-gray-100"><?php echo htmlspecialchars($company['nib']); ?></p>
                    </div>
                    <div>
                        <span class="form-label">NPWP</span>
                        <p class="form-input bg-gray-100"><?php echo htmlspecialchars($company['npwp']); ?></p>
                    </div>
                    <div>
                        <span class="form-label">Tanggal Pendirian</span>
                        <p class="form-input bg-gray-100"><?php echo formatTanggalIndo($company['tanggal_pendirian']); ?></p>
                    </div>
                    <div>
                        <span class="form-label">Akta Pendirian</span>
                        <p class="form-input bg-gray-100">No. <?php echo htmlspecialchars($company['akta_pendirian']); ?> (Tgl. <?php echo formatTanggalIndo($company['tanggal_akta_pendirian']); ?>)</p>
                    </div>
                    <div>
                        <span class="form-label">Notaris Pendirian</span>
                        <p class="form-input bg-gray-100"><?php echo htmlspecialchars($company['notaris_pendirian']); ?> (<?php echo htmlspecialchars($company['domisili_notaris_pendirian']); ?>)</p>
                    </div>
                    <div>
                        <span class="form-label">SK AHU Pendirian</span>
                        <p class="form-input bg-gray-100"><?php echo htmlspecialchars($company['sk_ahu_pendirian']); ?> (Tgl. <?php echo formatTanggalIndo($company['tanggal_sk_ahu_pendirian']); ?>)</p>
                    </div>
                    <div>
                        <span class="form-label">Modal Dasar</span>
                        <p class="form-input bg-gray-100"><?php echo formatRupiah($company['modal_dasar']); ?></p>
                    </div>
                    <div>
                        <span class="form-label">Modal Disetor</span>
                        <p class="form-input bg-gray-100"><?php echo formatRupiah($company['modal_disetor']); ?></p>
                    </div>
                    <div>
                        <span class="form-label">Nilai Nominal Saham</span>
                        <p class="form-input bg-gray-100"><?php echo formatRupiah($company['nilai_nominal_saham']); ?></p>
                    </div>
                </div>
            </div>
        </div> <div class="lg:col-span-2 space-y-6">

            <div class="card" id="shareholders">
                <div class="card-header flex justify-between items-center">
                    <h3 class="text-lg font-semibold">Pemegang Saham</h3>
                    <a href="add_shareholder.php?company_id=<?php echo $company_id; ?>" class="btn-primary-sm no-underline">+ Tambah</a>
                </div>
                <div class="card-content p-0">
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Identitas</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Persentase</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if ($result_shareholders->num_rows > 0): ?>
                                    <?php while($row = $result_shareholders->fetch_assoc()): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['nama_pemegang']); ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($row['nomor_identitas']); ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600"><?php echo number_format($row['persentase_kepemilikan'], 2); ?>%</td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                                <button type="button" class="btn-primary-sm btn-secondary"
                                                    data-nama="<?php echo htmlspecialchars($row['nama_pemegang']); ?>"
                                                    data-identitas="<?php echo htmlspecialchars($row['nomor_identitas']); ?>"
                                                    data-npwp="<?php echo htmlspecialchars($row['npwp']); ?>"
                                                    data-saham="<?php echo number_format($row['jumlah_saham']); ?>"
                                                    data-persen="<?php echo number_format($row['persentase_kepemilikan'], 2); ?>%"
                                                    data-file-identitas="<?php echo htmlspecialchars($row['file_identitas_path']); ?>"
                                                    data-file-npwp="<?php echo htmlspecialchars($row['file_npwp_path']); ?>"
                                                    onclick="openShareholderModal(this)">
                                                    Lihat
                                                </button>
                                                <a href="edit_shareholder.php?id=<?php echo $row['id']; ?>&company_id=<?php echo $company_id; ?>" class="text-blue-600 hover:text-blue-900 ml-2">Edit</a>
                                                <a href="#" class="text-red-600 hover:text-red-900 ml-2">Hapus</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="px-4 py-4 text-center text-sm text-gray-500">
                                            Belum ada data pemegang saham.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card" id="bod">
                <div class="card-header flex justify-between items-center">
                    <h3 class="text-lg font-semibold">Board of Directors & Commissioners</h3>
                    <a href="add_board.php?company_id=<?php echo $company_id; ?>" class="btn-primary-sm no-underline">+ Tambah</a>
                </div>
                <div class="card-content p-0">
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jabatan</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Lengkap</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Masa Jabatan</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if ($result_board->num_rows > 0): ?>
                                    <?php while($row = $result_board->fetch_assoc()): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold 
                                                <?php echo (strpos($row['jabatan'], 'Direktur') !== false) ? 'text-blue-600' : 'text-green-600'; ?>">
                                                <?php echo htmlspecialchars($row['jabatan']); ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['nama_lengkap']); ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                                <?php echo formatTanggalIndo($row['masa_jabatan_mulai']); ?> s/d <?php echo formatTanggalIndo($row['masa_jabatan_akhir']); ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                                <button type="button" class="btn-primary-sm btn-secondary"
                                                    data-jabatan="<?php echo htmlspecialchars($row['jabatan']); ?>"
                                                    data-nama="<?php echo htmlspecialchars($row['nama_lengkap']); ?>"
                                                    data-ktp="<?php echo htmlspecialchars($row['no_ktp']); ?>"
                                                    data-npwp="<?php echo htmlspecialchars($row['npwp']); ?>"
                                                    data-alamat="<?php echo htmlspecialchars($row['alamat']); ?>"
                                                    data-telepon="<?php echo htmlspecialchars($row['telepon']); ?>"
                                                    data-email="<?php echo htmlspecialchars($row['email']); ?>"
                                                    data-masajabatan="<?php echo formatTanggalIndo($row['masa_jabatan_mulai']); ?> s/d <?php echo formatTanggalIndo($row['masa_jabatan_akhir']); ?>"
                                                    data-akta-nomor="<?php echo htmlspecialchars($row['akta_pengangkatan_nomor']); ?>"
                                                    data-akta-tgl="<?php echo formatTanggalIndo($row['akta_pengangkatan_tgl']); ?>"
                                                    data-akta-sk="<?php echo htmlspecialchars($row['akta_pengangkatan_sk']); ?>"
                                                    data-akta-notaris="<?php echo htmlspecialchars($row['akta_pengangkatan_notaris']); ?>"
                                                    data-file-ktp="<?php echo htmlspecialchars($row['file_ktp_path']); ?>"
                                                    data-file-npwp="<?php echo htmlspecialchars($row['file_npwp_path']); ?>"
                                                    onclick="openBoardModal(this)">
                                                    Lihat
                                                </button>
                                                <a href="edit_board.php?id=<?php echo $row['id']; ?>&company_id=<?php echo $company_id; ?>" class="text-blue-600 hover:text-blue-900 ml-2">Edit</a>
                                                <a href="#" class="text-red-600 hover:text-red-900 ml-2">Hapus</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="px-4 py-4 text-center text-sm text-gray-500">
                                            Belum ada data pengurus.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card" id="deeds">
                <div class="card-header flex justify-between items-center">
                    <h3 class="text-lg font-semibold">Legalitas (Akta Perusahaan)</h3>
                    <a href="add_deed.php?company_id=<?php echo $company_id; ?>" class="btn-primary-sm no-underline">+ Tambah</a>
                </div>
                <div class="card-content p-0">
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nomor Akta</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notaris</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if ($result_deeds->num_rows > 0): ?>
                                    <?php while($row = $result_deeds->fetch_assoc()): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($row['nomor_akta']); ?>
                                                
                                                <?php if ($row['nomor_akta'] == $company['akta_pendirian'] && $row['tanggal_akta'] == $company['tanggal_akta_pendirian']): ?>
                                                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-800">Pendirian</span>
                                                <?php endif; ?>
                                                
                                                <?php if ($row['id'] == $company['id_akta_terakhir']): ?>
                                                     <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Perubahan Terakhir</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600"><?php echo formatTanggalIndo($row['tanggal_akta']); ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($row['nama_notaris']); ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                                <?php if ($row['file_path']): ?>
                                                    <button type="button" class="btn-primary-sm btn-secondary"
                                                        data-path="<?php echo BASE_URL . '/' . htmlspecialchars($row['file_path']); ?>"
                                                        data-nomor="Akta No. <?php echo htmlspecialchars($row['nomor_akta']); ?> (<?php echo formatTanggalIndo($row['tanggal_akta']); ?>)"
                                                        onclick="openFileModal(this)">
                                                        Lihat
                                                    </button>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                                <a href="edit_deed.php?id=<?php echo $row['id']; ?>&company_id=<?php echo $company_id; ?>" class="text-blue-600 hover:text-blue-900">Edit</a>
                                                <a href="#" class="text-red-600 hover:text-red-900 ml-2">Hapus</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="px-4 py-4 text-center text-sm text-gray-500">
                                            Belum ada data akta.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card" id="licenses">
                <div class="card-header flex justify-between items-center">
                    <h3 class="text-lg font-semibold">Legalitas (Perizinan)</h3>
                    <a href="add_license.php?company_id=<?php echo $company_id; ?>" class="btn-primary-sm no-underline">+ Tambah</a>
                </div>
                <div class="card-content p-0">
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Izin</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nomor</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Terbit</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal Expired</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if ($result_licenses->num_rows > 0): ?>
                                    <?php while($row = $result_licenses->fetch_assoc()): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['nama_izin']); ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($row['nomor_izin']); ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600"><?php echo formatTanggalIndo($row['tanggal_izin']); ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600"><?php echo formatTanggalIndo($row['tanggal_expired']); ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                                <a href="#" class="text-red-600 hover:text-red-900">Hapus</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="px-4 py-4 text-center text-sm text-gray-500">
                                            Belum ada data perizinan.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card" id="kbli">
                <div class="card-header flex justify-between items-center">
                    <h3 class="text-lg font-semibold">Daftar KBLI</h3>
                    <a href="add_kbli.php?company_id=<?php echo $company_id; ?>" class="btn-primary-sm no-underline">+ Tambah</a>
                </div>
                <div class="card-content p-0">
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode KBLI</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deskripsi</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if ($result_kbli->num_rows > 0): ?>
                                    <?php while($row = $result_kbli->fetch_assoc()): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['kode_kbli']); ?></td>
                                            <td class="px-4 py-3 text-sm text-gray-600"><?php echo nl2br(htmlspecialchars($row['deskripsi'])); ?></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                                <a href="#" class="text-red-600 hover:text-red-900">Hapus</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="px-4 py-4 text-center text-sm text-gray-500">
                                            Belum ada data KBLI.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div> </div> <div id="shareholderModal" class="modal-overlay hidden">
        <div class="bg-white p-6 rounded-lg shadow-xl max-w-lg w-full mx-4">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h3 class="text-xl font-semibold text-gray-800">Detail Pemegang Saham</h3>
                <button onclick="closeModal('shareholderModal')" class="text-gray-500 hover:text-gray-800 text-3xl">&times;</button>
            </div>
            <div class="space-y-3">
                <div>
                    <span class="form-label">Nama Pemegang</span>
                    <p id="modalShareholderNama" class="form-input bg-gray-100"></p>
                </div>
                
                <div>
                    <span class="form-label">Nomor Identitas (KTP/NIB)</span>
                    <div class="flex items-center space-x-2">
                        <p id="modalShareholderIdentitas" class="form-input bg-gray-100 flex-1"></p>
                        <button id="btnShareholderIdentitas" type="button" class="btn-primary-sm btn-secondary hidden"
                                data-path="" data-nomor="File Identitas" onclick="openFileModal(this)">
                            Lihat Dokumen
                        </button>
                    </div>
                </div>

                <div>
                    <span class="form-label">NPWP</span>
                     <div class="flex items-center space-x-2">
                        <p id="modalShareholderNpwp" class="form-input bg-gray-100 flex-1"></p>
                        <button id="btnShareholderNpwp" type="button" class="btn-primary-sm btn-secondary hidden"
                                data-path="" data-nomor="File NPWP" onclick="openFileModal(this)">
                            Lihat Dokumen
                        </button>
                    </div>
                </div>

                <div>
                    <span class="form-label">Jumlah Saham</span>
                    <p id="modalShareholderSaham" class="form-input bg-gray-100"></p>
                </div>
                <div>
                    <span class="form-label">Persentase</span>
                    <p id="modalShareholderPersen" class="form-input bg-gray-100"></p>
                </div>
            </div>
            <div class="mt-6 flex justify-end">
                <button onclick="closeModal('shareholderModal')" class="btn-primary-sm btn-secondary">Tutup</button>
            </div>
        </div>
    </div>

    <div id="boardModal" class="modal-overlay hidden">
        <div class="bg-white p-6 rounded-lg shadow-xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h3 class="text-xl font-semibold text-gray-800">Detail Pengurus</h3>
                <button onclick="closeModal('boardModal')" class="text-gray-500 hover:text-gray-800 text-3xl">&times;</button>
            </div>
            <div class="space-y-3">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <span class="form-label">Jabatan</span>
                        <p id="modalBoardJabatan" class="form-input bg-gray-100"></p>
                    </div>
                     <div>
                        <span class="form-label">Nama Lengkap</span>
                        <p id="modalBoardNama" class="form-input bg-gray-100"></p>
                    </div>
                </div>
                
                <div>
                    <span class="form-label">No. KTP</span>
                    <div class="flex items-center space-x-2">
                        <p id="modalBoardKtp" class="form-input bg-gray-100 flex-1"></p>
                        <button id="btnBoardKtp" type="button" class="btn-primary-sm btn-secondary hidden"
                                data-path="" data-nomor="File KTP" onclick="openFileModal(this)">
                            Lihat Dokumen
                        </button>
                    </div>
                </div>

                <div>
                    <span class="form-label">NPWP</span>
                    <div class="flex items-center space-x-2">
                        <p id="modalBoardNpwp" class="form-input bg-gray-100 flex-1"></p>
                        <button id="btnBoardNpwp" type="button" class="btn-primary-sm btn-secondary hidden"
                                data-path="" data-nomor="File NPWP" onclick="openFileModal(this)">
                            Lihat Dokumen
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                     <div>
                        <span class="form-label">Telepon</span>
                        <p id="modalBoardTelepon" class="form-input bg-gray-100"></p>
                    </div>
                    <div>
                        <span class="form-label">Email</span>
                        <p id="modalBoardEmail" class="form-input bg-gray-100"></p>
                    </div>
                </div>
                 <div>
                    <span class="form-label">Alamat</span>
                    <p id="modalBoardAlamat" class="form-input bg-gray-100 min-h-[60px] h-auto whitespace-pre-wrap"></p>
                </div>
                <div>
                    <span class="form-label">Masa Jabatan</span>
                    <p id="modalBoardMasaJabatan" class="form-input bg-gray-100"></p>
                </div>
                <div>
                    <span class="form-label">Akta Pengangkatan</span>
                    <div class="p-3 bg-gray-100 rounded-md border space-y-2">
                        <p class="text-sm"><strong>Nomor Akta:</strong> <span id="modalBoardAktaNomor">-</span></p>
                        <p class="text-sm"><strong>Tanggal Akta:</strong> <span id="modalBoardAktaTgl">-</span></p>
                        <p class="text-sm"><strong>Notaris:</strong> <span id="modalBoardAktaNotaris">-</span></p>
                        <p class="text-sm"><strong>SK AHU:</strong> <span id="modalBoardAktaSk">-</span></p>
                    </div>
                </div>
            </div>
            <div class="mt-6 flex justify-end">
                <button onclick="closeModal('boardModal')" class="btn-primary-sm btn-secondary">Tutup</button>
            </div>
        </div>
    </div>
    
    <div id="fileModal" class="modal-overlay hidden">
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 flex flex-col" style="height: 90vh;">
            <div class="flex justify-between items-center border-b p-4">
                <h id="modalFileTitle" class="text-xl font-semibold text-gray-800">Lihat Dokumen</h>
                <button onclick="closeModal('fileModal')" class="text-gray-500 hover:text-gray-800 text-3xl">&times;</button>
            </div>
            <div class="flex-1 p-4 bg-gray-200">
                <iframe id="modalFileFrame" src="about:blank" class="w-full h-full border-0" frameborder="0"></iframe>
            </div>
            <div class="p-4 border-t flex justify-end">
                <button onclick="closeModal('fileModal')" class="btn-primary-sm btn-secondary">Tutup</button>
            </div>
        </div>
    </div>

</main>

<script>
    
    // Fungsi generik untuk menampilkan file di modal
    function openFileModal(button) {
        const data = button.dataset;
        document.getElementById('modalFileTitle').textContent = data.nomor;
        document.getElementById('modalFileFrame').src = data.path;
        document.getElementById('fileModal').classList.remove('hidden');
    }

    function openShareholderModal(button) {
        const data = button.dataset;
        document.getElementById('modalShareholderNama').textContent = data.nama;
        document.getElementById('modalShareholderIdentitas').textContent = data.identitas || '-';
        document.getElementById('modalShareholderNpwp').textContent = data.npwp || '-';
        document.getElementById('modalShareholderSaham').textContent = data.saham;
        document.getElementById('modalShareholderPersen').textContent = data.persen;

        // Logika tombol Lihat Dokumen Identitas
        const btnIdentitas = document.getElementById('btnShareholderIdentitas');
        if (data.fileIdentitas) {
            btnIdentitas.dataset.path = '<?php echo BASE_URL; ?>/' + data.fileIdentitas;
            btnIdentitas.dataset.nomor = 'File Identitas - ' + data.nama;
            btnIdentitas.classList.remove('hidden');
        } else {
            btnIdentitas.classList.add('hidden');
        }

        // Logika tombol Lihat Dokumen NPWP
        const btnNpwp = document.getElementById('btnShareholderNpwp');
        if (data.fileNpwp) {
            btnNpwp.dataset.path = '<?php echo BASE_URL; ?>/' + data.fileNpwp;
            btnNpwp.dataset.nomor = 'File NPWP - ' + data.nama;
            btnNpwp.classList.remove('hidden');
        } else {
            btnNpwp.classList.add('hidden');
        }

        document.getElementById('shareholderModal').classList.remove('hidden');
    }

    function openBoardModal(button) {
        const data = button.dataset;
        document.getElementById('modalBoardJabatan').textContent = data.jabatan;
        document.getElementById('modalBoardNama').textContent = data.nama;
        document.getElementById('modalBoardKtp').textContent = data.ktp || '-';
        document.getElementById('modalBoardNpwp').textContent = data.npwp || '-';
        document.getElementById('modalBoardTelepon').textContent = data.telepon || '-';
        document.getElementById('modalBoardEmail').textContent = data.email || '-';
        document.getElementById('modalBoardAlamat').textContent = data.alamat || '-';
        document.getElementById('modalBoardMasaJabatan').textContent = data.masajabatan;
        
        document.getElementById('modalBoardAktaNomor').textContent = data.aktaNomor || '-';
        document.getElementById('modalBoardAktaTgl').textContent = data.aktaTgl || '-';
        document.getElementById('modalBoardAktaNotaris').textContent = data.aktaNotaris || '-';
        document.getElementById('modalBoardAktaSk').textContent = data.aktaSk || '-';
        
        // Logika tombol Lihat Dokumen KTP
        const btnKtp = document.getElementById('btnBoardKtp');
        if (data.fileKtp) {
            btnKtp.dataset.path = '<?php echo BASE_URL; ?>/' + data.fileKtp;
            btnKtp.dataset.nomor = 'File KTP - ' + data.nama;
            btnKtp.classList.remove('hidden');
        } else {
            btnKtp.classList.add('hidden');
        }

        // Logika tombol Lihat Dokumen NPWP
        const btnNpwp = document.getElementById('btnBoardNpwp');
        if (data.fileNpwp) {
            btnNpwp.dataset.path = '<?php echo BASE_URL; ?>/' + data.fileNpwp;
            btnNpwp.dataset.nomor = 'File NPWP - ' + data.nama;
            btnNpwp.classList.remove('hidden');
        } else {
            btnNpwp.classList.add('hidden');
        }

        document.getElementById('boardModal').classList.remove('hidden');
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        modal.classList.add('hidden');
        
        if (modalId === 'fileModal') {
            document.getElementById('modalFileFrame').src = 'about:blank';
        }
    }
</script>


<?php
// Tutup statement
$stmt_company->close();
$stmt_board->close();
$stmt_shareholders->close();
$stmt_deeds->close();
$stmt_licenses->close();
$stmt_kbli->close();

require '../../includes/footer.php'; // Path relatif ke footer.php
?>