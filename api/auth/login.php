<?php
// [SECURITY] Menggunakan library JWT yang sudah di-install
use Firebase\JWT\JWT;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, ['message' => 'Metode tidak diizinkan']);
    return;
}

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->email) || !isset($data->password)) {
    json_response(400, ['message' => 'Email dan password wajib diisi']);
    return;
}

// [SECURITY] Mencegah SQL Injection dengan Prepared Statements
$email = $data->email; // Tidak perlu escape manual lagi

// Menyiapkan statement
$stmt = $conn->prepare("SELECT id, full_name, email, role, password_hash FROM users WHERE email = ? LIMIT 1");
if (!$stmt) {
    json_response(500, ['message' => 'Gagal menyiapkan statement: ' . $conn->error]);
    return;
}

// Bind parameter
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    
    // Verifikasi password
    if (password_verify($data->password, $user['password_hash'])) {
        // [SECURITY] Buat token JWT yang aman, bukan base64
        $payload = [
            'iat' => time(), // Issued at: Waktu token dibuat
            'exp' => time() + (60 * 60 * 24), // Expiration time: 1 hari (detik * menit * jam)
            'data' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'role' => $user['role']
            ]
        ];

        $jwt = JWT::encode($payload, JWT_SECRET, 'HS256');
        
        // Hapus hash password dari output
        unset($user['password_hash']);

        json_response(200, [
            'token' => $jwt,
            'user' => $user
        ]);
        // Tidak perlu return di sini karena json_response sudah exit
    }
}

// Jika loop selesai tanpa menemukan user atau password salah
json_response(401, ['message' => 'Email atau password salah']);

$stmt->close();
?>