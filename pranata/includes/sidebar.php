<?php
// includes/sidebar.php

// Ambil variabel $page_active yang sudah di-set oleh file pemanggil
// Ambil juga data user dari session yang sudah di-set oleh header.php
// $nama_lengkap

// === [LOGIKA HAK AKSES BARU] ===
// Ambil data dari session (pastikan ada fallback jika session belum ada)
$tier = $_SESSION['tier'] ?? 'Staf';
$app_akses = $_SESSION['app_akses'] ?? 'Karyawan'; // Menggunakan 'app_akses'

// Buat flag boolean untuk kemudahan
$is_admin = ($app_akses == 'Admin'); // Admin adalah app_akses
$is_top_management = ($app_akses == 'Top Management');
$is_hr = ($app_akses == 'HR');
$is_legal = ($app_akses == 'Legal');
$is_finance = ($app_akses == 'Finance');

// Cek apakah user adalah atasan (untuk menu Persetujuan Cuti)
$is_atasan = (in_array($tier, ['Manager', 'Supervisor', 'Direksi', 'Komisaris', 'Admin']));
// === [AKHIR LOGIKA HAK AKSES BARU] ===


// --- [PERUBAHAN] Tentukan grup halaman aktif untuk auto-open accordion ---
$karyawan_menu_pages = [
    'presensi', 'request_leave', 'approve_leave', 
    'benefit', // <-- [PENAMBAHAN DARI SEBELUMNYA]
    'laporan_pekerjaan', 
    'pelatihan_list', 'assessment_list', 'view_registry', 'register_number', 
    'lembur', 'perjalanan_dinas_list', 'email_client'
];
$is_karyawan_menu_open = in_array($page_active, $karyawan_menu_pages);

$tm_menu_pages = [
    'tm_korporat', 'tm_daftar_karyawan', 'tm_laporan_pekerjaan', 'approve_cuti_massal', 
    'tm_dokumen', 'tm_laporan_keuangan', 'tm_perjanjian', 'tm_perijinan', 
    'tm_aksi_korporat', 'tm_masalah_hukum', 'tm_haki'
];
$is_tm_menu_open = in_array($page_active, $tm_menu_pages);

// === [PERUBAHAN DI SINI] ===
// Menambahkan 'hr_laporan_pajak' ke dalam array menu HR
$hr_menu_pages = [
    'hr_daftar_karyawan', 'presensi_report', 'hr_daftar_cuti', 'hr_cuti_massal', 
    'hr_struktur_upah', 'hr_master_gaji', 'hr_payroll_settings', 'hr_hitung_gaji', 'hr_slip_gaji', 
    'hr_hitung_thr', 'hr_bukti_potong', 
    'hr_laporan_pajak', // <-- PENAMBAHAN BARU
    'hr_assessment', 'hr_pelatihan', 'hr_pk', 'hr_mutasi', 'hr_sp', 'hr_pp', 'hr_skk', 
    'hr_perjalanan_dinas'
];
// === [AKHIR PERUBAHAN] ===
$is_hr_menu_open = in_array($page_active, $hr_menu_pages);

$legal_menu_pages = [
    'manage_companies', 'legal_perjanjian', 'legal_perijinan', 'legal_aksi_korporat', 
    'legal_masalah_hukum', 'legal_haki', 'legal_asset'
];
// Jika 'manage_companies' aktif, buka 'Legal' (kecuali jika 'Admin' juga aktif)
$is_legal_menu_open = in_array($page_active, $legal_menu_pages) && !$is_admin;


$finance_menu_pages = [
    'fin_laporan_keuangan', 'fin_perpajakan', 'fin_payroll', 
    'fin_pembayaran_vendor', 'fin_bon_sementara', 'fin_hutang_piutang'
];
$is_finance_menu_open = in_array($page_active, $finance_menu_pages);

