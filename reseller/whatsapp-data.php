<?php
$pageTitle = 'WhatsApp Dynamic Data';
$breadcrumb = [['label'=>'WhatsApp'],['label'=>'Dynamic Data']];
require_once __DIR__ . '/layout.php';

$uid = $user['id'];

// Handle AJAX Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    if (!csrf_verify()) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    $action = $_POST['action'];

    // 1. Bulk Upload
    if ($action === 'upload_data') {
        $tableName = sanitize($_POST['table_name']);
        $data = json_decode($_POST['data'], true);
        $keyColumn = $_POST['key_column'];

        if (empty($tableName) || empty($data) || empty($keyColumn)) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }

        $columns = array_keys($data[0]);
        $schema = array_map(function($c) {
            $type = (stripos($c, 'image') !== false || stripos($c, 'picture') !== false) ? 'image' : 'text';
            return ['name' => $c, 'type' => $type];
        }, $columns);
        DB::execute("INSERT INTO whatsapp_data_schemas (user_id, table_name, columns) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE columns = VALUES(columns)", [$uid, $tableName, json_encode($schema)]);

        DB::execute("DELETE FROM whatsapp_custom_data WHERE user_id = ? AND table_name = ?", [$uid, $tableName]);
        $count = 0;
        foreach ($data as $row) {
            $key = (string)($row[$keyColumn] ?? '');
            if (!$key) continue;
            DB::execute("INSERT INTO whatsapp_custom_data (user_id, table_name, data_key, data_value) VALUES (?, ?, ?, ?)", [$uid, $tableName, $key, json_encode($row)]);
            $count++;
        }
        echo json_encode(['success' => true, 'message' => "Successfully imported $count rows"]);
        exit;
    }

    // 2. Add/Edit Single Row
    if ($action === 'save_row') {
        $tableName = sanitize($_POST['table_name']);
        $rowId = (int)($_POST['row_id'] ?? 0);
        $keyColumn = $_POST['key_column'];
        $rowData = $_POST['row_data'] ?? [];

        if (!empty($_FILES['images'])) {
            foreach ($_FILES['images']['tmp_name'] as $fieldName => $tmpName) {
                if ($tmpName) {
                    $ext = pathinfo($_FILES['images']['name'][$fieldName], PATHINFO_EXTENSION);
                    $fileName = 'data_' . uniqid() . '.' . $ext;
                    $uploadPath = __DIR__ . '/../uploads/whatsapp/data/' . $fileName;
                    if (!is_dir(dirname($uploadPath))) mkdir(dirname($uploadPath), 0777, true);
                    if (move_uploaded_file($tmpName, $uploadPath)) {
                        $rowData[$fieldName] = '/uploads/whatsapp/data/' . $fileName;
                    }
                }
            }
        }

        $dataKey = $rowData[$keyColumn] ?? '';
        if (!$dataKey) {
            echo json_encode(['success' => false, 'message' => 'The Unique Key ('.$keyColumn.') cannot be empty']);
            exit;
        }

        if ($rowId > 0) {
            DB::execute("UPDATE whatsapp_custom_data SET data_key = ?, data_value = ? WHERE id = ? AND user_id = ?", [$dataKey, json_encode($rowData), $rowId, $uid]);
        } else {
            DB::execute("INSERT INTO whatsapp_custom_data (user_id, table_name, data_key, data_value) VALUES (?, ?, ?, ?)", [$uid, $tableName, $dataKey, json_encode($rowData)]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // 3. Create Table Schema (Manual)
    if ($action === 'create_table') {
        $tableName = sanitize($_POST['table_name']);
        $columns = json_decode($_POST['columns'], true);
        DB::execute("INSERT INTO whatsapp_data_schemas (user_id, table_name, columns) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE columns = VALUES(columns)", [$uid, $tableName, json_encode($columns)]);
        echo json_encode(['success' => true]);
        exit;
    }
}

// Handle Deletions
if (isset($_GET['delete_row'])) {
    $id = (int)$_GET['delete_row'];
    DB::execute("DELETE FROM whatsapp_custom_data WHERE id = ? AND user_id = ?", [$id, $uid]);
    flash_set('success', 'Row deleted.');
    header("Location: whatsapp-data.php?view=" . urlencode($_GET['table']));
    exit;
}

if (isset($_GET['delete_table'])) {
    $table = sanitize($_GET['delete_table']);
    DB::execute("DELETE FROM whatsapp_custom_data WHERE user_id = ? AND table_name = ?", [$uid, $table]);
    DB::execute("DELETE FROM whatsapp_data_schemas WHERE user_id = ? AND table_name = ?", [$uid, $table]);
    flash_set('success', "Table '$table' deleted.");
    header("Location: whatsapp-data.php");
    exit;
}

$viewTable = $_GET['view'] ?? null;
if ($viewTable) {
    $schemaRow = DB::queryOne("SELECT columns FROM whatsapp_data_schemas WHERE user_id = ? AND table_name = ?", [$uid, $viewTable]);
    $schema = $schemaRow ? json_decode($schemaRow['columns'], true) : [];
    $rows = DB::query("SELECT * FROM whatsapp_custom_data WHERE user_id = ? AND table_name = ? ORDER BY id DESC", [$uid, $viewTable]);
} else {
    $tables = DB::query("
        SELECT table_name, COUNT(*) as row_count, MAX(created_at) as last_updated 
        FROM whatsapp_custom_data 
        WHERE user_id = ? 
        GROUP BY table_name
    ", [$uid]);
}
?>

<div class="page-header">
  <div>
      <h1><?= $viewTable ? "Data Browser: " . htmlspecialchars($viewTable) : "Dynamic Data Hub" ?></h1>
      <div class="subtitle"><?= $viewTable ? "Manage specific rows for this dataset" : "Build flexible business databases for your chatbot" ?></div>
  </div>
  <div style="display:flex; gap:12px">
    <?php if ($viewTable): ?>
        <a href="whatsapp-data.php" class="btn btn-muted"><i class="fa-solid fa-arrow-left"></i> Back</a>
        <button class="btn btn-primary" onclick="openRowModal()"><i class="fa-solid fa-plus"></i> Add Entry</button>
    <?php else: ?>
        <button class="btn btn-muted" onclick="openModal('schemaModal')"><i class="fa-solid fa-plus"></i> New Table</button>
        <button class="btn btn-primary" onclick="openModal('uploadModal')"><i class="fa-solid fa-cloud-upload"></i> Import</button>
    <?php endif; ?>
  </div>
</div>

<?php if ($viewTable): ?>
    <div class="card overflow-hidden">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <?php foreach ($schema as $col): ?><th><?= htmlspecialchars($col['name']) ?></th><?php endforeach; ?>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): $val = json_decode($r['data_value'], true); ?>
                        <tr>
                            <?php foreach ($schema as $col): ?>
                                <td>
                                    <?php if ($col['type'] === 'image' && !empty($val[$col['name']])): ?>
                                        <img src="<?= htmlspecialchars($val[$col['name']]) ?>" style="width:36px; height:36px; border-radius:8px; object-fit:cover;">
                                    <?php else: ?>
                                        <?= htmlspecialchars($val[$col['name']] ?? '-') ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="text-end">
                                <button class="btn btn-sm btn-icon" onclick='openRowModal(<?= json_encode($r) ?>)'><i class="fa-solid fa-edit"></i></button>
                                <a href="?delete_row=<?= $r['id'] ?>&table=<?= urlencode($viewTable) ?>" class="btn btn-sm btn-icon text-danger"><i class="fa-solid fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-24">
        <?php foreach ($tables as $t): ?>
            <div class="card p-24">
                <h3><?= htmlspecialchars($t['table_name']) ?></h3>
                <div class="text-muted mb-15"><?= number_format($t['row_count']) ?> Rows</div>
                <div style="display:flex; justify-content:space-between">
                    <a href="?view=<?= urlencode($t['table_name']) ?>" class="btn btn-sm btn-muted">View Data</a>
                    <a href="?delete_table=<?= urlencode($t['table_name']) ?>" class="btn btn-sm btn-icon text-danger"><i class="fa-solid fa-trash"></i></a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Modals (Schema, Upload, Row) - Reused from client/whatsapp-data.php -->
<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
<script>
// JS logic reused from client/whatsapp-data.php
</script>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
