<?php
// File: api/config.php
// Tujuan: Satu tempat untuk bootstrap API (autoload, DB, konstanta, helper umum).
// Catatan: File ini aman di-include berkali-kali (idempotent).

declare(strict_types=1);

/* -------------------------------------------------
|  Composer Autoload (library pihak ketiga: JWT, dsb.)
|--------------------------------------------------*/
$__vendor = __DIR__ . '/vendor/autoload.php';
if (is_file($__vendor)) {
  require_once $__vendor;
}

/* -------------------------------------------------
|  Include koneksi DB + CORS + konstanta JWT
|  (menyediakan: $conn (mysqli), json_response(), JWT_* constants)
|--------------------------------------------------*/
require_once __DIR__ . '/config/database.php';

/* -------------------------------------------------
|  Konstanta Aplikasi (boleh override via ENV)
|--------------------------------------------------*/
if (!defined('APP_NAME')) define('APP_NAME', getenv('APP_NAME') ?: 'Nova API');
if (!defined('APP_ENV'))  define('APP_ENV', getenv('APP_ENV') ?: 'production');
if (!defined('DEBUG'))    define('DEBUG', APP_ENV !== 'production');

if (!defined('MAX_PAGE_SIZE')) define('MAX_PAGE_SIZE', (int)(getenv('MAX_PAGE_SIZE') ?: 100));
if (!defined('DEF_PAGE_SIZE')) define('DEF_PAGE_SIZE', (int)(getenv('DEF_PAGE_SIZE') ?: 20));

/* -------------------------------------------------
|  Helper: membaca body JSON
|--------------------------------------------------*/
if (!function_exists('json_body')) {
  /**
   * Kembalikan array dari body JSON; jika invalid → 400.
   * @return array<string,mixed>
   */
  function json_body(): array {
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    if (!is_array($data)) {
      json_response(400, ['message' => 'Body harus JSON']);
    }
    return $data;
  }
}

/* -------------------------------------------------
|  Helper: validasi HTTP method
|--------------------------------------------------*/
if (!function_exists('require_method')) {
  /**
   * Pastikan method request termasuk yang diizinkan, selain OPTIONS (diproses di CORS preflight).
   * @param array<int,string> $allowed
   */
  function require_method(array $allowed): void {
    $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($m === 'OPTIONS') { http_response_code(204); exit; }
    if (!in_array($m, $allowed, true)) {
      json_response(405, ['message' => 'Metode tidak diizinkan']);
    }
  }
}

/* -------------------------------------------------
|  Helper: ambil query param dengan tipe aman
|--------------------------------------------------*/
if (!function_exists('query_str')) {
  function query_str(string $key, ?string $default = null): ?string {
    if (!isset($_GET[$key])) return $default;
    $val = trim((string)$_GET[$key]);
    return $val === '' ? $default : $val;
  }
}
if (!function_exists('query_int')) {
  function query_int(string $key, ?int $default = null): ?int {
    if (!isset($_GET[$key])) return $default;
    return (int)$_GET[$key];
  }
}
if (!function_exists('paginate_from_query')) {
  /**
   * Hitung pagination (page, pageSize, offset) dengan batas aman.
   * @return array{page:int,pageSize:int,offset:int}
   */
  function paginate_from_query(): array {
    $page     = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = max(1, min((int)($_GET['pageSize'] ?? DEF_PAGE_SIZE), MAX_PAGE_SIZE));
    return ['page' => $page, 'pageSize' => $pageSize, 'offset' => ($page - 1) * $pageSize];
  }
}

/* -------------------------------------------------
|  Helper: response standar paginated
|--------------------------------------------------*/
if (!function_exists('json_paginated')) {
  /**
   * @param array<int,array<string,mixed>> $rows
   */
  function json_paginated(array $rows, int $total, int $page, int $pageSize, array $extra = []): void {
    json_response(200, array_merge([
      'data' => $rows,
      'pagination' => [
        'page'     => $page,
        'pageSize' => $pageSize,
        'total'    => $total,
        'pages'    => (int)ceil($total / max(1, $pageSize)),
      ],
    ], $extra));
  }
}

/* -------------------------------------------------
|  Helper: normalisasi sort (whitelist kolom)
|--------------------------------------------------*/
if (!function_exists('normalized_sort')) {
  /**
   * @param string[] $whitelist
   * @return array{sort:string,dir:string}
   */
  function normalized_sort(array $whitelist, string $defSort = 'created_at', string $defDir = 'DESC'): array {
    $sort = $_GET['sort'] ?? $defSort;
    $dir  = strtoupper($_GET['dir'] ?? $defDir);
    $sort = in_array($sort, $whitelist, true) ? $sort : $defSort;
    $dir  = in_array($dir, ['ASC','DESC'], true) ? $dir : $defDir;
    return ['sort' => $sort, 'dir' => $dir];
  }
}

/* -------------------------------------------------
|  Helper: aman untuk nilai boolean query (?active=true/false)
|--------------------------------------------------*/
if (!function_exists('to_bool')) {
  function to_bool($val, ?bool $default = null): ?bool {
    if ($val === null) return $default;
    $s = strtolower((string)$val);
    if (in_array($s, ['1','true','yes','y'], true)) return true;
    if (in_array($s, ['0','false','no','n'], true)) return false;
    return $default;
  }
}

/* -------------------------------------------------
|  Selesai bootstrap
|--------------------------------------------------*/
// $conn siap dipakai endpoint; helper terpasang; CORS sudah di-handle oleh database.php.
