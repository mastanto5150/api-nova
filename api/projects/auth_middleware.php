<?php
// Menggunakan library JWT dan kelas Key untuk verifikasi
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;

/**
 * Fungsi ini akan memeriksa token JWT dari header Authorization.
 * Jika token valid, fungsi akan mengembalikan payload (data user).
 * Jika tidak valid, fungsi akan mengirim response error 401 dan menghentikan eksekusi script.
 *
 * @return stdClass Payload dari token JWT yang berisi data user.
 */
function authenticate() {
    // Memeriksa apakah header Authorization ada
    if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
        json_response(401, ['message' => 'Akses ditolak. Token tidak ditemukan.']);
        exit();
    }

    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    // Memisahkan "Bearer" dari token-nya
    $parts = explode(' ', $authHeader);
    if (count($parts) !== 2 || $parts[0] !== 'Bearer') {
        json_response(401, ['message' => 'Akses ditolak. Format token tidak valid.']);
        exit();
    }

    $token = $parts[1];
    if (empty($token)) {
        json_response(401, ['message' => 'Akses ditolak. Token kosong.']);
        exit();
    }

    try {
        // Mencoba men-decode token.
        // Fungsi JWT::decode akan otomatis memverifikasi signature dan waktu kedaluwarsa.
        $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
        
        // Mengembalikan data user jika token valid
        return $decoded;

    } catch (ExpiredException $e) {
        // Menangkap error jika token sudah kedaluwarsa
        json_response(401, ['message' => 'Token kedaluwarsa. Silakan login kembali.']);
        exit();
    } catch (Exception $e) {
        // Menangkap error lainnya (misalnya: signature tidak valid)
        json_response(401, ['message' => 'Token tidak valid: ' . $e->getMessage()]);
        exit();
    }
}
?>