$admin_menu_pages = [
    'manage_users', 'manage_companies', 'document_codes', 'manage_struktur', 'presensi_report'
];
// Jika admin, 'manage_companies' akan membuka 'Master Data'
$is_admin_menu_open = in_array($page_active, $admin_menu_pages) && $is_admin;


// --- FUNGSI HELPER UNTUK RENDER MENU (TAILWIND) ---

// --- Helper Ikon SVG ---
function getIconSVG($iconName) {
    $svgIcons = [
        'presensi' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
        'cuti' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>',
        'approve' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
        'laporan' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>',
        'pelatihan' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v11.494m0 0L20.25 12M12 17.747L3.75 12M12 6.253L20.25 12"></path>',
        'assessment' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>',
        'registry' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>',
        'lembur' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
        'perjadin' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l5.447 2.724A1 1 0 0021 16.382V5.618a1 1 0 00-1.447-.894L15 7m-6 3v3m6-3v3"></path>',
        'email' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>',
        'building' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />',
        'users' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />',
        'folder' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H5a2 2 0 00-2 2v5a2 2 0 002 2z" />',
        'wallet' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />',
        'scale' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2" />',
        'briefcase' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />',
        'file-text' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />',
        'award' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13V1a1 1 0 011-1h12a1 1 0 011 1v12a1 1 0 01-1 1H6a1 1 0 01-1-1zm5 6H3a1 1 0 00-1 1v2a1 1 0 001 1h7a1 1 0 001-1v-2a1 1 0 00-1-1zm10-4a3 3 0 11-6 0 3 3 0 016 0z" />',
        'cog' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924-1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />',
        'at' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />',
        'map-pin' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />',
        'chart-pie' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />',
        'receipt' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />',
        'note' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h8a1 1 0 001-1z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.375 12.25A1.125 1.125 0 0117.25 11H8.75a1.125 1.125 0 01-1.125-1.125V3.625A1.125 1.125 0 018.75 2.5h8.5A1.125 1.125 0 0118.375 3.625v8.625z" />',
        'exchange' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m-5 3H4m0 0l4 4m-4-4l4-4" />',
        'chevron' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>',
    ];
    
    $iconPath = $svgIcons[$iconName] ?? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>'; // Default (plus)
    return '<svg class="inline-block w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">' . $iconPath . '</svg>';
}

// [PERUBAHAN] Fungsi Header Accordion dimodifikasi
function renderAccordionHeader($toggleId, $title, $isOpen = false)
{
    // Tentukan class untuk ikon berdasarkan status $isOpen
    $svgClass = $isOpen ? 'w-4 h-4 transition-transform rotate-90' : 'w-4 h-4 transition-transform';
    
    echo '<button type="button" class="sidebar-link w-full flex justify-between items-center bg-blue-600 text-white hover:bg-blue-700 hover:text-white" data-menu-toggle="' . $toggleId . '">';
    echo '  <span class="text-sm font-bold uppercase tracking-wider">' . $title . '</span>';
    // Terapkan $svgClass di sini
    echo '  <svg class="' . $svgClass . '" fill="none" stroke="currentColor" viewBox="0 0 24 24">' . getIconSVG('chevron') . '</svg>';
    echo '</button>';
}

