<?php
// File: api/config/database.php
declare(strict_types=1);

/**
 * Konfigurasi & bootstrap minimal untuk API (mysqli + CORS + JWT const).
 * Catatan:
 * - Untuk produksi, matikan display_errors (lihat bagian DEBUG).
 * - File ini di-include oleh setiap endpoint; usahakan idempotent.
 */

/* -------------------------------------------------
|  DEBUG (nyalakan hanya di development)
|--------------------------------------------------*/
$DEBUG = true; // set ke false di produksi
error_reporting($DEBUG ? E_ALL : 0);
ini_set('display_errors', $DEBUG ? '1' : '0');

/* -------------------------------------------------
|  Helper JSON response (didefinisikan sebelum dipakai)
|--------------------------------------------------*/
if (!function_exists('json_response')) {
  function json_response(int $code, array $data): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }
}

/* -------------------------------------------------
|  Autoload (composer) — opsional kalau pakai JWT lib
|--------------------------------------------------*/
$vendorAutoload = __DIR__ . '/../../vendor/autoload.php';
if (is_file($vendorAutoload)) {
  require_once $vendorAutoload;
}

/* -------------------------------------------------
|  CORS & preflight (idempotent untuk tiap request)
|--------------------------------------------------*/
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*'; // PRODUKSI: whitelist domain front-end
header('Access-Control-Allow-Origin: ' . $origin);
header('Vary: Origin'); // agar cache aware terhadap Origin
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400'); // cache preflight 24 jam

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

/* -------------------------------------------------
|  DB Credentials (GANTI SESUAI SERVER ANDA)
|--------------------------------------------------*/
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'pgritawa_nova');
if (!defined('DB_PASS')) define('DB_PASS', 'ZLeA)lz%3Z]a'); // GANTI di produksi
if (!defined('DB_NAME')) define('DB_NAME', 'pgritawa_nova');

/* -------------------------------------------------
|  JWT Settings (GANTI di produksi)
|--------------------------------------------------*/
if (!defined('JWT_SECRET'))   define('JWT_SECRET', 'F!$Z&5p@#nL8^yB*sV@c7g$R@hN#wX&eJ*qM9t!K');
if (!defined('JWT_ISSUER'))   define('JWT_ISSUER', $_SERVER['HTTP_HOST'] ?? 'nova-api');
if (!defined('JWT_AUDIENCE')) define('JWT_AUDIENCE', 'nova-web');
if (!defined('JWT_TTL'))      define('JWT_TTL', 86400); // 1 hari (detik)

/* -------------------------------------------------
|  Koneksi mysqli (shared $conn)
|--------------------------------------------------*/
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
  $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  // Charset & SQL mode aman
  $conn->set_charset('utf8mb4');
  $conn->query("SET sql_mode = 'STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
} catch (Throwable $e) {
  // Hindari bocor kredensial di produksi
  $msg = $DEBUG ? ('Koneksi database gagal: ' . $e->getMessage()) : 'Koneksi database gagal';
  json_response(500, ['message' => $msg]);
}

/* -------------------------------------------------
|  Utilitas kecil (opsional)
|--------------------------------------------------*/
if (!function_exists('db_now')) {
  function db_now(): string { return date('Y-m-d H:i:s'); }
}
