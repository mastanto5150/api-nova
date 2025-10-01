<?php
// =================================================================
// FILE KONFIGURASI UTAMA APLIKASI
// =================================================================

// 1. PENGATURAN DASAR & ERROR REPORTING
// -----------------------------------------------------------------
// Mengaktifkan error reporting untuk development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Memuat autoloader dari Composer untuk semua library (seperti JWT)
require_once __DIR__ . '/vendor/autoload.php';

// 2. PENGATURAN CORS (Cross-Origin Resource Sharing)
// -----------------------------------------------------------------
// Header ini mengizinkan aplikasi frontend Anda untuk berkomunikasi dengan API ini
header("Access-Control-Allow-Origin: *"); // Untuk production, ganti "*" dengan domain frontend Anda
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Menangani preflight request dari browser
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// 3. KREDENSIAL DAN KUNCI RAHASIA
// -----------------------------------------------------------------
// --- GANTI DENGAN KREDENSIAL DATABASE ANDA ---
define('DB_HOST', 'localhost');
define('DB_USER', 'pgritawa_nova');
define('DB_PASS', 'ZLeA)lz%3Z]a');
define('DB_NAME', 'pgritawa_nova');

// --- KUNCI RAHASIA UNTUK ENKRIPSI JWT ---
// Ganti ini dengan string acak yang sangat panjang dan aman
define('JWT_SECRET', 'F!$Z&5p@#nL8^yB*sV@c7g$R@hN#wX&eJ*qM9t!K');

// 4. KONEKSI DATABASE
// -----------------------------------------------------------------
// Membuat koneksi database menggunakan mysqli
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Memeriksa jika koneksi gagal
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["message" => "Koneksi database gagal: " . $conn->connect_error]);
    exit();
}

// 5. FUNGSI HELPER GLOBAL
// -----------------------------------------------------------------
// Fungsi ini akan tersedia di semua file yang memanggil config.php
if (!function_exists('json_response')) {
    /**
     * Mengirim respons dalam format JSON dan menghentikan eksekusi.
     * @param int $code Kode status HTTP (e.g., 200, 404, 500)
     * @param array $data Data yang akan di-encode ke JSON
     */
    function json_response($code, $data) {
        http_response_code($code);
        echo json_encode($data);
        exit;
    }
}
?>