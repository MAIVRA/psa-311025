<?php
// index.php (Login Page)
// File ini ada di root folder 'pranata/'

// Mulai session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Jika sudah login, tendang ke dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

// === [PATH DIPERBARUI] ===
// Path ke db.php sekarang ada di dalam folder 'includes/'
require 'includes/db.php';
// === [AKHIR PERUBAHAN] ===

$error_message = '';

// Logika Login
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password_input = $_POST['password'];

    if (empty($email) || empty($password_input)) {
        $error_message = "Email dan Password wajib diisi.";
    } else {
        // Buka koneksi baru
        $conn_login = new mysqli($servername, $username, $password, $dbname);
        if ($conn_login->connect_error) {
            $error_message = "Koneksi database gagal.";
        } else {
            $conn_login->set_charset("utf8mb4");
            
            // === [MODIFIKASI: Ambil app_akses dan izinkan BOD/BOC login] ===
            $sql = "SELECT id, nama_lengkap, password, tier, app_akses, status_karyawan 
                    FROM users 
                    WHERE email = ? 
                    AND (status_karyawan = 'PKWT' OR status_karyawan = 'PKWTT' OR status_karyawan = 'BOD' OR status_karyawan = 'BOC')";
            
            $stmt = $conn_login->prepare($sql);
            if ($stmt === false) {
                 $error_message = "Gagal mempersiapkan statement: " . $conn_login->error;
            } else {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();

                    // Verifikasi password
                    if (password_verify($password_input, $user['password'])) {
                        // Password benar! Simpan data ke session
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                        $_SESSION['tier'] = $user['tier'];
                        $_SESSION['app_akses'] = $user['app_akses']; // <-- [PENAMBAHAN BARU]
                        
                        $stmt->close();
                        $conn_login->close();
                        
                        // Redirect ke dashboard
                        header("Location: dashboard.php"); // Path ini sudah benar (root)
                        exit;
                    } else {
                        // === [PERBAIKAN KEAMANAN] ===
                        // Password salah
                         $error_message = "Email atau Password salah, atau akun Anda tidak aktif.";
                        // === [AKHIR PERUBAHAN] ===
                    }
                } else {
                    // === [PERBAIKAN KEAMANAN] ===
                    // User tidak ditemukan atau status tidak aktif
                    $error_message = "Email atau Password salah, atau akun Anda tidak aktif.";
                    // === [AKHIR PERBAHAN] ===
                }
                
                $stmt->close();
            }
            $conn_login->close();
        }
    }
    
    // === [PERBAIKAN KEAMANAN] ===
    // Baris di bawah ini tidak lagi diperlukan karena pesan 'DEBUG:' sudah dihapus.
    // === [AKHIR PERUBAHAN] ===
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PRANATA SUPER APPS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png">
    <link rel="manifest" href="site.webmanifest">
    <link rel="shortcut icon" href="favicon.ico">
    <style>
        /* Opsi: Tambahkan font kustom jika diinginkan */
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 md:p-10 rounded-lg shadow-lg w-full max-w-md">
        <div class="flex justify-center mb-6">
            <img src="logo.png" alt="Logo PT Putra Natur Utama" class="w-32 h-auto">
            </div>
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-2">PRANATA SUPER APPS</h2>
        <p class="text-center text-gray-500 mb-6">PT PUTRA NATUR UTAMA</p>

        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <form action="index.php" method="POST" class="space-y-5">
            <div>
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email"
                       class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                       required
                       value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
            </div>
            <div>
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password"
                       class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                       required>
            </div>
            <div>
                <button type="submit"
                        class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out">
                    Masuk
                </button>
            </div>
        </form>
    </div>
</body>
</html>