function renderKaryawanMenu($page_active, $is_atasan)
{
    echo '  <div class="space-y-1 mt-2">';
    
    echo '    <a href="' . BASE_URL . '/modules/presensi/index.php" class="sidebar-link ' . ($page_active == 'presensi' ? 'sidebar-link-active' : '') . '">';
    echo getIconSVG('presensi') . ' Presensi';
    echo '    </a>';
    
    echo '    <a href="' . BASE_URL . '/modules/leave/request_leave.php" class="sidebar-link ' . ($page_active == 'request_leave' ? 'sidebar-link-active' : '') . '">';
    echo getIconSVG('cuti') . ' Pengajuan Cuti';
    echo '    </a>';
    
    // === [PENAMBAHAN DARI SEBELUMNYA] ===
    echo '    <a href="' . BASE_URL . '/modules/benefit/index.php" class="sidebar-link ' . ($page_active == 'benefit' ? 'sidebar-link-active' : '') . '">';
    echo getIconSVG('wallet') . ' Benefit Karyawan'; // Menggunakan ikon 'wallet'
    echo '    </a>';
    // === [AKHIR PENAMBAHAN] ===
    
    if ($is_atasan) {
        echo '  <a href="' . BASE_URL . '/modules/leave/approve_leave.php" class="sidebar-link ' . ($page_active == 'approve_leave' ? 'sidebar-link-active' : '') . '">';
        echo getIconSVG('approve') . ' Persetujuan Cuti';
        echo '  </a>';
    }

    echo '    <a href="#" class="sidebar-link ' . ($page_active == 'laporan_pekerjaan' ? 'sidebar-link-active' : '') . '">';
    echo getIconSVG('laporan') . ' Laporan Pekerjaan';
    echo '    </a>';
    echo '    <a href="#" class="sidebar-link ' . ($page_active == 'pelatihan_list' ? 'sidebar-link-active' : '') . '">';
    echo getIconSVG('pelatihan') . ' Pelatihan';
    echo '    </a>';
    echo '    <a href="#" class="sidebar-link ' . ($page_active == 'assessment_list' ? 'sidebar-link-active' : '') . '">';
    echo getIconSVG('assessment') . ' Assessment';
    echo '    </a>';
    
    echo '    <a href="' . BASE_URL . '/modules/registry/view_registry.php" class="sidebar-link ' . (in_array($page_active, ['view_registry', 'register_number']) ? 'sidebar-link-active' : '') . '">';
    echo getIconSVG('registry') . ' Daftar Registrasi Surat';
    echo '    </a>';
    
    echo '    <a href="#" class="sidebar-link ' . ($page_active == 'lembur' ? 'sidebar-link-active' : '') . '">';
    echo getIconSVG('lembur') . ' Lembur';
    echo '    </a>';
    echo '    <a href="#" class="sidebar-link ' . ($page_active == 'perjalanan_dinas_list' ? 'sidebar-link-active' : '') . '">';
    echo getIconSVG('perjadin') . ' Perjalanan Dinas';
    echo '    </a>';
    echo '    <a href="#" class="sidebar-link ' . ($page_active == 'email_client' ? 'sidebar-link-active' : '') . '">';
    echo getIconSVG('email') . ' E-mail';
    echo '    </a>';
    echo '  </div>';
}

