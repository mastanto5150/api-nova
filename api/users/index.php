<?php
// File: api/users/index.php
// Dipanggil dari /api/index.php — asumsi: sudah require_once '../config/database.php' (mysqli $conn, konstanta JWT_*)
// dan require_once '../projects/auth_middleware.php' (fungsi auth_user(), require_auth(), require_role()).

declare(strict_types=1);

// Routing sederhana berdasarkan $path_parts dari index.php
$uid = $path_parts[1] ?? null;
$sub = $path_parts[2] ?? null;

if ($uid === 'me') {
  handle_me($conn);
  return;
}

if ($uid && ctype_digit($uid)) {
  handle_user_item($conn, (int)$uid);
  return;
}

handle_users_collection($conn);


// ========================= Handlers =========================

/**
 * GET /api/users
 * POST /api/users
 */
function handle_users_collection(mysqli $conn): void {
  switch ($_SERVER['REQUEST_METHOD'] ?? 'GET') {
    case 'GET':
      // Hanya role OWNER/MANAGER boleh melihat daftar user
      require_role(['owner','manager']);

      $q        = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
      $role     = isset($_GET['role']) ? trim((string)$_GET['role']) : '';
      $active   = isset($_GET['active']) ? trim((string)$_GET['active']) : '';
      $page     = max(1, (int)($_GET['page'] ?? 1));
      $pageSize = max(1, min(100, (int)($_GET['pageSize'] ?? 20)));
      $offset   = ($page - 1) * $pageSize;

      $allowedSort = ['created_at','full_name','email','role'];
      $sort = in_array(($_GET['sort'] ?? 'created_at'), $allowedSort, true) ? $_GET['sort'] : 'created_at';
      $dir  = strtoupper($_GET['dir'] ?? 'DESC');
      $dir  = in_array($dir, ['ASC','DESC'], true) ? $dir : 'DESC';

      $where = [];
      $types = '';
      $params = [];

      if ($q !== '') {
        $where[] = '(full_name LIKE ? OR email LIKE ?)';
        $like = "%{$q}%";
        $params[] = $like; $params[] = $like; $types .= 'ss';
      }
      if ($role !== '') {
        $where[] = 'role = ?';
        $params[] = $role; $types .= 's';
      }
      if ($active !== '') {
        // terima '1' | '0' | 'true' | 'false'
        $isActive = in_array(strtolower($active), ['1','true','yes'], true) ? 1 : 0;
        $where[] = 'is_active = ?';
        $params[] = $isActive; $types .= 'i';
      }

      $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

      // hitung total
      $countSql = "SELECT COUNT(*) AS total FROM users {$whereSql}";
      $stmt = $conn->prepare($countSql);
      if (!$stmt) json_response(500, ['message' => 'Gagal menyiapkan count']);
      if ($types) $stmt->bind_param($types, ...$params);
      $stmt->execute();
      $res = $stmt->get_result();
      $total = (int)($res->fetch_assoc()['total'] ?? 0);
      $stmt->close();

      // data
      $sql = "SELECT id, full_name, email, role, is_active, created_at, updated_at
              FROM users
              {$whereSql}
              ORDER BY {$sort} {$dir}
              LIMIT ? OFFSET ?";
      $stmt = $conn->prepare($sql);
      if (!$stmt) json_response(500, ['message' => 'Gagal menyiapkan query']);
      $types2 = $types . 'ii';
      $params2 = $params; $params2[] = $pageSize; $params2[] = $offset;
      $stmt->bind_param($types2, ...$params2);
      $stmt->execute();
      $rs = $stmt->get_result();

      $rows = [];
      while ($row = $rs->fetch_assoc()) $rows[] = $row;
      $stmt->close();

      json_response(200, [
        'data' => $rows,
        'pagination' => [
          'page' => $page,
          'pageSize' => $pageSize,
          'total' => $total,
          'pages' => (int)ceil($total / $pageSize),
          'sort' => $sort,
          'dir' => $dir,
        ]
      ]);
      break;

    case 'POST':
      // Hanya OWNER/MANAGER boleh membuat user
      require_role(['owner','manager']);

      $raw = file_get_contents('php://input') ?: '';
      $data = json_decode($raw, true);
      if (!is_array($data)) json_response(400, ['message' => 'Body harus JSON']);

      $full_name = trim((string)($data['full_name'] ?? ''));
      $email     = strtolower(trim((string)($data['email'] ?? '')));
      $role      = trim((string)($data['role'] ?? 'staff'));
      $password  = (string)($data['password'] ?? '');

      if ($full_name === '' || $email === '' || $password === '') {
        json_response(422, ['message' => 'full_name, email, dan password wajib diisi']);
      }

      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(422, ['message' => 'Format email tidak valid']);
      }

      $hash = password_hash($password, PASSWORD_DEFAULT);
      $is_active = 1;

      $stmt = $conn->prepare(
        "INSERT INTO users (full_name, email, role, password_hash, is_active, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, NOW(), NOW())"
      );
      if (!$stmt) json_response(500, ['message' => 'Gagal menyiapkan statement']);

      $stmt->bind_param('ssssi', $full_name, $email, $role, $hash, $is_active);
      try {
        $stmt->execute();
      } catch (mysqli_sql_exception $e) {
        $stmt->close();
        // 1062: duplicate entry
        if ((int)$e->getCode() === 1062) {
          json_response(409, ['message' => 'Email sudah terdaftar']);
        }
        json_response(500, ['message' => 'Gagal membuat user']);
      }
      $newId = $conn->insert_id;
      $stmt->close();

      json_response(201, ['message' => 'User dibuat', 'id' => $newId]);
      break;

    default:
      json_response(405, ['message' => 'Metode tidak diizinkan']);
  }
}

