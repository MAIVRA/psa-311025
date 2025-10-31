<?php
// view_registry.php

// 1. Set variabel khusus untuk halaman ini
$page_title = "Daftar Registrasi Surat";
$page_active = "view_registry";

// 2. Include template header
// === [PATH DIPERBAIKI] ===
require '../../includes/header.php';
// === [AKHIR PERUBAHAN] ===

// 3. Ambil ID & Tier user yg login
$user_id_login = $_SESSION['user_id'];
$user_tier_login = $_SESSION['tier'];

// 4. Inisialisasi variabel filter & data
$error_message = '';
$registry_list = [];
$document_codes = [];

// Filter Values
$filter_start_date = $_GET['start_date'] ?? '';
$filter_end_date = $_GET['end_date'] ?? '';
$filter_code_id = $_GET['code_id'] ?? '';
$filter_keyword = $_GET['keyword'] ?? '';

// 5. Buka koneksi baru
$conn_get = new mysqli($servername, $username, $password, $dbname);
if ($conn_get->connect_error) { die("Koneksi Gagal: " . $conn_get->connect_error); }
$conn_get->set_charset("utf8mb4");

try {
    // Ambil daftar kode dokumen
    $result_codes = $conn_get->query("SELECT id, kode_surat, deskripsi FROM document_codes ORDER BY kode_surat ASC");
    if (!$result_codes) throw new Exception("Gagal mengambil daftar kode dokumen: " . $conn_get->error);
    while ($row = $result_codes->fetch_assoc()) { $document_codes[] = $row; }

    // Query utama
    $sql = " SELECT dr.id, dr.nomor_lengkap, dr.tanggal_surat, dr.penandatangan, dr.ditujukan_kepada, dr.perihal, dr.isi_ringkas, dr.created_at, dr.created_by_id, dr.file_path, dr.tipe_dokumen, dr.akses_dokumen, dr.akses_terbatas_level, dr.akses_karyawan_ids, dc.kode_surat, u.nama_lengkap AS pembuat_nama FROM document_registry dr JOIN document_codes dc ON dr.document_code_id = dc.id JOIN users u ON dr.created_by_id = u.id WHERE 1=1 ";
    $bind_types = ""; $bind_values = [];
    if (!empty($filter_start_date)) { $sql .= " AND dr.tanggal_surat >= ?"; $bind_types .= "s"; $bind_values[] = $filter_start_date; }
    if (!empty($filter_end_date)) { $sql .= " AND dr.tanggal_surat <= ?"; $bind_types .= "s"; $bind_values[] = $filter_end_date; }
    if (!empty($filter_code_id)) { $sql .= " AND dr.document_code_id = ?"; $bind_types .= "i"; $bind_values[] = $filter_code_id; }
    if (!empty($filter_keyword)) { $keyword_like = "%" . $filter_keyword . "%"; $sql .= " AND (dr.nomor_lengkap LIKE ? OR dr.perihal LIKE ? OR dr.penandatangan LIKE ? OR dr.ditujukan_kepada LIKE ? OR dr.isi_ringkas LIKE ? OR u.nama_lengkap LIKE ?)"; $bind_types .= "ssssss"; $bind_values = array_merge($bind_values, [$keyword_like, $keyword_like, $keyword_like, $keyword_like, $keyword_like, $keyword_like]); }
    $sql .= " ORDER BY dr.tanggal_surat DESC, dr.id DESC";

    // Eksekusi
    $stmt = $conn_get->prepare($sql);
    if (!$stmt) throw new Exception("Gagal prepare query registry: " . $conn_get->error);
    if (!empty($bind_types)) { if (strlen($bind_types) != count($bind_values)) throw new Exception("Bind type/value mismatch"); $stmt->bind_param($bind_types, ...$bind_values); }
    if (!$stmt->execute()) throw new Exception("Gagal eksekusi query registry: " . $stmt->error);
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) { $registry_list[] = $row; }
    $stmt->close();

} catch (Exception $e) { $error_message = "Error mengambil data: " . $e->getMessage(); }
$conn_get->close();

// 6. Include template sidebar
// === [PATH DIPERBAIKI] ===
require '../../includes/sidebar.php';
// === [AKHIR PERUBAHAN] ===

