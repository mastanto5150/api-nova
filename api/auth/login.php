<?php
// File: api/auth/login.php
// Dependensi: $conn (mysqli), fungsi json_response(), konstanta JWT_SECRET, dan library firebase/php-jwt terpasang.

declare(strict_types=1);

use Firebase\JWT\JWT;

// --- CORS & JSON headers (aman untuk SPA) ---
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // produksi: ganti ke origin spesifik
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_response(405, ['message' => 'Metode tidak diizinkan']);
}

// --- Ambil & validasi body JSON ---
$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
  json_response(400, ['message' => 'Body harus JSON']);
}

$email = isset($data['email']) ? trim((string)$data['email']) : '';
$password = isset($data['password']) ? (string)$data['password'] : '';

if ($email === '' || $password === '') {
  json_response(400, ['message' => 'Email dan password wajib diisi']);
}

// --- Query user (prepared statement) ---
$sql = "SELECT id, full_name, email, role, password_hash, COALESCE(is_active,1) AS is_active
        FROM users WHERE email = ? LIMIT 1";

$stmt = $conn->prepare($sql);
if (!$stmt) {
  json_response(500, ['message' => 'Gagal menyiapkan statement']);
}

$stmt->bind_param('s', $email);

if (!$stmt->execute()) {
  $stmt->close();
  json_response(500, ['message' => 'Gagal eksekusi query']);
}

$result = $stmt->get_result();
$user = $result ? $result->fetch_assoc() : null;

// Tutup result/statement sesegera mungkin
if ($result) { $result->free(); }
$stmt->close();

// --- Cek user & status aktif (jangan bocorkan detail) ---
if (!$user || !(int)$user['is_active']) {
  json_response(401, ['message' => 'Email atau password salah']);
}

// --- Verifikasi password ---
if (!password_verify($password, $user['password_hash'])) {
  json_response(401, ['message' => 'Email atau password salah']);
}

// --- (Opsional) Rehash jika algoritma/cost berubah ---
if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
  $newHash = password_hash($password, PASSWORD_DEFAULT);
  $up = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ? LIMIT 1");
  if ($up) {
    $up->bind_param('si', $newHash, $user['id']);
    $up->execute();
    $up->close();
  }
}

// --- Siapkan payload JWT ---
$now  = time();
$ttl  = defined('JWT_TTL') ? (int)JWT_TTL : 86400; // default 1 hari
$exp  = $now + $ttl;
$iss  = defined('JWT_ISSUER') ? JWT_ISSUER : ($_SERVER['HTTP_HOST'] ?? 'nova-api');
$aud  = defined('JWT_AUDIENCE') ? JWT_AUDIENCE : $iss;
$sub  = (string)$user['id'];

if (!defined('JWT_SECRET') || JWT_SECRET === '') {
  json_response(500, ['message' => 'JWT secret tidak terkonfigurasi']);
}

$payload = [
  'iss'  => $iss,
  'aud'  => $aud,
  'iat'  => $now,
  'nbf'  => $now,
  'exp'  => $exp,
  'sub'  => $sub,
  'data' => [
    'id'        => (int)$user['id'],
    'email'     => $user['email'],
    'full_name' => $user['full_name'],
    'role'      => $user['role'],
  ],
];

// --- Generate token ---
$jwt = JWT::encode($payload, JWT_SECRET, 'HS256');

// --- Response tanpa bocorkan hash ---
$responseUser = [
  'id'        => (int)$user['id'],
  'full_name' => $user['full_name'],
  'email'     => $user['email'],
  'role'      => $user['role'],
];

json_response(200, [
  'token'       => $jwt,
  'expires_in'  => $ttl,
  'user'        => $responseUser,
]);
