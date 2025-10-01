<?php
// File: api/index.php
// Router utama untuk semua endpoint API.
// Prasyarat: config.php meng-include config/database.php (mysqli $conn), konstanta JWT_*, dan helper json_response().

declare(strict_types=1);

// --------------------------------------------------
// Bootstrap (autoload vendor, DB + CORS, helpers, konstanta)
// --------------------------------------------------
require_once __DIR__ . '/config.php';

// --------------------------------------------------
// Preflight OPTIONS ditangani di database.php, tapi amankan lagi
// --------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
  http_response_code(204);
  exit;
}

// --------------------------------------------------
// Util: parsing path menjadi segmen setelah "/api"
// --------------------------------------------------
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/api/index.php')), '/');
/**
 * Contoh:
 *  - SCRIPT_NAME: /api/index.php  -> $base = /api
 *  - REQUEST_URI: /api/projects/123/scurve -> setelah strip '/api' -> 'projects/123/scurve'
 */
$base = $scriptDir === '/' ? '' : $scriptDir;
$path = preg_replace('#^' . preg_quote($base, '#') . '/?#', '', $reqPath);
$path = ltrim($path, '/');

// Jika akses langsung ke /api atau /api/, path bisa kosong -> set ke ''
if ($path === 'index.php') $path = '';
$segments = $path === '' ? [] : explode('/', $path);

// Ekspor ke file handler yang di-include
$path_parts = $segments;

// --------------------------------------------------
// Root & ping (opsional untuk health check)
// --------------------------------------------------
if (count($segments) === 0) {
  json_response(200, [
    'app'    => APP_NAME,
    'status' => 'ok',
    'time'   => date('c'),
  ]);
}

$resource = strtolower($segments[0] ?? '');

// --------------------------------------------------
// Dispatch ke modul sesuai resource
// Struktur folder handler:
// - auth/        -> auth/login.php, (bisa ditambah refresh/logout nanti)
// - users/       -> index.php (CRUD + /me)
// - projects/    -> index.php (list/detail/tabs)
// --------------------------------------------------
switch ($resource) {
  case 'ping':
    json_response(200, ['status' => 'ok', 'time' => date('c')]);

  case 'auth':
    // /api/auth/login
    $action = strtolower($segments[1] ?? '');
    if ($action === 'login') {
      // Handler login memakai $conn, json_response(), JWT_*
      require __DIR__ . '/auth/login.php';
      exit; // login.php sudah exit via json_response
    }
    json_response(404, ['message' => 'Endpoint auth tidak ditemukan']);

  case 'users':
    // /api/users, /api/users/:id, /api/users/me
    require __DIR__ . '/projects/auth_middleware.php'; // untuk require_auth()
    require __DIR__ . '/users/index.php';
    exit;

  case 'projects':
    // /api/projects, /api/projects/:id, /api/projects/:id/:tab
    require __DIR__ . '/projects/auth_middleware.php'; // aktifkan jika ingin proteksi semua endpoint
    require __DIR__ . '/projects/index.php';
    exit;

  default:
    json_response(404, ['message' => 'Endpoint tidak ditemukan']);
}