/**
 * GET /api/users/:id
 * PUT /api/users/:id
 * DELETE /api/users/:id (soft delete: set is_active = 0)
 */
function handle_user_item(mysqli $conn, int $id): void {
  $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

  if ($method === 'GET') {
    // Boleh diakses oleh OWNER/MANAGER; atau user itu sendiri
    $me = require_auth();
    if ($me['role'] !== 'owner' && $me['role'] !== 'manager' && $me['id'] !== $id) {
      json_response(403, ['message' => 'Forbidden']);
    }

    $stmt = $conn->prepare("SELECT id, full_name, email, role, is_active, created_at, updated_at FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) json_response(500, ['message' => 'Gagal menyiapkan statement']);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    if (!$row) json_response(404, ['message' => 'User tidak ditemukan']);
    json_response(200, $row);
  }

  if ($method === 'PUT' || $method === 'PATCH') {
    // OWNER/MANAGER boleh ubah profil user; user boleh ubah profilnya sendiri (kecuali role & aktif)
    $me = require_auth();

    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    if (!is_array($data)) json_response(400, ['message' => 'Body harus JSON']);

    // Ambil saat ini untuk validasi
    $cur = $conn->prepare("SELECT id, full_name, email, role, is_active FROM users WHERE id = ? LIMIT 1");
    $cur->bind_param('i', $id);
    $cur->execute();
    $r = $cur->get_result()->fetch_assoc();
    $cur->close();
    if (!$r) json_response(404, ['message' => 'User tidak ditemukan']);

    // Field yang boleh diubah
    $full_name = array_key_exists('full_name', $data) ? trim((string)$data['full_name']) : $r['full_name'];
    $email     = array_key_exists('email', $data) ? strtolower(trim((string)$data['email'])) : $r['email'];

    // Role & is_active hanya oleh owner/manager
    $role      = $r['role'];
    $is_active = (int)$r['is_active'];

    if ($me['role'] === 'owner' || $me['role'] === 'manager') {
      if (array_key_exists('role', $data))      $role = trim((string)$data['role']);
      if (array_key_exists('is_active', $data)) $is_active = in_array($data['is_active'], [1,'1',true,'true'], true) ? 1 : 0;
    } else {
      // kalau bukan owner/manager, hanya boleh ubah diri sendiri
      if ($me['id'] !== $id) json_response(403, ['message' => 'Forbidden']);
    }

    // Validasi email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      json_response(422, ['message' => 'Format email tidak valid']);
    }

    // Update utama
    $stmt = $conn->prepare(
      "UPDATE users SET full_name = ?, email = ?, role = ?, is_active = ?, updated_at = NOW() WHERE id = ?"
    );
    if (!$stmt) json_response(500, ['message' => 'Gagal menyiapkan statement']);
    $stmt->bind_param('sssii', $full_name, $email, $role, $is_active, $id);
    try {
      $stmt->execute();
    } catch (mysqli_sql_exception $e) {
      $stmt->close();
      if ((int)$e->getCode() === 1062) json_response(409, ['message' => 'Email sudah digunakan']);
      json_response(500, ['message' => 'Gagal update user']);
    }
    $stmt->close();

    // Optional: ganti password jika field 'new_password' dikirim
    if (isset($data['new_password']) && $data['new_password'] !== '') {
      // Jika bukan owner/manager, hanya boleh ganti password dirinya
      if ($me['role'] !== 'owner' && $me['role'] !== 'manager' && $me['id'] !== $id) {
        json_response(403, ['message' => 'Forbidden (password)']);
      }
      $hash = password_hash((string)$data['new_password'], PASSWORD_DEFAULT);
      $pw = $conn->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
      $pw->bind_param('si', $hash, $id);
      $pw->execute();
      $pw->close();
    }

    json_response(200, ['message' => 'User diperbarui']);
  }

  if ($method === 'DELETE') {
    // Hanya OWNER/MANAGER — soft delete
    require_role(['owner','manager']);
    $stmt = $conn->prepare("UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    if ($affected < 1) json_response(404, ['message' => 'User tidak ditemukan']);
    json_response(200, ['message' => 'User dinonaktifkan']);
  }

  json_response(405, ['message' => 'Metode tidak diizinkan']);
}

/**
 * GET /api/users/me
 * Profil user saat ini dari JWT.
 */
function handle_me(mysqli $conn): void {
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    json_response(405, ['message' => 'Metode tidak diizinkan']);
  }
  $me = require_auth();

  $stmt = $conn->prepare("SELECT id, full_name, email, role, is_active, created_at, updated_at FROM users WHERE id = ? LIMIT 1");
  $stmt->bind_param('i', $me['id']);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();
  $stmt->close();

  if (!$row) json_response(404, ['message' => 'User tidak ditemukan']);
  json_response(200, $row);
}
