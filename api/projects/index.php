<?php
// File: api/projects/index.php
// Dipanggil dari /api/index.php — asumsi: $conn (mysqli), json_response(), dan konstanta JWT_* sudah tersedia.
// Perbaikan besar:
// - Pakai prepared statements di semua query (anti-SQLi)  (lihat docs mysqli prepared) :contentReference[oaicite:0]{index=0}
// - Tambah pagination, pencarian, filter, dan sort yang di-whitelist (best practice MySQL LIMIT/OFFSET) :contentReference[oaicite:1]{index=1}
// - Validasi method + response yang rapi (CORS ditangani di bootstrap).
// - Tab data diamankan lewat peta nama tabel (bukan interpolasi string mentah).
// - S-Curve mengembalikan pasangan [timestamp, percent] (siap untuk chart).

declare(strict_types=1);

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/** Helper: ambil bearer (opsional: pakai untuk proteksi). */
function current_user_or_null(): ?array {
  $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
  if (!preg_match('/^Bearer\s+(.+)$/i', $hdr, $m)) return null;
  $token = $m[1];
  try {
    $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
    // data standar dari login.php: $payload['data'] = ['id','email','full_name','role']
    return [
      'id'   => (int)($decoded->data->id ?? 0),
      'role' => (string)($decoded->data->role ?? ''),
    ];
  } catch (Throwable $e) {
    return null;
  }
}

/** Guard (aktifkan jika semua endpoint wajib auth) */
// $me = current_user_or_null();
// if (!$me) json_response(401, ['message' => 'Unauthorized']);

$project_id = $path_parts[1] ?? null;
$tab        = $path_parts[2] ?? null;

if ($project_id && $tab) {
  handle_project_tabs($conn, (int)$project_id, $tab);
} elseif ($project_id) {
  handle_get_project($conn, (int)$project_id);
} else {
  handle_projects_collection($conn);
}

// ---------------- Collection (/api/projects) ----------------
function handle_projects_collection(mysqli $conn): void {
  $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
  switch ($method) {
    case 'GET':
      // Query params
      $q         = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
      $status    = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
      $type      = isset($_GET['type']) ? trim((string)$_GET['type']) : '';
      $page      = max(1, (int)($_GET['page'] ?? 1));
      $pageSize  = max(1, min(100, (int)($_GET['pageSize'] ?? 20)));
      $offset    = ($page - 1) * $pageSize;

      // Sorting whitelist
      $allowedSort = ['created_at','start_date','end_date','budget','name'];
      $sort   = in_array(($_GET['sort'] ?? 'created_at'), $allowedSort, true) ? $_GET['sort'] : 'created_at';
      $dir    = strtoupper($_GET['dir'] ?? 'DESC');
      $dir    = in_array($dir, ['ASC','DESC'], true) ? $dir : 'DESC';

      // Build WHERE dinamis
      $where = [];
      $params = [];
      $types  = '';

      if ($q !== '') {
        $where[] = '(name LIKE ? OR client LIKE ?)';
        $like = "%{$q}%";
        $params[] = $like; $params[] = $like; $types .= 'ss';
      }
      if ($status !== '') {
        $where[] = 'status = ?';
        $params[] = $status; $types .= 's';
      }
      if ($type !== '') {
        $where[] = 'type = ?';
        $params[] = $type; $types .= 's';
      }
      $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

      // Total count (untuk pagination) — jalankan terpisah (praktik umum) :contentReference[oaicite:2]{index=2}
      $countSql = "SELECT COUNT(*) AS total FROM projects {$whereSql}";
      $stmt = $conn->prepare($countSql);
      if (!$stmt) json_response(500, ['message' => 'Gagal menyiapkan count']);

      if ($types) { $stmt->bind_param($types, ...$params); }
      $stmt->execute();
      $res = $stmt->get_result();
      $total = (int)($res->fetch_assoc()['total'] ?? 0);
      $stmt->close();

      // Data query
      $sql = "SELECT id, name, client, budget, start_date, end_date, status, type, created_by, created_at
              FROM projects
              {$whereSql}
              ORDER BY {$sort} {$dir}
              LIMIT ? OFFSET ?";
      $stmt = $conn->prepare($sql);
      if (!$stmt) json_response(500, ['message' => 'Gagal menyiapkan query']);

      // bind param dinamis + limit/offset
      $types2 = $types . 'ii';
      $params2 = $params;
      $params2[] = $pageSize;
      $params2[] = $offset;
      $stmt->bind_param($types2, ...$params2);

      $stmt->execute();
      $result = $stmt->get_result();
      $rows = [];
      while ($row = $result->fetch_assoc()) { $rows[] = $row; }
      $stmt->close();

      json_response(200, [
        'data'      => $rows,
        'pagination'=> [
          'page'     => $page,
          'pageSize' => $pageSize,
          'total'    => $total,
          'pages'    => (int)ceil($total / $pageSize),
          'sort'     => $sort,
          'dir'      => $dir
        ],
      ]);
      break;

    case 'POST':
      // Wajib auth untuk membuat project
      $me = current_user_or_null();
      if (!$me) json_response(401, ['message' => 'Unauthorized']);

      $raw = file_get_contents('php://input') ?: '';
      $data = json_decode($raw, true);
      if (!is_array($data)) json_response(400, ['message' => 'Body harus JSON']);

      $name       = trim((string)($data['name'] ?? ''));
      $client     = trim((string)($data['client'] ?? ''));
      $budget     = (float)($data['budget'] ?? 0);
      $start_date = !empty($data['start']) ? (string)$data['start'] : null;
      $end_date   = !empty($data['end']) ? (string)$data['end'] : null;
      $status     = (string)($data['status'] ?? 'Baru');
      $type       = (string)($data['type'] ?? 'Konstruksi');
      $created_by = (int)($data['created_by'] ?? $me['id']);

      if ($name === '' || $client === '') {
        json_response(422, ['message' => 'name dan client wajib diisi']);
      }

      $stmt = $conn->prepare(
        "INSERT INTO projects (name, client, budget, start_date, end_date, status, type, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
      );
      if (!$stmt) json_response(500, ['message' => 'Gagal menyiapkan statement']);

      $stmt->bind_param('ssdssssi', $name, $client, $budget, $start_date, $end_date, $status, $type, $created_by);
      if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        json_response(500, ['message' => 'Gagal membuat proyek', 'error' => $err]);
      }
      $newId = $conn->insert_id;
      $stmt->close();

      json_response(201, ['message' => 'Proyek berhasil dibuat', 'id' => $newId]);
      break;

    default:
      json_response(405, ['message' => 'Metode tidak diizinkan']);
  }
}