function renderTopManagementMenu($page_active)
{
    echo '  <div class="space-y-1 mt-2">';
    echo '    <a href="#" class="sidebar-link ' . ($page_active == 'tm_korporat' ? 'sidebar-link-active' : '') . '">';
    echo getIconSVG('building') . ' Korporat';
    echo '    </a>';

    echo '    <button type="button" class="sidebar-link w-full flex justify-between items-center" data-menu-toggle="tm_karyawan_menu">';
    echo '        <span>' . getIconSVG('users') . ' Karyawan</span>';
    echo '        <svg class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>';
    echo '    </button>';
    echo '    <div id="tm_karyawan_menu" class="hidden pl-8 space-y-1 mt-1">'; 
    echo '        <a href="#" class="sidebar-link ' . ($page_active == 'tm_daftar_karyawan' ? 'sidebar-link-active' : '') . '">Daftar Karyawan</a>'; 
    echo '        <a href="#" class="sidebar-link ' . ($page_active == 'tm_laporan_pekerjaan' ? 'sidebar-link-active' : '') . '">Laporan Pekerjaan</a>';
    echo '        <a href="' . BASE_URL . '/modules/leave/approve_cuti_massal.php" class="sidebar-link ' . ($page_active == 'approve_cuti_massal' ? 'sidebar-link-active' : '') . '">Cuti Massal</a>';
    echo '    </div>';

    echo '    <a href="#" class="sidebar-link ' . ($page_active == 'tm_dokumen' ? 'sidebar-link-active' : '') . '">';
    echo getIconSVG('folder') . ' Dokumen';
    echo '    </a>';

    echo '    <button type="button" class="sidebar-link w-full flex justify-between items-center" data-menu-toggle="tm_keuangan_menu">';
    echo '        <span>' . getIconSVG('wallet') . ' Keuangan</span>';
    echo '        <svg class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>';
    echo '    </button>';
    echo '    <div id="tm_keuangan_menu" class="hidden pl-8 space-y-1 mt-1">';
    echo '        <a href="#" class="sidebar-link ' . ($page_active == 'tm_laporan_keuangan' ? 'sidebar-link-active' : '') . '">Laporan Keuangan</a>';
    echo '    </div>';
    
    echo '    <button type="button" class="sidebar-link w-full flex justify-between items-center" data-menu-toggle="tm_legal_menu">';
    echo '        <span>' . getIconSVG('scale') . ' Legal</span>';
    echo '        <svg class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>';
    echo '    </button>';
    echo '    <div id="tm_legal_menu" class="hidden pl-8 space-y-1 mt-1">';
    echo '        <a href="#" class="sidebar-link ' . ($page_active == 'tm_perjanjian' ? 'sidebar-link-active' : '') . '">Perjanjian</a>';
    echo '        <a href="#" class="sidebar-link ' . ($page_active == 'tm_perijinan' ? 'sidebar-link-active' : '') . '">Perijinan</a>';
    echo '        <a href="#" class="sidebar-link ' . ($page_active == 'tm_aksi_korporat' ? 'sidebar-link-active' : '') . '">Aksi Korporat</a>';
    echo '        <a href="#" class="sidebar-link ' . ($page_active == 'tm_masalah_hukum' ? 'sidebar-link-active' : '') . '">Permasalahan Hukum</a>';
    echo '        <a href="#" class="sidebar-link ' . ($page_active == 'tm_haki' ? 'sidebar-link-active' : '') . '">HAKI</a>';
    echo '    </div>';

    echo '  </div>';
}

