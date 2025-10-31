<?php
// includes/header.php

// Mulai session jika belum ada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// === [PATH DIPERBARUI] ===
// db.php ada di folder yang sama (includes/), jadi 'db.php' sudah benar.
require 'db.php';
// === [AKHIR PERUBAHAN] ===

// Inisialisasi variabel koneksi (dari db.php)
global $servername, $username, $password, $dbname;

// Cek status login
if (!isset($_SESSION['user_id'])) {
    // Jika tidak ada session user_id (belum login)
    
    // === [PERBAIKAN PATH: MENGGUNAKAN BASE_URL] ===
    // Redirect ke index.php di folder root (pranata/index.php)
    // Ini sekarang akan selalu benar, dari folder mana pun file ini di-include.
    header("Location: " . BASE_URL . "/index.php");
    // === [AKHIR PERUBAHAN] ===
    
    exit;
}

// Ambil data user dari session untuk ditampilkan
$user_id = $_SESSION['user_id'];
$nama_lengkap = $_SESSION['nama_lengkap'];
$tier = $_SESSION['tier'];

// Set judul halaman default jika tidak di-set oleh file pemanggil
if (!isset($page_title)) {
    $page_title = "PRANATA SUPER APPS";
}
// Set menu aktif default jika tidak di-set
if (!isset($page_active)) {
    $page_active = "";
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - PNU</title>
    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo BASE_URL; ?>/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo BASE_URL; ?>/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo BASE_URL; ?>/favicon-16x16.png">
    <link rel="manifest" href="<?php echo BASE_URL; ?>/site.webmanifest">
    <link rel="shortcut icon" href="<?php echo BASE_URL; ?>/favicon.ico">
    <style>
        /* CSS Global untuk Aplikasi */
        .sidebar-link {
            display: flex; align-items: center; padding: 0.75rem 1rem;
            color: #374151; /* gray-700 */
            font-weight: 500; border-radius: 0.375rem; /* rounded-md */
            transition: all 0.2s ease-in-out;
        }
        .sidebar-link:hover {
            background-color: #f3f4f6; /* gray-100 */
            color: #1f2937; /* gray-800 */
        }
        .sidebar-link-active {
            background-color: #3b82f6; /* blue-600 */
            color: white;
        }
        .sidebar-link-active:hover {
            background-color: #2563eb; /* blue-700 */
            color: white;
        }
        /* Style untuk form-input */
        .form-input {
             display: block; width: 100%;
             padding: 0.5rem 0.75rem;
             border: 1px solid #d1d5db; /* gray-300 */
             border-radius: 0.375rem; /* rounded-md */
             box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); /* shadow-sm */
             transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .form-input:focus {
            outline: none;
            border-color: #3b82f6; /* blue-500 */
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25); /* ring-blue-500 */
        }
        .form-input.bg-gray-100 { background-color: #f3f4f6; }
        .form-label {
            display: block; margin-bottom: 0.25rem;
            font-size: 0.875rem; /* text-sm */
            font-weight: 500; /* font-medium */
            color: #374151; /* gray-700 */
        }
        /* Style untuk card */
        .card {
            background-color: white;
            border-radius: 0.5rem; /* rounded-lg */
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); /* shadow-md */
            overflow: hidden; /* Menjaga agar konten tidak tumpah */
        }
        .card-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid #e5e7eb; /* gray-200 */ }
        .card-content { padding: 1.5rem; }
        .card-list { max-height: 300px; overflow-y: auto; }
        
        /* Tombol */
        .btn-primary-sm {
            padding: 0.25rem 0.75rem; /* px-3 py-1 */
            font-size: 0.875rem; /* text-sm */
            font-weight: 500; /* font-medium */
            color: white;
            background-color: #3b82f6; /* blue-600 */
            border-radius: 0.375rem; /* rounded-md */
            transition: background-color 0.2s;
            text-decoration: none;
        }
        .btn-primary-sm:hover { background-color: #2563eb; /* blue-700 */ }
        .btn-secondary { background-color: #e5e7eb; color: #374151; }
        .btn-secondary:hover { background-color: #d1d5db; }
        .btn-danger { background-color: #ef4444; color: white; }
        .btn-danger:hover { background-color: #dc2626; }
        .no-underline { text-decoration: none; }

        /* Modal */
        .modal-overlay {
            position: fixed; inset: 0;
            background-color: rgba(0, 0, 0, 0.5); /* Latar belakang gelap */
            display: flex; align-items: center; justify-content: center;
            z-index: 50;
            padding: 1rem;
        }
        .modal-overlay.hidden { display: none; }
        
        /* Input Uppercase */
        .uppercase-input { text-transform: uppercase; }

    </style>
</head>
<body class="bg-gray-100">

<div class="flex h-screen">
    }