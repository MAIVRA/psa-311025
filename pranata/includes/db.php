<?php
// Memulai session di awal, karena file ini akan di-include di setiap halaman
if (!session_id()) {
    session_start();
}

// === [PERBAIKAN: Cek jika konstanta sudah ada] ===
// Ini mencegah error "Constant already defined" jika file di-include berkali-kali

// Definisikan BASE_URL jika belum ada.
if (!defined('BASE_URL')) {
    // Ini adalah path ke folder root aplikasi Anda di localhost.
    define('BASE_URL', '/pranata'); 
}

// Definisikan BASE_PATH jika belum ada.
if (!defined('BASE_PATH')) {
    // Ini adalah path fisik absolut ke folder root aplikasi di server.
    // dirname(__DIR__) akan menunjuk ke folder 'pranata/' karena file ini ada di 'pranata/includes/'
    define('BASE_PATH', dirname(__DIR__)); 
}
// === [AKHIR PERBAIKAN] ===


// Konfigurasi koneksi database (sesuai XAMPP default)
$servername = "localhost";
$username = "root";
$password = ""; // Password default XAMPP biasanya kosong
$dbname = "pranatasuperapps"; // Nama database yang kita buat

// Buat koneksi
$conn = new mysqli($servername, $username, $password, $dbname);

// Cek koneksi
if ($conn->connect_error) {
    // Kita tidak bisa menggunakan BASE_URL di sini jika koneksi gagal sebelum konstanta didefinisikan
    // Tampilkan pesan error sederhana saja
    die("Koneksi Database Gagal: " . $conn->connect_error); 
}

// Set karakter set agar sesuai dengan database
$conn->set_charset("utf8mb4");

// Fungsi helper untuk konversi angka romawi
if (!function_exists('toRoman')) {
    function toRoman($number) {
        $map = array('M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1);
        $returnValue = '';
        while ($number > 0) {
            foreach ($map as $roman => $int) {
                if($number >= $int) {
                    $number -= $int;
                    $returnValue .= $roman;
                    break;
                }
            }
        }
        return $returnValue;
    }
}

?>