// === [PERUBAHAN DI SINI] Flash message dipindahkan ke atas ===
// 7. Cek Flash Message dari Session
$flash_message = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']); // Hapus setelah dibaca
}
?>

<div id="flashModal" class="modal-overlay <?php echo empty($flash_message) ? 'hidden' : ''; // Tampilkan jika ada pesan ?>">
    <div class="bg-white p-6 rounded-lg shadow-xl max-w-sm w-full mx-4">
        <div class="flex items-center">
            <div class="flex-shrink-0 bg-green-100 rounded-full p-2">
                 <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            </div>
            <div class="ml-4">
                <h3 class="text-lg font-semibold text-gray-800">Sukses!</h3>
                <p id="flashModalMessage" class="mt-1 text-gray-600"><?php echo htmlspecialchars($flash_message); // Langsung isi pesannya ?></p>
            </div>
        </div>
        <div class="mt-6 flex justify-end">
            <button
                type="button"
                onclick="closeFlashModal()"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition duration-200">
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
</script>
<style>
    /* CSS ini (dari file asli Anda) dipertahankan karena mendukung pdf-modal-content */
     .tooltip-container { position: relative; display: inline-block; } .tooltip-text { visibility: hidden; width: 150px; background-color: #555; color: #fff; text-align: center; border-radius: 6px; padding: 5px 0; position: absolute; z-index: 50; bottom: 125%; left: 50%; margin-left: -75px; opacity: 0; transition: opacity 0.3s; font-size: 0.75rem; } .tooltip-text::after { content: ""; position: absolute; top: 100%; left: 50%; margin-left: -5px; border-width: 5px; border-style: solid; border-color: #555 transparent transparent transparent; } .tooltip-container:hover .tooltip-text { visibility: visible; opacity: 1; } .pdf-modal-overlay, .summary-modal-overlay { position: fixed; inset: 0; background-color: rgba(0, 0, 0, 0.7); display: flex; align-items: center; justify-content: center; z-index: 50; padding: 1rem; } .pdf-modal-content { background-color: white; border-radius: 0.5rem; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); width: 90vw; height: 90vh; display: flex; flex-direction: column; overflow: hidden;} .pdf-modal-header, .summary-modal-header { padding: 1rem; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; } .pdf-modal-body { flex-grow: 1; overflow: auto; padding: 1rem; background-color: #f3f4f6; display: flex; justify-content: center; } .pdf-modal-canvas { border: 1px solid #d1d5db; display: block; margin: auto; } .pdf-modal-footer, .summary-modal-footer { padding: 1rem; border-top: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; } .pdf-nav button { background-color: #e5e7eb; color: #374151; padding: 0.5rem 1rem; border-radius: 0.375rem; font-size: 0.875rem; font-weight: 500; cursor: pointer; transition: background-color 0.2s; } .pdf-nav button:hover { background-color: #d1d5db; } .pdf-nav button:disabled { opacity: 0.5; cursor: not-allowed; } .summary-modal-content { background-color: white; border-radius: 0.5rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05); max-width: 600px; width: 90vw; max-height: 80vh; display: flex; flex-direction: column; } .summary-modal-body { flex-grow: 1; overflow-y: auto; padding: 1.5rem; font-size: 0.875rem; line-height: 1.6; }
</style>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
        <h1 class="text-3xl font-bold text-gray-800"><?php echo $page_title; ?></h1>
        
        <a href="<?php echo BASE_URL; ?>/modules/registry/register_number.php" class="btn-primary-sm flex items-center shadow-md self-start md:self-center px-4 py-2 text-sm font-semibold no-underline">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Registrasi Surat Baru
        </a>
    </div>

     <?php if (!empty($error_message)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
        </div>
    <?php endif; ?>

    <div class="card mb-6">
        <div class="card-header"><h2 class="text-xl font-semibold text-gray-800">Filter Pencarian</h2></div>
        <div class="card-content">
            <form action="<?php echo BASE_URL; ?>/modules/registry/view_registry.php" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label for="start_date" class="form-label text-sm">Dari Tgl Surat</label>
                    <input type="date" id="start_date" name="start_date" class="form-input text-sm" value="<?php echo htmlspecialchars($filter_start_date); ?>">
                </div>
                <div>
                    <label for="end_date" class="form-label text-sm">Sampai Tgl Surat</label>
                    <input type="date" id="end_date" name="end_date" class="form-input text-sm" value="<?php echo htmlspecialchars($filter_end_date); ?>">
                </div>
                <div>
                    <label for="code_id" class="form-label text-sm">Jenis Surat</label>
                    <select id="code_id" name="code_id" class="form-input text-sm">
                        <option value="">Semua Jenis</option>
                        <?php foreach ($document_codes as $code): ?>
                            <option value="<?php echo $code['id']; ?>" <?php if ($code['id'] == $filter_code_id) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($code['kode_surat']); ?> - <?php echo htmlspecialchars($code['deskripsi']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="keyword" class="form-label text-sm">Kata Kunci</label>
                    <input type="text" id="keyword" name="keyword" class="form-input text-sm" placeholder="Nomor/Perihal/Tujuan/TTD/Input Oleh..." value="<?php echo htmlspecialchars($filter_keyword); ?>">
                </div>
                <div class="md:col-start-4 flex justify-end space-x-2">
                    <a href="<?php echo BASE_URL; ?>/modules/registry/view_registry.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg text-sm font-semibold no-underline">Reset</a>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">Cari</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2 class="text-xl font-semibold text-gray-800">Hasil Registrasi</h2></div>
        <div class="card-content overflow-x-auto">
            <table class="w-full min-w-max">
                <thead>
                    <tr class="bg-gray-100 text-left text-sm font-semibold text-gray-600 uppercase">
                        <th class="py-3 px-4">Nomor Surat</th>
                        <th class="py-3 px-4">Tgl Surat</th>
                        <th class="py-3 px-4">Perihal/Judul</th>
                        <th class="py-3 px-4">Tujuan</th>
                        <th class="py-3 px-4">Penandatangan</th>
                        <th class="py-3 px-4">Diinput Oleh</th>
                        <th class="py-3 px-4">File & Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700 text-sm">
                    <?php if (empty($registry_list)): ?>
                        <tr>
                            <td colspan="7" class="py-4 px-4 text-center text-gray-500">
                                <?php if(empty($filter_start_date) && empty($filter_end_date) && empty($filter_code_id) && empty($filter_keyword)): ?>
                                    Belum ada data registrasi surat.
                                <?php else: ?>
                                    Tidak ada data registrasi surat yang cocok dengan filter Anda.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($registry_list as $reg): ?>
                            <?php
                            // Logika Access Control (tetap sama, sudah bagus)
                            $can_access_file = false;
                            $is_direksi = ($user_tier_login == 'Direksi');
                            $is_uploader = ($reg['created_by_id'] == $user_id_login);
                            if ($reg['akses_dokumen'] == 'Semua') { if ($reg['tipe_dokumen'] == 'Rahasia' && !$is_uploader && !$is_direksi) { $can_access_file = false; } else { $can_access_file = true; } } elseif ($reg['akses_dokumen'] == 'Dilarang') { if ($is_uploader || $is_direksi) { $can_access_file = true; } } elseif ($reg['akses_dokumen'] == 'Terbatas') { if ($is_uploader || $is_direksi) { $can_access_file = true; } else { $level = $reg['akses_terbatas_level']; if ($level == 'Manager' && $user_tier_login == 'Manager') { $can_access_file = true; } elseif ($level == 'Karyawan' && !empty($reg['akses_karyawan_ids'])) { $allowed_ids = json_decode($reg['akses_karyawan_ids'], true); if (is_array($allowed_ids) && in_array($user_id_login, $allowed_ids)) { $can_access_file = true; } } } }
                            $highlight_class = (!$can_access_file || empty($reg['file_path'])) ? 'bg-orange-50' : '';
                            ?>
                            <tr class="border-b border-gray-100 hover:bg-gray-50 <?php echo $highlight_class; ?>">
                                <td class="py-3 px-4 font-mono font-semibold"><?php echo htmlspecialchars($reg['nomor_lengkap']); ?></td>
                                <td class="py-3 px-4 whitespace-nowrap"><?php echo date('d M Y', strtotime($reg['tanggal_surat'])); ?></td>
                                <td class="py-3 px-4 text-xs max-w-xs truncate" title="<?php echo htmlspecialchars($reg['perihal'] ?? ''); ?>"><?php echo htmlspecialchars($reg['perihal'] ?? 'N/A'); ?></td>
                                <td class="py-3 px-4"><?php echo htmlspecialchars($reg['ditujukan_kepada'] ?? 'N/A'); ?></td>
                                <td class="py-3 px-4"><?php echo htmlspecialchars($reg['penandatangan'] ?? 'N/A'); ?></td>
                                <td class="py-3 px-4 text-xs">
                                    <?php echo htmlspecialchars($reg['pembuat_nama']); ?>
                                    <span class="block text-gray-500"><?php echo date('d M Y H:i', strtotime($reg['created_at'])); ?></span>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <?php if ($can_access_file): ?>
                                        <?php if (empty($reg['file_path'])): ?>
                                            <a href="<?php echo BASE_URL; ?>/modules/registry/upload_scan.php?registry_id=<?php echo $reg['id']; ?>" class="inline-flex items-center text-blue-600 hover:text-blue-800 font-medium text-xs bg-blue-100 px-2 py-1 rounded mr-1 no-underline">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                                                Upload
                                            </a>
                                            <div class="tooltip-container inline-block">
                                                <svg class="w-4 h-4 text-orange-500 inline-block align-middle" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                                <span class="tooltip-text">Salinan belum diupload</span>
                                            </div>
                                        <?php else: ?>
                                            <button type="button" onclick="openPdfPreviewModal('<?php echo htmlspecialchars($reg['file_path']); ?>', '<?php echo htmlspecialchars(addslashes($reg['nomor_lengkap']), ENT_QUOTES); ?>')" class="inline-flex items-center text-green-600 hover:text-green-800 font-medium text-xs bg-green-100 px-2 py-1 rounded mr-1">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                                Preview
                                            </button>
                                            <a href="<?php echo BASE_URL; ?>/modules/registry/download_scan.php?registry_id=<?php echo $reg['id']; ?>" class="inline-flex items-center text-blue-600 hover:text-blue-800 font-medium text-xs bg-blue-100 px-2 py-1 rounded no-underline">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                                Download
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-xs text-red-600 font-medium italic">Tidak Ada Akses</span>
                                    <?php endif; ?>
                                    
                                    <?php if ($can_access_file && !empty($reg['isi_ringkas'])): ?>
                                        <button type="button" onclick="openSummaryModal('<?php echo htmlspecialchars(addslashes($reg['nomor_lengkap']), ENT_QUOTES); ?>', <?php echo htmlspecialchars(json_encode($reg['isi_ringkas']), ENT_QUOTES); ?>)" class="inline-flex items-center text-gray-600 hover:text-gray-800 font-medium text-xs bg-gray-100 px-2 py-1 rounded ml-2">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                            Ringkasan
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<div id="pdfPreviewModal" class="pdf-modal-overlay hidden" style="display: none;">
    <div class="pdf-modal-content">
        <div class="pdf-modal-header">
            <h3 class="text-lg font-semibold text-gray-800" id="pdfModalTitle">Preview Dokumen</h3>
            <button onclick="closePdfPreviewModal()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>
        <div class="pdf-modal-body">
            <canvas id="pdfCanvas" class="pdf-modal-canvas"></canvas>
            <div id="pdfLoading" class="text-center text-gray-500 py-10 hidden">Memuat PDF...</div>
        </div>
        <div class="pdf-modal-footer">
            <div class="pdf-nav space-x-2 flex items-center">
                <button id="pdfZoomOut" title="Zoom Out">-</button>
                <button id="pdfZoomIn" title="Zoom In">+</button>
                <button id="pdfZoomReset" title="Fit Width">Fit Width</button>
                <span class="text-xs text-gray-500 ml-2">(<span id="pdfCurrentZoom">100</span>%)</span>
            </div>
            <div class="pdf-nav space-x-2">
                <button id="pdfPrevPage" disabled>&lt; Sebelumnya</button>
                <button id="pdfNextPage" disabled>Berikutnya &gt;</button>
            </div>
            <div>
                <span class="text-sm text-gray-600">Halaman: <span id="pdfCurrentPage">0</span> / <span id="pdfTotalPages">0</span></span>
            </div>
            <button onclick="closePdfPreviewModal()" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg text-sm font-semibold">Tutup</button>
        </div>
    </div>
</div>

<script type="module">
    // --- [PERBAIKAN PATH: MENGGUNAKAN BASE_URL] ---
    const BASE_URL = '<?php echo BASE_URL; ?>';
    const pdfjsLibPath = `${BASE_URL}/js/pdfjs/pdf.mjs`;
    const pdfjsWorkerPath = `${BASE_URL}/js/pdfjs/pdf.worker.mjs`;
    // --- [AKHIR PERBAIKAN] ---

    // --- Import PDF.js ---
    let pdfjsLib;
    try {
        pdfjsLib = await import(pdfjsLibPath);
        if (pdfjsLib.GlobalWorkerOptions) {
            pdfjsLib.GlobalWorkerOptions.workerSrc = pdfjsWorkerPath;
        }
    } catch (error) {
        console.error("Gagal memuat PDF.js:", error);
    }

    // --- Variabel Global PDF Viewer ---
    const modal = document.getElementById('pdfPreviewModal');
    const modalTitle = document.getElementById('pdfModalTitle');
    const modalBody = modal ? modal.querySelector('.pdf-modal-body') : null;
    const canvas = document.getElementById('pdfCanvas');
    const ctx = canvas ? canvas.getContext('2d') : null;
    const loadingDiv = document.getElementById('pdfLoading');
    const prevButton = document.getElementById('pdfPrevPage');
    const nextButton = document.getElementById('pdfNextPage');
    const currentPageSpan = document.getElementById('pdfCurrentPage');
    const totalPagesSpan = document.getElementById('pdfTotalPages');
    const zoomInButton = document.getElementById('pdfZoomIn');
    const zoomOutButton = document.getElementById('pdfZoomOut');
    const zoomResetButton = document.getElementById('pdfZoomReset');
    const currentZoomSpan = document.getElementById('pdfCurrentZoom');
    let currentPdfDoc = null;
    let currentPageNum = 1;
    let totalPages = 0;
    let currentScale = 1.0; 
    const ZOOM_STEP = 0.25;
    const MIN_ZOOM = 0.25;
    const MAX_ZOOM = 3.0;
    let fitWidthScale = 1.0; // Variabel untuk menyimpan skala Fit Width
    let isRendering = false;

    // --- Fungsi Render Halaman ---
    async function renderPage(num) {
        if (!currentPdfDoc || isRendering || !canvas || !modalBody) return;
        isRendering = true;
        if(loadingDiv) loadingDiv.classList.remove('hidden'); 
        if(canvas) canvas.classList.add('hidden');
        try {
            const page = await currentPdfDoc.getPage(num);
            const viewport = page.getViewport({ scale: currentScale });
            canvas.height = viewport.height; canvas.width = viewport.width;
            const renderContext = { canvasContext: ctx, viewport: viewport };
            await page.render(renderContext).promise;
            currentPageNum = num; 
            if(currentPageSpan) currentPageSpan.textContent = currentPageNum;
            if(prevButton) prevButton.disabled = (currentPageNum <= 1);
            if(nextButton) nextButton.disabled = (currentPageNum >= totalPages);
            if(currentZoomSpan) { currentZoomSpan.textContent = Math.round(currentScale * 100); }
            if(zoomInButton) zoomInButton.disabled = (currentScale >= MAX_ZOOM);
            if(zoomOutButton) zoomOutButton.disabled = (currentScale <= MIN_ZOOM);
        } catch (error) { console.error('Error rendering page:', error); alert('Gagal merender halaman PDF.');
        } finally { 
            isRendering = false; 
            if(loadingDiv) loadingDiv.classList.add('hidden'); 
            if(canvas) canvas.classList.remove('hidden'); 
        }
    }

    // --- Fungsi Navigasi & Zoom ---
    function goToPrevPage() { if (currentPageNum <= 1 || isRendering) return; renderPage(currentPageNum - 1); }
    function goToNextPage() { if (currentPageNum >= totalPages || isRendering) return; renderPage(currentPageNum + 1); }
    function zoomIn() { if (currentScale >= MAX_ZOOM || isRendering) return; currentScale += ZOOM_STEP; renderPage(currentPageNum); }
    function zoomOut() { if (currentScale <= MIN_ZOOM || isRendering) return; currentScale -= ZOOM_STEP; renderPage(currentPageNum); }
    
    // --- [PERBAIKAN FUNGSI ZOOM] ---
    // Mengembalikan ke logika "Fit Width" sesuai file asli Anda
    function resetZoom() { 
         if (isRendering || !currentPdfDoc || !modalBody) return;
         // Set ke skala Fit Width yang sudah disimpan
         currentScale = fitWidthScale;
         renderPage(currentPageNum);
    }
    // --- [AKHIR PERBAIKAN FUNGSI ZOOM] ---

    // --- Fungsi Buka Modal & Load PDF ---
    async function openPdfPreviewModal(pdfPath, title) {
        if (!pdfjsLib || !modal || !modalBody) { alert("PDF viewer tidak dapat dimuat."); return; }
        currentPdfDoc = null; currentPageNum = 1; totalPages = 0;
        if(currentPageSpan) currentPageSpan.textContent = 0; 
        if(totalPagesSpan) totalPagesSpan.textContent = 0;
        if(prevButton) prevButton.disabled = true; 
        if(nextButton) nextButton.disabled = true;
        if(modalTitle) modalTitle.textContent = title || "Preview Dokumen";
        if(loadingDiv) loadingDiv.classList.remove('hidden'); 
        if(canvas) canvas.classList.add('hidden');
        modal.classList.remove('hidden'); modal.style.display = 'flex';
        try {
            // --- [PERBAIKAN PATH: MENGGUNAKAN BASE_URL] ---
            const correctedPdfPath = `${BASE_URL}/${pdfPath}`; 
            // --- [AKHIR PERBAIKAN] ---
            
            const loadingTask = pdfjsLib.getDocument(correctedPdfPath); 
            currentPdfDoc = await loadingTask.promise;
            totalPages = currentPdfDoc.numPages; 
            if(totalPagesSpan) totalPagesSpan.textContent = totalPages;
            
            // Hitung dan simpan skala 'fit-width'
            const firstPage = await currentPdfDoc.getPage(1);
            const originalViewport = firstPage.getViewport({ scale: 1 });
            const containerWidth = modalBody.clientWidth - 20; // Kurangi padding
            fitWidthScale = containerWidth / originalViewport.width;
            currentScale = fitWidthScale; // Mulai dengan fit width
            
            await renderPage(1); // Render halaman pertama
        } catch (error) { console.error('Error loading PDF:', error); alert('Gagal memuat file PDF. Pastikan file valid dan path benar: ' + pdfPath); closePdfPreviewModal(); }
    }

    // --- Fungsi Tutup Modal PDF ---
    function closePdfPreviewModal() {
        if (!modal || !ctx) return;
        modal.classList.add('hidden'); modal.style.display = 'none';
        currentPdfDoc = null;
        if(ctx) ctx.clearRect(0, 0, canvas.width, canvas.height);
    }

    // --- Event Listeners & Global Functions ---
    if(prevButton) prevButton.addEventListener('click', goToPrevPage);
    if(nextButton) nextButton.addEventListener('click', goToNextPage);
    if(zoomInButton) zoomInButton.addEventListener('click', zoomIn);
    if(zoomOutButton) zoomOutButton.addEventListener('click', zoomOut);
    if(zoomResetButton) zoomResetButton.addEventListener('click', resetZoom);
    window.openPdfPreviewModal = openPdfPreviewModal;
    window.closePdfPreviewModal = closePdfPreviewModal;

</script>

<?php
// 9. Include template footer
// === [PATH DIPERBAIKI] ===
require '../../includes/footer.php';
// === [AKHIR PERUBAHAN] ===
?>