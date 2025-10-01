<?php
// [FIX] Panggil middleware di paling atas untuk melindungi semua endpoint pengguna
require_once __DIR__ . '/../middleware/auth_middleware.php';

// [FIX] Jalankan fungsi otentikasi. Jika token tidak valid, script akan berhenti di sini.
$decoded_token = authenticate();

// File ini dipanggil dari /api/index.php dan variabel $conn sudah tersedia
switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST': // Membuat user baru
        // Catatan: Sebaiknya logika ini diberi perlindungan tambahan,
        // misalnya hanya user dengan role 'admin' yang boleh membuat user baru.
        // if ($decoded_token->data->role !== 'admin') {
        //     json_response(403, ['message' => 'Akses ditolak: Hanya admin yang dapat membuat pengguna.']);
        //     return;
        // }

        $data = json_decode(file_get_contents("php://input"));

        if (!isset($data->full_name) || !isset($data->email) || !isset($data->password) || !isset($data->role)) {
            json_response(400, ['message' => 'Semua field wajib diisi']);
            return;
        }

        $full_name = $data->full_name;
        $email = $data->email;
        $role = $data->role;
        $password_hash = password_hash($data->password, PASSWORD_BCRYPT);

        // Mencegah SQL Injection dengan Prepared Statements
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)");
        if (!$stmt) {
            json_response(500, ['message' => 'Gagal menyiapkan statement: ' . $conn->error]);
            return;
        }

        $stmt->bind_param("ssss", $full_name, $email, $password_hash, $role);

        if ($stmt->execute()) {
            $new_user_id = $conn->insert_id;
            json_response(201, ['message' => 'Pengguna berhasil dibuat', 'id' => $new_user_id]);
        } else {
            // Cek jika error disebabkan oleh duplikat email
            if ($conn->errno == 1062) {
                json_response(409, ['message' => 'Email sudah terdaftar']);
            } else {
                json_response(500, ['message' => 'Gagal membuat pengguna: ' . $stmt->error]);
            }
        }
        $stmt->close();
        break;

    case 'GET': // Mengambil daftar user
        $sql = "SELECT id, full_name, email, role FROM users";
        $result = $conn->query($sql);

        if ($result) {
            $users = [];
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
            json_response(200, $users);
        } else {
            json_response(500, ['message' => 'Gagal mengambil data pengguna: ' . $conn->error]);
        }
        break;

    default:
        json_response(405, ['message' => 'Metode tidak diizinkan']);
        break;
}
?>