function renderHRMenu($page_active)
{
    // $link_disabled_class sudah tidak diperlukan lagi
    
    echo '  <div class="space-y-1 mt-2">';
    
    echo '    <a href="' . BASE_URL . '/modules/hr/hr_daftar_karyawan.php" class="sidebar-link ' . ($page_active == 'hr_daftar_karyawan' ? 'sidebar-link-active' : '') . '">';
    echo getIconSVG('users') . ' Daftar Karyawan';
    echo '    </a>';
    
    echo '    <a href="' . BASE_URL . '/modules/presensi/presensi_karyawan.php" class="sidebar-link ' . ($page_active == 'presensi_report' ? 'sidebar-link-active' : '') . '">';
    echo getIconSVG('laporan') . ' Laporan Presensi';
    echo '    </a>';

    echo '    <button type="button" class="sidebar-link w-full flex justify-between items-center" data-menu-toggle="hr_cuti_menu">';
    echo '        <span>' . getIconSVG('cuti') . ' Cuti Karyawan</span>';
    echo '        <svg class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>';
    echo '    </button>';
    echo '    <div id="hr_cuti_menu" class="hidden pl-8 space-y-1 mt-1">'; 
    echo '        <a href="' . BASE_URL . '/modules/hr/hr_daftar_cuti.php" class="sidebar-link ' . ($page_active == 'hr_daftar_cuti' ? 'sidebar-link-active' : '') . '">Daftar Cuti</a>'; 
    echo '        <a href="' . BASE_URL . '/modules/hr/hr_cuti_massal.php" class="sidebar-link ' . ($page_active == 'hr_cuti_massal' ? 'sidebar-link-active' : '') . '">Cuti Massal</a>';
    echo '    </div>';

    echo '    <button type="button" class="sidebar-link w-full flex justify-between items-center" data-menu-toggle="hr_payroll_menu">';
    echo '        <span>' . getIconSVG('wallet') . ' Payroll</span>';
    echo '        <svg class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>';
    echo '    </button>';
    
    // === [PERUBAHAN DI SINI] ===
    // Mengaktifkan link "Pelaporan Pajak"
    echo '    <div id="hr_payroll_menu" class="hidden pl-8 space-y-1 mt-1">'; 
    echo '        <a href="' . BASE_URL . '/modules/hr/payroll_struktur_upah.php" class="sidebar-link ' . ($page_active == 'hr_struktur_upah' ? 'sidebar-link-active' : '') . '">Struktur & Skala Upah</a>'; 
    echo '        <a href="' . BASE_URL . '/modules/hr/payroll_master_gaji.php" class="sidebar-link ' . ($page_active == 'hr_master_gaji' ? 'sidebar-link-active' : '') . '">Master Data Gaji</a>';
    echo '        <a href="' . BASE_URL . '/modules/hr/hr_payroll_settings.php" class="sidebar-link ' . ($page_active == 'hr_payroll_settings' ? 'sidebar-link-active' : '') . '">Setting BPJS & PPh</a>'; 
    echo '        <a href="' . BASE_URL . '/modules/hr/hr_hitung_gaji.php" class="sidebar-link ' . ($page_active == 'hr_hitung_gaji' ? 'sidebar-link-active' : '') . '">Penghitungan Gaji</a>';
    
    echo '        <a href="' . BASE_URL . '/modules/hr/hr_hitung_thr.php" class="sidebar-link ' . ($page_active == 'hr_hitung_thr' ? 'sidebar-link-active' : '') . '">';
    echo '            Hitung THR';
    echo '        </a>';
    
    echo '        <a href="' . BASE_URL . '/modules/hr/hr_bukti_potong.php" class="sidebar-link ' . ($page_active == 'hr_bukti_potong' ? 'sidebar-link-active' : '') . '">';
    echo '            Bukti Potong'; 
    echo '        </a>';
    
    // Link diaktifkan
    echo '        <a href="' . BASE_URL . '/modules/hr/hr_laporan_pajak.php" class="sidebar-link ' . ($page_active == 'hr_laporan_pajak' ? 'sidebar-link-active' : '') . '">';
    echo '            Pelaporan Pajak'; // <-- LINK DIAKTIFKAN
    echo '        </a>';
    
    echo '        <a href="' . BASE_URL . '/modules/hr/hr_slip_gaji.php" class="sidebar-link ' . ($page_active == 'hr_slip_gaji' ? 'sidebar-link-active' : '') . '">Slip Gaji</a>';
    echo '    </div>';
    // === [AKHIR PERUBAHAN] ===
    
    echo '    <a href="#" class="sidebar-link ' . ($page_active == 'hr_assessment' ? 'sidebar-link-active' : '') . '">';
    echo getIconSVG('assessment') . ' Assessment';
    echo '    </a>';
    echo '    <a href="#" class="sidebar-link ' . ($page_active == 'hr_pelatihan' ? 'sidebar-link-active' : '') . '">';
    echo getIconSVG('pelatihan') . ' Pelatihan';
    echo '    </a>';

    echo '    <button type="button" class="sidebar-link w-full flex justify-between items-center" data-menu-toggle="hr_hi_menu">';
    echo '        <span>' . getIconSVG('briefcase') . ' Hubungan Industrial</span>';
    echo '        <svg class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>';
    echo '    </button>';
    echo '    <div id="hr_hi_menu" class="hidden pl-8 space-y-1 mt-1">'; 
    echo '        <a href="#" class="sidebar-link ' . ($page_active == 'hr_pk' ? 'sidebar-link-active' : '') . '">Perjanjian Kerja</a>'; 
    echo '        <a href="#" class="sidebar-link ' . ($page_active == 'hr_mutasi' ? 'sidebar-link-active' : '') . '">Mutasi Karyawan</a>';
    echo '        <a href="#" class="sidebar-link ' . ($page_active == 'hr_sp' ? 'sidebar-link-active' : '') . '">Surat Peringatan</a>';
    echo '        <a href="#" class="sidebar-link ' . ($page_active == 'hr_pp' ? 'sidebar-link-active' : '') . '">Peraturan Perusahaan</a>';
    echo '        <a href="#" class="sidebar-link ' . ($page_active == 'hr_skk' ? 'sidebar-link-active' : '') . '">Surat Keterangan Kerja</a>';
    echo '    </div>';

    echo '    <a href="#" class="sidebar-link ' . ($page_active == 'hr_perjalanan_dinas' ? 'sidebar-link-active' : '') . '">';
    echo getIconSVG('perjadin') . ' Perjalanan Dinas';
    echo '    </a>';
    
    echo '  </div>';
}

