<?php
// Mengaktifkan error reporting untuk development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Memuat autoloader dari Composer (ini akan memuat library JWT)
require_once __DIR__ . '/../vendor/autoload.php';

// Mengatur header CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// --- GANTI DENGAN KREDENSIAL DATABASE ANDA ---
define('DB_HOST', 'localhost');
define('DB_USER', 'pgritawa_nova'); 
define('DB_PASS', 'ZLeA)lz%3Z]a');    // GANTI dengan password DB yang baru & aman
define('DB_NAME', 'pgritawa_nova'); 

// --- KUNCI RAHASIA UNTUK JWT ---
// Ganti ini dengan string acak yang sangat panjang dan aman
// [FIXED] Fungsi define() memerlukan dua argumen: nama konstanta dan nilainya.
define('JWT_SECRET', 'F!$Z&5p@#nL8^yB*sV@c7g$R@hN#wX&eJ*qM9t!K');

// Membuat koneksi database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    http_response_code(500);
    // [IMPROVEMENT] Menggunakan json_response helper untuk konsistensi
    json_response(500, ["message" => "Koneksi database gagal: " . $conn->connect_error]);
    die(); // Menghentikan eksekusi setelah response
}

// Fungsi helper untuk respons JSON yang sudah ada di file helpers.php,
// namun untuk standalone, bisa diletakkan di sini juga.
// Pastikan fungsi ini hanya didefinisikan sekali.
if (!function_exists('json_response')) {
    function json_response($code, $data) {
        http_response_code($code);
        echo json_encode($data);
        exit;
    }
}
?>