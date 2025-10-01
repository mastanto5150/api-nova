<?php
// File: api/projects/auth_middleware.php
// Middleware otentikasi & otorisasi berbasis JWT (firebase/php-jwt).
// Prasyarat: require_once '../config/database.php' dipanggil lebih dulu (autoload + konstanta JWT_* tersedia).

declare(strict_types=1);

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Ambil nilai Authorization: Bearer <token> dari berbagai server API.
 */
function _get_authorization_header(): ?string {
  // Standar: $_SERVER['HTTP_AUTHORIZATION'] (Nginx/FastCGI)
  if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    return trim((string)$_SERVER['HTTP_AUTHORIZATION']);
  }
  // Beberapa lingkungan menyimpan di 'Authorization'
  if (!empty($_SERVER['Authorization'])) {
    return trim((string)$_SERVER['Authorization']);
  }
  // Apache: apache_request_headers()
  if (function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    // Normalisasi key agar case-insensitive
    $map = [];
    foreach ($headers as $k => $v) $map[strtolower($k)] = $v;
    if (isset($map['authorization'])) return trim((string)$map['authorization']);
  }
  return null;
}

/**
 * Ekstrak token Bearer dari header Authorization.
 */
function _get_bearer_token(): ?string {
  $hdr = _get_authorization_header();
  if (!$hdr) return null;
  if (preg_match('/^Bearer\s+(.+)$/i', $hdr, $m)) return $m[1];
  return null;
}

/**
 * Verifikasi JWT, kembalikan array klaim user atau null bila invalid.
 * Atur leeway kecil untuk toleransi skew waktu antar server (mis. 60 detik).
 */
function _verify_jwt(?string $token): ?array {
  if (!$token) return null;

  // Pastikan secret terdefinisi
  if (!defined('JWT_SECRET') || JWT_SECRET === '') return null;

  // Toleransi clock skew
  // NB: properti statis pada JWT dipakai oleh decoder internal.
  JWT::$leeway = defined('JWT_LEEWAY') ? (int)JWT_LEEWAY : 60;

  try {
    $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
    // Bentuk payload (ikuti struktur dari login.php)
    $data = [
      'id'        => isset($decoded->data->id) ? (int)$decoded->data->id : 0,
      'email'     => isset($decoded->data->email) ? (string)$decoded->data->email : '',
      'full_name' => isset($decoded->data->full_name) ? (string)$decoded->data->full_name : '',
      'role'      => isset($decoded->data->role) ? (string)$decoded->data->role : '',
      // meta
      'exp'       => isset($decoded->exp) ? (int)$decoded->exp : null,
      'iat'       => isset($decoded->iat) ? (int)$decoded->iat : null,
      'sub'       => isset($decoded->sub) ? (string)$decoded->sub : null,
    ];
    return $data['id'] ? $data : null;
  } catch (Throwable $e) {
    return null;
  }
}

/**
 * Ambil user terotentikasi (array) atau null.
 */
function auth_user(): ?array {
  static $cached = null; // cache per-request
  if ($cached !== null) return $cached;
  $cached = _verify_jwt(_get_bearer_token());
  return $cached;
}

/**
 * Require auth — kirim 401 jika belum login.
 */
function require_auth(): array {
  $user = auth_user();
  if (!$user) {
    json_response(401, ['message' => 'Unauthorized']);
  }
  return $user;
}

/**
 * Require role tertentu. $roles bisa string atau array string (mis. ['owner','manager']).
 * Jika role tidak cocok -> 403.
 */
function require_role($roles): array {
  $user = require_auth();
  $roles = is_array($roles) ? $roles : [$roles];
  if (!in_array($user['role'], $roles, true)) {
    json_response(403, ['message' => 'Forbidden']);
  }
  return $user;
}