function renderLegalMenu($page_active)
{
    echo '  <div class="space-y-1 mt-2">';
    echo '    <a href="' . BASE_URL . '/modules/companies/manage_companies.php" class="sidebar-link ' . ($page_active == 'manage_companies' ? 'sidebar-link-active' : '') . '">';
    echo getIconSVG('building') . ' Korporat';
    echo '    </a>';
    echo '    <a href="#" class="sidebar-link ' . ($page_active == 'legal_perjanjian' ? 'sidebar-link-active' : '') . '">';
    echo getIconSVG('file-text') . ' Perjanjian';
    echo '    </a>';
    echo '    <a href="#" class="sidebar-link ' . ($page_active == 'legal_perijinan' ? 'sidebar-link-active' : '') . '">';
    echo getIconSVG('award') . ' Perijinan';
    echo '    </a>';
    echo '    <a href="#" class="sidebar-link ' . ($page_active == 'legal_aksi_korporat' ? 'sidebar-link-active' : '') . '">';
    echo getIconSVG('cog') . ' Aksi Korporat';
    echo '    </a>';
    echo '    <a href="#" class="sidebar-link ' . ($page_active == 'legal_masalah_hukum' ? 'sidebar-link-active' : '') . '">';
    echo getIconSVG('scale') . ' Permasalahan Hukum';
    echo '    </a>';
    echo '    <a href="#" class="sidebar-link ' . ($page_active == 'legal_haki' ? 'sidebar-link-active' : '') . '">';
    echo getIconSVG('at') . ' HAKI';
    echo '    </a>';
    echo '    <a href="#" class="sidebar-link ' . ($page_active == 'legal_asset' ? 'sidebar-link-active' : '') . '">';
    echo getIconSVG('map-pin') . ' Asset';
    echo '    </a>';
    echo '  </div>';
}

function renderFinanceMenu($page_active)
{
    echo '  <div class="space-y-1 mt-2">';
    echo '    <a href="#" class="sidebar-link ' . ($page_active == 'fin_laporan_keuangan' ? 'sidebar-link-active' : '') . '">';
    echo getIconSVG('chart-pie') . ' Laporan Keuangan';
    echo '    </a>';
    echo '    <a href="#" class="sidebar-link ' . ($page_active == 'fin_perpajakan' ? 'sidebar-link-active' : '') . '">';
    echo getIconSVG('file-text') . ' Perpajakan';
    echo '    </a>';
    echo '    <a href="#" class="sidebar-link ' . ($page_active == 'fin_payroll' ? 'sidebar-link-active' : '') . '">';
    echo getIconSVG('wallet') . ' Payroll';
    echo '    </a>';
    echo '    <a href="#" class="sidebar-link ' . ($page_active == 'fin_pembayaran_vendor' ? 'sidebar-link-active' : '') . '">';
    echo getIconSVG('receipt') . ' Pembayaran Vendor';
    echo '    </a>';
    echo '    <a href="#" class="sidebar-link ' . ($page_active == 'fin_bon_sementara' ? 'sidebar-link-active' : '') . '">';
    echo getIconSVG('note') . ' Bon Sementara';
    echo '    </a>';
    echo '    <a href="#" class="sidebar-link ' . ($page_active == 'fin_hutang_piutang' ? 'sidebar-link-active' : '') . '">';
    echo getIconSVG('exchange') . ' Hutang/Piutang';
    echo '    </a>';
    echo '  </div>';
}

