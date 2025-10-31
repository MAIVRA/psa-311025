<?php
// register_number.php

// 1. Set variabel khusus untuk halaman ini
$page_title = "Register Nomor Surat";
$page_active = "view_registry";

// 2. Include template header
// === [PATH DIPERBAIKI] ===
require '../../includes/header.php';
// === [AKHIR PERUBAHAN] ===

// 3. Ambil ID user yg login
$creator_user_id = $_SESSION['user_id'];
$creator_user_name = $_SESSION['nama_lengkap'];

// 4. Inisialisasi variabel
$error_message = '';
$success_message = '';
$document_codes = [];
$signer_list = [];
$all_users_list = [];

// 5. Buka koneksi baru & ambil data dropdown
$conn_get = new mysqli($servername, $username, $password, $dbname);
if ($conn_get->connect_error) { die("Koneksi Gagal: " . $conn_get->connect_error); }
$conn_get->set_charset("utf8mb4");

try {
    // Ambil daftar kode dokumen
    $result_codes = $conn_get->query("SELECT id, kode_surat, deskripsi FROM document_codes ORDER BY kode_surat ASC");
    if (!$result_codes) throw new Exception("Gagal mengambil daftar kode dokumen: " . $conn_get->error);
    while ($row = $result_codes->fetch_assoc()) { $document_codes[] = $row; }

    // Ambil daftar penandatangan
    $sql_signers = " (SELECT nama_lengkap, jabatan AS jabatan_display, 1 AS sort_order FROM board_members bm JOIN companies c ON bm.company_id = c.id WHERE c.nama_perusahaan = 'PT PUTRA NATUR UTAMA' AND bm.jabatan IN ('Direktur Utama', 'Direktur') AND bm.nama_lengkap IS NOT NULL AND bm.nama_lengkap != '' ) UNION (SELECT nama_lengkap, COALESCE(nama_jabatan, tier) AS jabatan_display, 2 AS sort_order FROM users WHERE (tier = 'Manager' OR nama_jabatan = 'GM') AND nama_lengkap IS NOT NULL AND nama_lengkap != '' ) ORDER BY sort_order ASC, nama_lengkap ASC ";
    $result_signers = $conn_get->query($sql_signers);
    if (!$result_signers) throw new Exception("Gagal mengambil daftar penandatangan: " . $conn_get->error);
    $signer_names = [];
    while ($row = $result_signers->fetch_assoc()) { if (!in_array($row['nama_lengkap'], $signer_names)) { $signer_list[] = $row; $signer_names[] = $row['nama_lengkap']; } }

    // Ambil daftar SEMUA user untuk multi-select akses karyawan
    $result_all_users = $conn_get->query("SELECT id, nama_lengkap, nik FROM users ORDER BY nama_lengkap ASC");
    if (!$result_all_users) throw new Exception("Gagal mengambil daftar semua user: " . $conn_get->error);
    while ($row = $result_all_users->fetch_assoc()) { $all_users_list[] = $row; }

} catch (Exception $e) { $error_message = "Error mengambil data: " . $e->getMessage(); }


