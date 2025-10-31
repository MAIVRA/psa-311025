<?php
// logout.php
// File ini ada di root folder 'pranata/'

// === [PATH DIPERBARUI] ===
// Panggil db.php untuk memastikan session_start() dijalankan
require 'includes/db.php';
// === [AKHIR PERUBAHAN] ===


// Hancurkan semua data session
$_SESSION = array(); // Kosongkan array session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy(); // Hancurkan session

// === [PERBAIKAN PATH: MENGGUNAKAN BASE_URL] ===
// Redirect ke halaman login (index.php di root)
// Menggunakan BASE_URL agar konsisten dan portabel
header("Location: " . BASE_URL . "/index.php");
// === [AKHIR PERBAIKAN] ===
exit;
?>