// ---------------- Item (/api/projects/:id) ----------------
function handle_get_project(mysqli $conn, int $id): void {
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    json_response(405, ['message' => 'Metode tidak diizinkan']);
  }

  $stmt = $conn->prepare("SELECT * FROM projects WHERE id = ? LIMIT 1");
  if (!$stmt) json_response(500, ['message' => 'Gagal menyiapkan statement']);
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();
  $stmt->close();

  if (!$row) json_response(404, ['message' => 'Proyek tidak ditemukan']);
  json_response(200, $row);
}

// ---------------- Tabs (/api/projects/:id/:tab) ----------------
function handle_project_tabs(mysqli $conn, int $id, string $tab): void {
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    json_response(405, ['message' => 'Metode tidak diizinkan']);
  }

  // Pastikan project ada
  $chk = $conn->prepare("SELECT 1 FROM projects WHERE id = ? LIMIT 1");
  $chk->bind_param('i', $id);
  $chk->execute();
  $exists = $chk->get_result()->fetch_row();
  $chk->close();
  if (!$exists) json_response(404, ['message' => 'Proyek tidak ditemukan']);

  // Map tab → tabel/view (whitelist, anti-SQLi)
  $table_map = [
    'expenses'            => 'expenses',
    'daily-reports'       => 'daily_reports',
    'boq'                 => 'boq_items',
    'purchase-requests'   => 'purchase_requests',
  ];

  if (isset($table_map[$tab])) {
    $tbl = $table_map[$tab];

    // Filter opsional: ?from=YYYY-MM-DD&to=YYYY-MM-DD&status=...
    $from   = isset($_GET['from']) ? (string)$_GET['from'] : null;
    $to     = isset($_GET['to']) ? (string)$_GET['to'] : null;
    $status = isset($_GET['status']) ? trim((string)$_GET['status']) : null;

    $where  = ['project_id = ?'];
    $types  = 'i';
    $params = [$id];

    if ($from) { $where[] = "DATE(created_at) >= ?"; $types .= 's'; $params[] = $from; }
    if ($to)   { $where[] = "DATE(created_at) <= ?"; $types .= 's'; $params[] = $to; }
    if ($status && $tbl !== 'boq_items') { $where[] = "status = ?"; $types .= 's'; $params[] = $status; }

    $sql = "SELECT * FROM `{$tbl}` WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) json_response(500, ['message' => 'Gagal menyiapkan statement']);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
    $stmt->close();

    json_response(200, $rows);
    return;
  }

  if ($tab === 'scurve') {
    // Kembalikan pasangan [timestamp(ms), percent]
    // planned: s_curve_baseline(planned_cumulative, curve_date)
    // actual : s_curve_actual(actual_cumulative, curve_date)
    $planned = [];
    $stmt = $conn->prepare("SELECT curve_date, planned_cumulative FROM s_curve_baseline WHERE project_id = ? ORDER BY curve_date");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
      $planned[] = [ (strtotime($row['curve_date']) * 1000), (float)$row['planned_cumulative'] ];
    }
    $stmt->close();

    $actual = [];
    $stmt = $conn->prepare("SELECT curve_date, actual_cumulative FROM s_curve_actual WHERE project_id = ? ORDER BY curve_date");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
      $actual[] = [ (strtotime($row['curve_date']) * 1000), (float)$row['actual_cumulative'] ];
    }
    $stmt->close();

    json_response(200, ['planned' => $planned, 'actual' => $actual]);
    return;
  }

  json_response(404, ['message' => 'Tab tidak ditemukan']);
}