function renderMasterDataMenu($page_active)
{
    echo '  <div class="space-y-1 mt-2">';
    
    echo '    <a href="' . BASE_URL . '/modules/users/manage_users.php" class="sidebar-link ' . ($page_active == 'manage_users' ? 'sidebar-link-active' : '') . '">';
    echo '      <svg class="inline-block w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg> Kelola Pengguna';
    echo '    </a>';
    
    echo '    <a href="' . BASE_URL . '/modules/companies/manage_companies.php" class="sidebar-link ' . ($page_active == 'manage_companies' ? 'sidebar-link-active' : '') . '">';
    echo '      <svg class="inline-block w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg> Kelola Perusahaan';
    echo '    </a>';

    echo '    <a href="' . BASE_URL . '/modules/registry/document_codes.php" class="sidebar-link ' . ($page_active == 'document_codes' ? 'sidebar-link-active' : '') . '">';
    echo '      <svg class="inline-block w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg> Kelola Kode Surat';
    echo '    </a>';
    
    echo '    <a href="' . BASE_URL . '/modules/users/manage_struktur.php" class="sidebar-link ' . ($page_active == 'manage_struktur' ? 'sidebar-link-active' : '') . '">';
    echo '      <svg class="inline-block w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> Kelola Struktur';
    echo '    </a>';

     echo '   <a href="' . BASE_URL . '/modules/presensi/presensi_karyawan.php" class="sidebar-link ' . ($page_active == 'presensi_report' ? 'sidebar-link-active' : '') . '">';
    echo '      <svg class="inline-block w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg> Laporan Presensi';
    echo '    </a>';
    echo '  </div>';
}
?>
<aside id="sidebar" class="w-64 bg-white shadow-lg flex flex-col fixed inset-y-0 left-0 z-30 transform -translate-x-full md:relative md:flex md:translate-x-0 transition-transform duration-300 ease-in-out">
    <div class="p-5 h-16 flex items-center bg-blue-600 text-white text-lg font-bold">
        <img src="<?php echo BASE_URL; ?>/logo.png" alt="Logo" class="w-7 h-7 mr-2"> PRANATA SUPER APPS
    </div>

    <nav class="flex-1 px-4 py-4 space-y-2 overflow-y-auto">

        <a href="<?php echo BASE_URL; ?>/dashboard.php" class="sidebar-link <?php if ($page_active == 'dashboard') echo 'sidebar-link-active'; ?>">
            <svg class="inline-block w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1V10M9 9h6"></path></svg>
            Dasbor
        </a>

        <div class="pt-4 space-y-2">
            <?php
            // Menu Karyawan (selalu tampil, tapi accordion)
            // Tentukan $is_karyawan_menu_open berdasarkan $page_active
            renderAccordionHeader('menu_karyawan', 'Menu Karyawan', $is_karyawan_menu_open);
            echo '<div id="menu_karyawan" class="' . ($is_karyawan_menu_open ? '' : 'hidden') . ' pl-4">';
            renderKaryawanMenu($page_active, $is_atasan);
            echo '</div>';
            
            // Menu lainnya (berdasarkan hak akses)
            if ($is_admin) {
                renderAccordionHeader('menu_admin', 'Master Data (Admin)', $is_admin_menu_open);
                echo '<div id="menu_admin" class="' . ($is_admin_menu_open ? '' : 'hidden') . ' pl-4">';
                renderMasterDataMenu($page_active);
                echo '</div>';
                
                renderAccordionHeader('menu_tm', 'Top Management', $is_tm_menu_open);
                echo '<div id="menu_tm" class="' . ($is_tm_menu_open ? '' : 'hidden') . ' pl-4">';
                renderTopManagementMenu($page_active);
                echo '</div>';
                
                renderAccordionHeader('menu_hr', 'HR', $is_hr_menu_open);
                echo '<div id="menu_hr" class="' . ($is_hr_menu_open ? '' : 'hidden') . ' pl-4">';
                renderHRMenu($page_active);
                echo '</div>';
                
                renderAccordionHeader('menu_legal', 'Legal', $is_legal_menu_open);
                echo '<div id="menu_legal" class="' . ($is_legal_menu_open ? '' : 'hidden') . ' pl-4">';
                renderLegalMenu($page_active);
                echo '</div>';
                
                renderAccordionHeader('menu_finance', 'Finance', $is_finance_menu_open);
                echo '<div id="menu_finance" class="' . ($is_finance_menu_open ? '' : 'hidden') . ' pl-4">';
                renderFinanceMenu($page_active);
                echo '</div>';
            
            } else if ($is_top_management) {
                renderAccordionHeader('menu_tm', 'Top Management', $is_tm_menu_open);
                echo '<div id="menu_tm" class="' . ($is_tm_menu_open ? '' : 'hidden') . ' pl-4">';
                renderTopManagementMenu($page_active);
                echo '</div>';
            
            } else if ($is_hr) {
                renderAccordionHeader('menu_hr', 'HR', $is_hr_menu_open);
                echo '<div id="menu_hr" class="' . ($is_hr_menu_open ? '' : 'hidden') . ' pl-4">';
                renderHRMenu($page_active);
                echo '</div>';
            
            } else if ($is_legal) {
                renderAccordionHeader('menu_legal', 'Legal', $is_legal_menu_open);
                echo '<div id="menu_legal" class="' . ($is_legal_menu_open ? '' : 'hidden') . ' pl-4">';
                renderLegalMenu($page_active);
                echo '</div>';
            
            } else if ($is_finance) {
                renderAccordionHeader('menu_finance', 'Finance', $is_finance_menu_open);
                echo '<div id="menu_finance" class="' . ($is_finance_menu_open ? '' : 'hidden') . ' pl-4">';
                renderFinanceMenu($page_active);
                echo '</div>';
            }
            ?>
        </div>

    </nav>
</aside>

<div id="sidebarBackdrop" class="fixed inset-0 bg-black bg-opacity-50 z-20 hidden md:hidden"></div>

<div class="flex-1 flex flex-col overflow-hidden">

    <header class="bg-white shadow-sm z-10 sticky top-0">
        <div class="flex justify-between md:justify-end items-center p-4 h-16">
            
            <button id="sidebarToggle" class="text-gray-600 hover:text-gray-800 focus:outline-none md:hidden">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
            </button>

            <div class="flex items-center">
                <a href="<?php echo BASE_URL; ?>/modules/profile/view_profile.php" 
                   title="Lihat Profile"
                   class="relative w-10 h-10 flex items-center justify-center text-gray-600 hover:text-blue-600 rounded-full hover:bg-gray-100 transition duration-200 mr-3
                          <?php if ($page_active == 'profile') echo 'text-blue-600 bg-blue-100'; // Highlight jika aktif ?>" >
                   <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                   
                </a>
                <button
                   type="button"
                   onclick="openLogoutModal()"
                   class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-semibold transition duration-200 cursor-pointer">
                   Keluar
                </button>

            </div>
        </div>
    </header>