// 6. Logika saat form di-submit (method POST - Simpan Registrasi)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Re-open connection if needed
    if (!$conn_get->ping()) {
        $conn_get = new mysqli($servername, $username, $password, $dbname);
        $conn_get->set_charset("utf8mb4");
    }

    // Mulai Transaksi
    $conn_get->begin_transaction();
    try {
        // Ambil data dari form POST
        $document_code_id = (int)$_POST['document_code_id'];
        $tanggal_surat = $_POST['tanggal_surat'];
        $nomor_urut_generated = (int)$_POST['nomor_urut_generated'];
        $nomor_lengkap_generated = trim($_POST['nomor_lengkap_generated']);
        $penandatangan_nama = !empty($_POST['penandatangan']) ? trim($_POST['penandatangan']) : NULL;
        $ditujukan_kepada = !empty($_POST['ditujukan_kepada']) ? trim($_POST['ditujukan_kepada']) : NULL;
        $perihal = !empty($_POST['perihal']) ? trim($_POST['perihal']) : NULL;
        $isi_ringkas = !empty($_POST['isi_ringkas']) ? trim($_POST['isi_ringkas']) : NULL;
        $tipe_dokumen = $_POST['tipe_dokumen'];
        if (!isset($_POST['akses_dokumen'])) { throw new Exception("Data Akses Dokumen tidak terkirim. Coba refresh halaman."); }
        $akses_dokumen = $_POST['akses_dokumen'];


        // Ambil data akses terbatas (hanya jika akses_dokumen = 'Terbatas')
        $akses_terbatas_level = NULL;
        $akses_karyawan_ids_json = NULL;

        // Validasi konsistensi Tipe -> Akses (Wajib)
         if (($tipe_dokumen == 'Rahasia' && $akses_dokumen != 'Dilarang') ||
             ($tipe_dokumen == 'Terbatas' && $akses_dokumen != 'Terbatas') ||
             ($tipe_dokumen == 'Umum' && $akses_dokumen != 'Semua')) {
             $expected_akses = '';
             if($tipe_dokumen == 'Rahasia') $expected_akses = 'Dilarang';
             else if($tipe_dokumen == 'Terbatas') $expected_akses = 'Terbatas';
             else $expected_akses = 'Semua';
             throw new Exception("Ketidaksesuaian: Tipe Dokumen '$tipe_dokumen' seharusnya memiliki Akses Dokumen '$expected_akses', tetapi terkirim '$akses_dokumen'. Coba refresh halaman.");
         }


        if ($akses_dokumen == 'Terbatas') {
            $akses_terbatas_level = $_POST['akses_terbatas_level'] ?? NULL;
            if (empty($akses_terbatas_level)) { throw new Exception("Level Akses Terbatas wajib dipilih."); }
            if ($akses_terbatas_level == 'Karyawan') {
                $selected_karyawan_ids = $_POST['akses_karyawan_ids'] ?? [];
                if (empty($selected_karyawan_ids)) { throw new Exception("Pilih minimal satu karyawan."); }
                $valid_ids = [];
                foreach ($selected_karyawan_ids as $kid) { if (filter_var($kid, FILTER_VALIDATE_INT)) { $valid_ids[] = (int)$kid; } }
                if (empty($valid_ids)) { throw new Exception("Pilihan karyawan tidak valid."); }
                $akses_karyawan_ids_json = json_encode($valid_ids);
            }
        }

        // Validasi Dasar Lainnya
        if (empty($document_code_id) || empty($tanggal_surat) || empty($nomor_lengkap_generated) || $nomor_urut_generated <= 0) { throw new Exception("Data tidak lengkap atau nomor surat belum digenerate."); }
        if (empty($perihal)) { throw new Exception("Perihal/Judul Surat wajib diisi."); }


        // Ekstrak bulan dan tahun
        $tanggal_dt = new DateTime($tanggal_surat);
        $bulan = (int)$tanggal_dt->format('n'); $tahun = (int)$tanggal_dt->format('Y');

        // Verifikasi Ulang Nomor Urut
        $stmt_verify = $conn_get->prepare("SELECT MAX(nomor_urut) AS last_num FROM document_registry WHERE document_code_id = ? AND tahun = ? FOR UPDATE");
        if (!$stmt_verify) throw new Exception("Gagal prepare verifikasi: " . $conn_get->error);
        $stmt_verify->bind_param("is", $document_code_id, $tahun); $stmt_verify->execute(); $result_verify = $stmt_verify->get_result(); $last_num_row = $result_verify->fetch_assoc();
        $last_num_db = $last_num_row['last_num'] ? (int)$last_num_row['last_num'] : 0; $next_num_expected = $last_num_db + 1; $stmt_verify->close();
        if ($nomor_urut_generated != $next_num_expected) { throw new Exception("Konflik penomoran (Expected $next_num_expected, got $nomor_urut_generated). Generate ulang."); }

        // Insert ke tabel document_registry
        $sql_insert = "INSERT INTO document_registry ( document_code_id, nomor_urut, bulan, tahun, nomor_lengkap, tanggal_surat, perihal, penandatangan, ditujukan_kepada, isi_ringkas, created_by_id, tipe_dokumen, akses_dokumen, akses_terbatas_level, akses_karyawan_ids ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = $conn_get->prepare($sql_insert);
        if ($stmt_insert === false) throw new Exception("Error persiapan statement insert: " . $conn_get->error);
        $stmt_insert->bind_param("iiiissssssissss", $document_code_id, $nomor_urut_generated, $bulan, $tahun, $nomor_lengkap_generated, $tanggal_surat, $perihal, $penandatangan_nama, $ditujukan_kepada, $isi_ringkas, $creator_user_id, $tipe_dokumen, $akses_dokumen, $akses_terbatas_level, $akses_karyawan_ids_json );

        if (!$stmt_insert->execute()) { throw new Exception("Gagal menyimpan registrasi surat: " . $stmt_insert->error); }
        $stmt_insert->close();

        // Commit Transaksi
        $conn_get->commit();
        $conn_get->close();

        $_SESSION['flash_message'] = "Nomor Surat '" . htmlspecialchars($nomor_lengkap_generated) . "' berhasil diregistrasi!";
        
        // === [PERBAIKAN PATH: MENGGUNAKAN BASE_URL] ===
        header("Location: " . BASE_URL . "/modules/registry/view_registry.php");
        // === [AKHIR PERBAIKAN] ===
        exit;

    } catch (Exception $e) {
        $conn_get->rollback();
        $error_message = "Error: " . $e->getMessage();
    }

} // Akhir dari method POST

