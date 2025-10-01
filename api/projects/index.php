<?php
// File ini dipanggil dari /api/index.php dan variabel $conn sudah tersedia

$project_id = $path_parts[1] ?? null;
$tab = $path_parts[2] ?? null;

// PERBAIKAN: Memasukkan variabel $conn ke dalam fungsi yang membutuhkannya
if ($project_id && $tab) {
    handle_project_tabs($conn, $project_id, $tab);
} else if ($project_id) {
    handle_get_project($conn, $project_id);
} else {
    handle_projects_collection($conn);
}

// PERBAIKAN: Menambahkan parameter $conn di definisi fungsi
function handle_projects_collection($conn) {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            $sql = "SELECT * FROM projects ORDER BY created_at DESC";
            $result = $conn->query($sql);
            $projects = [];
            while($row = $result->fetch_assoc()) {
                $projects[] = $row;
            }
            json_response(200, $projects);
            break;

        case 'POST':
            $data = json_decode(file_get_contents("php://input"));
            
            $name = $data->name ?? '';
            $client = $data->client ?? '';
            $budget = (float)($data->budget ?? 0);
            $start_date = !empty($data->start) ? $data->start : null;
            $end_date = !empty($data->end) ? $data->end : null;
            $status = $data->status ?? 'Baru';
            $type = $data->type ?? 'Konstruksi';
            $created_by = (int)($data->created_by ?? null);

            $stmt = $conn->prepare(
                "INSERT INTO projects (name, client, budget, start_date, end_date, status, type, created_by) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            if (!$stmt) {
                json_response(500, ['message' => 'Gagal menyiapkan statement: ' . $conn->error]);
                return;
            }

            $stmt->bind_param("ssdssssi", $name, $client, $budget, $start_date, $end_date, $status, $type, $created_by);
            
            if ($stmt->execute()) {
                json_response(201, ['message' => 'Proyek berhasil dibuat', 'id' => $conn->insert_id]);
            } else {
                json_response(500, ['message' => 'Gagal membuat proyek: ' . $stmt->error]);
            }
            $stmt->close();
            break;

        default:
            json_response(405, ['message' => 'Metode tidak diizinkan']);
            break;
    }
}

// PERBAIKAN: Menambahkan parameter $conn di definisi fungsi
function handle_get_project($conn, $id) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        json_response(405, ['message' => 'Metode tidak diizinkan']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
    if (!$stmt) {
        json_response(500, ['message' => 'Gagal menyiapkan statement: ' . $conn->error]);
        return;
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        json_response(200, $result->fetch_assoc());
    } else {
        json_response(404, ['message' => 'Proyek tidak ditemukan']);
    }
    $stmt->close();
}

// PERBAIKAN: Menambahkan parameter $conn di definisi fungsi
function handle_project_tabs($conn, $id, $tab) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        json_response(405, ['message' => 'Metode tidak diizinkan']);
        return;
    }

    $table_map = [
        'expenses' => 'expenses',
        'daily-reports' => 'daily_reports',
        'boq' => 'boq_items',
        'purchase-requests' => 'purchase_requests'
    ];

    if (array_key_exists($tab, $table_map)) {
        $table_name = $table_map[$tab];
        
        $stmt = $conn->prepare("SELECT * FROM `$table_name` WHERE project_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $items = [];
        while($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        json_response(200, $items);
        $stmt->close();

    } elseif ($tab === 'scurve') {
        $planned = [];
        $stmt_planned = $conn->prepare("SELECT planned_cumulative as value FROM s_curve_baseline WHERE project_id = ? ORDER BY curve_date");
        $stmt_planned->bind_param("i", $id);
        $stmt_planned->execute();
        $res_planned = $stmt_planned->get_result();
        while($row = $res_planned->fetch_assoc()) $planned[] = (float)$row['value'];
        $stmt_planned->close();

        $actual = [];
        $stmt_actual = $conn->prepare("SELECT actual_cumulative as value FROM s_curve_actual WHERE project_id = ? ORDER BY curve_date");
        $stmt_actual->bind_param("i", $id);
        $stmt_actual->execute();
        $res_actual = $stmt_actual->get_result();
        while($row = $res_actual->fetch_assoc()) $actual[] = (float)$row['value'];
        $stmt_actual->close();

        json_response(200, ['planned' => $planned, 'actual' => $actual]);
    } else {
        json_response(404, ['message' => 'Tab tidak ditemukan']);
    }
}
?>