// Tutup koneksi jika masih terbuka
if ($conn_get->ping()) {
    $conn_get->close();
}

// Data statis untuk dropdown
$list_tipe_dokumen = ['Umum', 'Terbatas', 'Rahasia'];
$list_akses_dokumen = ['Semua', 'Terbatas', 'Dilarang'];
$list_akses_terbatas = ['Direksi', 'Manager', 'Karyawan'];


// 7. Include template sidebar
// === [PATH DIPERBAIKI] ===
require '../../includes/sidebar.php';
// === [AKHIR PERUBAHAN] ===
?>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">

    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800"><?php echo $page_title; ?></h1>
        <a href="<?php echo BASE_URL; ?>/modules/registry/view_registry.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg text-sm font-semibold transition duration-200 flex items-center no-underline">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Registrasi
        </a>
    </div>

     <?php if (!empty($error_message)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
        </div>
    <?php endif; ?>

    <form id="registerForm" action="<?php echo BASE_URL; ?>/modules/registry/register_number.php" method="POST" class="bg-white p-6 rounded-lg shadow-md space-y-6 max-w-3xl mx-auto">
    <input type="hidden" name="nomor_urut_generated" id="nomor_urut_generated">
        <input type="hidden" name="nomor_lengkap_generated" id="nomor_lengkap_generated">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="tanggal_surat" class="form-label">Tanggal Surat <span class="text-red-500">*</span></label>
                <input type="date" id="tanggal_surat" name="tanggal_surat" class="form-input" required value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div>
                <label for="document_code_id" class="form-label">Jenis Surat <span class="text-red-500">*</span></label>
                <select id="document_code_id" name="document_code_id" class="form-input" required>
                    <option value="" disabled selected>Pilih Jenis Surat</option>
                    <?php foreach ($document_codes as $code): ?>
                        <option value="<?php echo $code['id']; ?>" data-kode="<?php echo htmlspecialchars($code['kode_surat']); ?>">
                            <?php echo htmlspecialchars($code['kode_surat']); ?> - <?php echo htmlspecialchars($code['deskripsi']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="flex items-end space-x-4">
            <div class="flex-grow">
                <label for="nomor_surat_preview" class="form-label">Nomor Surat (Otomatis)</label>
                <input type="text" id="nomor_surat_preview" class="form-input bg-gray-100 text-lg font-mono" readonly placeholder="Klik Generate ->">
                <div id="generate_error" class="text-xs text-red-600 mt-1 h-3"></div>
            </div>
            <button type="button" id="generateButton" class="bg-yellow-500 hover:bg-yellow-600 text-white px-5 py-2.5 rounded-lg font-semibold transition duration-200 h-10">Generate</button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="penandatangan" class="form-label">Ditandatangani Oleh</label>
                <select id="penandatangan" name="penandatangan" class="form-input">
                    <option value="">Pilih Pejabat</option>
                    <?php foreach ($signer_list as $signer): ?>
                        <option value="<?php echo htmlspecialchars($signer['nama_lengkap']); ?>">
                            <?php echo htmlspecialchars($signer['nama_lengkap']); ?> (<?php echo htmlspecialchars($signer['jabatan_display']); ?>)
                        </option>
                    <?php endforeach; ?>
                    <?php if (empty($signer_list)): ?>
                        <option value="" disabled>Belum ada data Manager/GM/Direksi PNU</option>
                    <?php endif; ?>
                </select>
            </div>
            <div>
                <label for="ditujukan_kepada" class="form-label">Ditujukan Kepada</label>
                <input type="text" id="ditujukan_kepada" name="ditujukan_kepada" class="form-input" placeholder="Nama/Instansi Tujuan">
            </div>
        </div>
        <div>
            <label for="perihal" class="form-label">Perihal / Judul Surat <span class="text-red-500">*</span></label>
            <input type="text" id="perihal" name="perihal" class="form-input" required>
        </div>
        <div>
            <label for="isi_ringkas" class="form-label">Isi Ringkas Surat</label>
            <textarea id="isi_ringkas" name="isi_ringkas" rows="3" class="form-input"></textarea>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-gray-200">
            <div>
                <label for="tipe_dokumen" class="form-label">Tipe Dokumen <span class="text-red-500">*</span></label>
                <select id="tipe_dokumen" name="tipe_dokumen" class="form-input" required>
                    <?php foreach ($list_tipe_dokumen as $tipe): ?>
                        <option value="<?php echo $tipe; ?>" <?php if ($tipe == 'Umum') echo 'selected'; ?>><?php echo $tipe; ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">Klasifikasi kerahasiaan dokumen.</p>
            </div>
             <div>
                <label for="akses_dokumen" class="form-label">Akses File Scan <span class="text-red-500">*</span></label>
                <select id="akses_dokumen" name="akses_dokumen" class="form-input" required>
                    <?php foreach ($list_akses_dokumen as $akses): ?>
                         <option value="<?php echo $akses; ?>" <?php if ($akses == 'Semua') echo 'selected'; ?>><?php echo $akses; ?></option>
                    <?php endforeach; ?>
                </select>
                <p id="deskripsi_akses" class="text-xs text-blue-600 mt-1"> </p>
            </div>
        </div>

         <div id="aksesTerbatasSection" class="hidden space-y-4 pt-4 border-t border-dashed border-gray-300">
            <h4 class="text-md font-semibold text-gray-700">Detail Akses Terbatas</h4>
            <div>
                <label for="akses_terbatas_level" class="form-label">Berikan Akses Kepada <span class="text-red-500">*</span></label>
                <select id="akses_terbatas_level" name="akses_terbatas_level" class="form-input">
                    <option value="" disabled selected>Pilih Level Akses</option>
                    <?php foreach ($list_akses_terbatas as $level): ?>
                        <option value="<?php echo $level; ?>"><?php echo $level; ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">Siapa saja (selain Penginput & Direksi) yang dapat mengakses file scan.</p>
            </div>
            <div id="pilihKaryawanSection" class="hidden">
                <label for="akses_karyawan_ids" class="form-label">Pilih Karyawan Spesifik <span class="text-red-500">*</span></label>
                <select id="akses_karyawan_ids" name="akses_karyawan_ids[]" class="form-input h-40" multiple>
                    <?php foreach ($all_users_list as $user): ?>
                        <option value="<?php echo $user['id']; ?>">
                            <?php echo htmlspecialchars($user['nama_lengkap']); ?> (<?php echo htmlspecialchars($user['nik']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1">Tahan Ctrl (atau Cmd di Mac) untuk memilih lebih dari satu.</p>
            </div>
         </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-gray-200">
            <div>
                <label for="input_by" class="form-label">Diinput Oleh</label>
                <input type="text" id="input_by" class="form-input bg-gray-100" readonly value="<?php echo htmlspecialchars($creator_user_name); ?>">
            </div>
            <div>
                <label for="input_date" class="form-label">Tanggal Input</label>
                <input type="text" id="input_date" class="form-input bg-gray-100" readonly value="<?php echo date('d M Y H:i:s'); ?>">
            </div>
        </div>

        <div class="mt-8 pt-6 border-t flex justify-end">
            <button type="submit" id="saveButton" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition duration-200 flex items-center disabled:opacity-50" disabled>
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                Simpan Registrasi
            </button>
        </div>
    </form>

</main>
<script>
    // --- Script untuk Generate Nomor Surat via AJAX ---
    (function() {
        // === [PERBAIKAN PATH: MENGGUNAKAN BASE_URL] ===
        const BASE_URL = '<?php echo BASE_URL; ?>';
        const AJAX_URL = `${BASE_URL}/modules/registry/ajax_generate_doc_number.php`;
        // === [AKHIR PERBAIKAN] ===

        const tglSuratInput = document.getElementById('tanggal_surat');
        const jenisSuratSelect = document.getElementById('document_code_id');
        const generateButton = document.getElementById('generateButton');
        const previewInput = document.getElementById('nomor_surat_preview');
        const errorDiv = document.getElementById('generate_error');
        const saveButton = document.getElementById('saveButton');
        const nomorUrutHidden = document.getElementById('nomor_urut_generated');
        const nomorLengkapHidden = document.getElementById('nomor_lengkap_generated');
        
        function resetFormState() {
            previewInput.value = '';
            previewInput.placeholder = 'Klik Generate ->';
            errorDiv.textContent = '';
            saveButton.disabled = true;
            nomorUrutHidden.value = '';
            nomorLengkapHidden.value = '';
        }
        
        function generateNumber() {
            const tglSurat = tglSuratInput.value;
            const docCodeId = jenisSuratSelect.value;
            const selectedOption = jenisSuratSelect.options[jenisSuratSelect.selectedIndex];
            const docCode = selectedOption ? selectedOption.dataset.kode : '';
            
            resetFormState();
            
            if (!tglSurat || !docCodeId) {
                errorDiv.textContent = 'Pilih Tanggal dan Jenis Surat.';
                return;
            }
            
            previewInput.placeholder = 'Generating...';
            generateButton.disabled = true;
            errorDiv.textContent = '';
            
            // === [PERBAIKAN PATH: MENGGUNAKAN BASE_URL] ===
            fetch(`${AJAX_URL}?doc_code_id=${docCodeId}&tanggal_surat=${tglSurat}`)
            // === [AKHIR PERBAIKAN] ===
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        previewInput.value = data.nomor_lengkap;
                        nomorUrutHidden.value = data.nomor_urut_selanjutnya;
                        nomorLengkapHidden.value = data.nomor_lengkap;
                        saveButton.disabled = false;
                    } else {
                        previewInput.placeholder = 'Klik Generate ->';
                        errorDiv.textContent = data.error || 'Gagal generate nomor.';
                    }
                })
                .catch(error => {
                    console.error('Error fetching doc number:', error);
                    previewInput.placeholder = 'Klik Generate ->';
                    errorDiv.textContent = 'Error: Gagal menghubungi server.';
                })
                .finally(() => {
                    generateButton.disabled = false;
                });
        }
        
        generateButton.addEventListener('click', generateNumber);
        tglSuratInput.addEventListener('change', resetFormState);
        jenisSuratSelect.addEventListener('change', resetFormState);
        
        const registerForm = document.getElementById('registerForm');
        registerForm.addEventListener('submit', function(event) {
            if (saveButton.disabled || !nomorLengkapHidden.value) {
                event.preventDefault();
                alert('Harap generate nomor surat terlebih dahulu sebelum menyimpan.');
            }
        });
    })();

    // --- Script untuk Tipe & Akses Dokumen ---
    (function() {
        const tipeDokumenSelect = document.getElementById('tipe_dokumen');
        const aksesDokumenSelect = document.getElementById('akses_dokumen');
        const deskripsiAksesP = document.getElementById('deskripsi_akses');
        const aksesTerbatasSection = document.getElementById('aksesTerbatasSection');
        const aksesLevelSelect = document.getElementById('akses_terbatas_level');
        const pilihKaryawanSection = document.getElementById('pilihKaryawanSection');

        if (!tipeDokumenSelect || !aksesDokumenSelect || !deskripsiAksesP || !aksesTerbatasSection || !aksesLevelSelect || !pilihKaryawanSection) return;

        // Deskripsi untuk setiap pilihan akses
        const deskripsiMap = {
            'Semua': 'Semua karyawan dan manajemen dapat mengakses dokumen ini.',
            'Terbatas': 'Akses file scan dibatasi sesuai pilihan di bawah.',
            'Dilarang': 'Hanya pengupload dan Direksi yang dapat mengakses dokumen ini.'
        };

        // Flag untuk menandai apakah dropdown akses sedang "terkunci"
        let isAksesLocked = true; // Awalnya terkunci karena default Tipe = Umum

        function handleTipeChange() {
            const tipe = tipeDokumenSelect.value;
            let targetAkses = '';

            aksesTerbatasSection.classList.add('hidden');
            pilihKaryawanSection.classList.add('hidden');
            aksesLevelSelect.value = '';

            if (tipe === 'Rahasia') {
                targetAkses = 'Dilarang';
                isAksesLocked = true;
            } else if (tipe === 'Terbatas') {
                targetAkses = 'Terbatas';
                isAksesLocked = true;
                aksesTerbatasSection.classList.remove('hidden');
                handleLevelChange();
            } else { // Umum
                targetAkses = 'Semua';
                isAksesLocked = true;
            }

            // Set nilai akses dokumen
            aksesDokumenSelect.value = targetAkses;

            // Atur style readonly tapi JANGAN disable
            if (isAksesLocked) {
                aksesDokumenSelect.classList.add('bg-gray-100', 'cursor-not-allowed');
            } else {
                 aksesDokumenSelect.classList.remove('bg-gray-100', 'cursor-not-allowed');
            }

            // Set deskripsinya
            deskripsiAksesP.textContent = deskripsiMap[targetAkses] || '';
        }

        function handleLevelChange() {
             const level = aksesLevelSelect.value;
             if (level === 'Karyawan') {
                 pilihKaryawanSection.classList.remove('hidden');
             } else {
                 pilihKaryawanSection.classList.add('hidden');
             }
        }

        // === [EVENT LISTENER BARU untuk Mencegah Klik] ===
        aksesDokumenSelect.addEventListener('mousedown', function(event) {
            // Jika dropdown sedang terkunci oleh Tipe Dokumen, cegah dropdown terbuka
            if (isAksesLocked) {
                event.preventDefault();
                // Opsional: Beri feedback visual sedikit
                this.blur(); // Hapus fokus
            }
        });
         // === [AKHIR EVENT LISTENER BARU] ===


        // Panggil saat halaman load
        handleTipeChange();
        handleLevelChange();

        // Panggil saat Tipe Dokumen atau Level Akses diubah
        tipeDokumenSelect.addEventListener('change', handleTipeChange);
        aksesLevelSelect.addEventListener('change', handleLevelChange);

    })();
</script>


<?php
// 9. Include template footer
// === [PATH DIPERBAIKI] ===
require '../../includes/footer.php';
// === [AKHIR PERUBAHAN] ===
?>