<?php
try {
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
        $data = json_decode($_POST['data'], true) ?: [];
        $keyColumn = $_POST['key_column'];

        if (empty($tableName) || empty($data) || empty($keyColumn)) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }

        // Save Schema automatically
        $columns = array_keys($data[0] ?? []);
        $schema = array_map(function($c) {
            $type = (stripos($c, 'image') !== false || stripos($c, 'picture') !== false) ? 'image' : 'text';
            return ['name' => $c, 'type' => $type];
        }, $columns);
        DB::execute("INSERT INTO whatsapp_data_schemas (user_id, table_name, columns) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE columns = VALUES(columns)", [$uid, $tableName, json_encode($schema)]);

        // Overwrite data
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
        $rowData = (array)($_POST['row_data'] ?? []);

        // Handle Image Uploads
        if (!empty($_FILES['images'])) {
            foreach ($_FILES['images']['tmp_name'] as $fieldName => $tmpName) {
                if ($tmpName) {
                    $ext = pathinfo($_FILES['images']['name'][$fieldName], PATHINFO_EXTENSION);
                    $fileName = 'data_' . uniqid() . '.' . $ext;
                    $uploadPath = __DIR__ . '/../uploads/whatsapp/data/' . $fileName;
                    
                    // Create dir if missing
                    if (!is_dir(dirname($uploadPath))) mkdir(dirname($uploadPath), 0755, true);
                    
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
        $columns = json_decode($_POST['columns'], true) ?: [];
        
        if (empty($tableName) || empty($columns)) {
            echo json_encode(['success' => false, 'message' => 'Table name and columns are required']);
            exit;
        }

        DB::execute("INSERT INTO whatsapp_data_schemas (user_id, table_name, columns) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE columns = VALUES(columns)", [$uid, $tableName, json_encode($columns)]);
        echo json_encode(['success' => true]);
        exit;
    }
}

// Handle Row Deletion
if (isset($_GET['delete_row'])) {
    $id = (int)$_GET['delete_row'];
    DB::execute("DELETE FROM whatsapp_custom_data WHERE id = ? AND user_id = ?", [$id, $uid]);
    flash_set('success', 'Row deleted.');
    header("Location: whatsapp-data.php?view=" . urlencode($_GET['table']));
    exit;
}

// Handle Table Deletion
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
    $rows = DB::query("SELECT * FROM whatsapp_custom_data WHERE user_id = ? AND table_name = ? ORDER BY id DESC", [$uid, $viewTable]) ?: [];
} else {
    $tables = DB::query("
        SELECT table_name, COUNT(*) as row_count, MAX(created_at) as last_updated 
        FROM whatsapp_custom_data 
        WHERE user_id = ? 
        GROUP BY table_name
    ", [$uid]) ?: [];
}
?>

<div class="page-header">
  <div>
      <h1><?= $viewTable ? "Data Browser: " . htmlspecialchars($viewTable) : "Dynamic Data Hub" ?></h1>
      <div class="subtitle"><?= $viewTable ? "Manage specific rows for this dataset" : "Build flexible business databases for your chatbot" ?></div>
  </div>
  <div style="display:flex; gap:12px">
    <?php if ($viewTable): ?>
        <a href="whatsapp-data.php" class="btn btn-muted"><i class="fa-solid fa-arrow-left"></i> Back to Hub</a>
        <button class="btn btn-primary" onclick="openRowModal()"><i class="fa-solid fa-plus"></i> Add New Entry</button>
    <?php else: ?>
        <button class="btn btn-muted" onclick="openModal('schemaModal')">
            <i class="fa-solid fa-plus"></i> Create Manual Table
        </button>
        <button class="btn btn-primary" onclick="openModal('uploadModal')">
            <i class="fa-solid fa-cloud-upload"></i> Import from Excel/CSV
        </button>
    <?php endif; ?>
  </div>
</div>

<?php if ($viewTable): ?>
    <div class="card overflow-hidden">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <?php foreach ((array)$schema as $col): ?>
                            <th><?= htmlspecialchars($col['name']) ?></th>
                        <?php endforeach; ?>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="<?= count((array)$schema) + 1 ?>" class="text-center p-40">No data found in this table.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): $val = json_decode($r['data_value'], true) ?: []; ?>
                        <tr>
                            <?php foreach ((array)$schema as $col): ?>
                                <td>
                                    <?php if ($col['type'] === 'image' && !empty($val[$col['name']])): ?>
                                        <div style="display:flex; align-items:center; gap:10px">
                                            <img src="<?= htmlspecialchars($val[$col['name']]) ?>" style="width:36px; height:36px; border-radius:8px; object-fit:cover; border:1px solid var(--border)">
                                        </div>
                                    <?php else: ?>
                                        <span style="font-weight:500"><?= htmlspecialchars($val[$col['name']] ?? '-') ?></span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="text-end">
                                <div style="display:flex; gap:8px; justify-content:flex-end">
                                    <button class="btn btn-sm btn-icon" onclick='openRowModal(<?= json_encode($r) ?>)' title="Edit Entry"><i class="fa-solid fa-edit"></i></button>
                                    <a href="?delete_row=<?= $r['id'] ?>&table=<?= urlencode($viewTable) ?>" class="btn btn-sm btn-icon text-danger" onclick="return confirm('Delete this row?')" title="Delete Entry"><i class="fa-solid fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-24">
        <?php if (empty($tables)): ?>
            <div class="card col-span-full" style="padding: 120px 40px; text-align: center; background: linear-gradient(145deg, var(--bg-card), var(--bg-muted)); border: 2px dashed var(--border); border-radius: 24px">
                <div style="width: 100px; height: 100px; background: rgba(0,200,150,0.1); color: var(--primary); border-radius: 30px; display: flex; align-items:center; justify-content:center; font-size:40px; margin: 0 auto 32px auto; box-shadow: 0 10px 30px rgba(0,200,150,0.1)">
                    <i class="fa-solid fa-database"></i>
                </div>
                <h2 style="font-weight: 800; font-size: 28px; margin-bottom: 16px; color: var(--text-main)">Start Your Business Database</h2>
                <p class="text-muted" style="max-width: 460px; margin: 0 auto 40px auto; font-size: 16px; line-height: 1.6">Build a flexible database for your products, clients, or orders. Start from a blank slate or import your existing datasets in seconds.</p>
                <div style="display:flex; gap:20px; justify-content:center">
                    <button class="btn btn-primary btn-lg" onclick="openModal('schemaModal')" style="padding: 14px 28px">
                        <i class="fa-solid fa-plus"></i> Create Manual Table
                    </button>
                    <button class="btn btn-muted btn-lg" onclick="openModal('uploadModal')" style="padding: 14px 28px">
                        <i class="fa-solid fa-cloud-upload"></i> Import Excel/CSV
                    </button>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($tables as $t): ?>
                <div class="card p-24" style="border-top: 4px solid var(--primary)">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:15px">
                        <div style="background:rgba(0,200,150,0.1); color:var(--primary); width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:18px">
                            <i class="fa-solid fa-table"></i>
                        </div>
                        <div style="display:flex; gap:8px">
                            <a href="?view=<?= urlencode($t['table_name']) ?>" class="btn btn-sm btn-icon" title="View Data"><i class="fa-solid fa-eye"></i></a>
                            <a href="?delete_table=<?= urlencode($t['table_name']) ?>" class="btn btn-sm btn-icon text-danger" onclick="return confirm('Delete this entire data table? This cannot be undone.')" title="Delete Table"><i class="fa-solid fa-trash"></i></a>
                        </div>
                    </div>
                    <h3 style="margin:0 0 5px 0"><?= htmlspecialchars($t['table_name']) ?></h3>
                    <div style="font-size:12px; color:var(--text-muted); margin-bottom:15px">
                        <i class="fa-solid fa-hashtag"></i> <?= number_format($t['row_count']) ?> Rows
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center; padding-top:15px; border-top:1px solid var(--border)">
                        <div style="font-size:11px; color:var(--text-muted)">Updated <?= date('d M, H:i', strtotime($t['last_updated'])) ?></div>
                        <span class="badge badge-success">Sync Ready</span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Schema Creator Modal (Manual Table) -->
<div id="schemaModal" class="modal-overlay">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Create Manual Table</h3>
      <button type="button" class="btn btn-icon" onclick="closeModal('schemaModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body">
        <div class="form-group mb-20">
            <label class="form-label">Table Name</label>
            <input type="text" id="newTableName" class="form-control" placeholder="e.g. Products, Our Clients">
        </div>
        
        <label class="form-label">Define Columns</label>
        <div id="columnBuilder">
            <div class="column-row" style="display:grid; grid-template-columns: 1fr 1fr 40px; gap:10px; margin-bottom:10px">
                <input type="text" class="form-control col-name" placeholder="Column Name (e.g. Price)" value="ID">
                <select class="form-control col-type">
                    <option value="text">Text / Number</option>
                    <option value="image">Image / Picture</option>
                </select>
                <div style="width:40px"></div>
            </div>
        </div>
        <button type="button" class="btn btn-sm btn-muted" onclick="addColumnRow()"><i class="fa-solid fa-plus"></i> Add Column</button>
        <div class="form-hint mt-10">The first column will be used as the unique ID for chatbot lookups.</div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-muted flex-1" onclick="closeModal('schemaModal')">Cancel</button>
        <button type="button" id="createTableBtn" class="btn btn-primary flex-1">Create Table</button>
    </div>
  </div>
</div>

<!-- Upload Modal -->
<div id="uploadModal" class="modal-overlay">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Import Data Table</h3>
      <button type="button" class="btn btn-icon" onclick="closeModal('uploadModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="modal-body">
        <div class="form-group mb-20">
            <label class="form-label">Table Name</label>
            <input type="text" id="uploadTableName" class="form-control" placeholder="e.g. Products, Clients, Orders">
        </div>
        
        <div id="dropZone" style="border: 2px dashed var(--border); border-radius:16px; padding:40px; text-align:center; transition:all 0.3s; cursor:pointer" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border)'">
            <i class="fa-solid fa-file-excel" style="font-size:40px; color:var(--primary); margin-bottom:15px"></i>
            <h4 style="margin:0">Drop Excel or CSV here</h4>
            <p class="text-muted" style="font-size:12px">or click to browse files</p>
            <input type="file" id="fileInput" accept=".csv, .xlsx, .xls" style="display:none">
        </div>

        <div id="uploadPreview" style="display:none; margin-top:20px">
            <label class="form-label">Select Unique Lookup ID (e.g. Order ID, Product Code)</label>
            <select id="keyColumnSelect" class="form-control mb-10"></select>
            <div class="form-hint">This is what customers will type to find this record.</div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-muted flex-1" onclick="closeModal('uploadModal')">Cancel</button>
        <button type="button" id="startUploadBtn" class="btn btn-primary flex-1" disabled>Import & Synchronize</button>
    </div>
  </div>
</div>

<!-- Row Editor Modal -->
<div id="rowModal" class="modal-overlay">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title" id="rowModalTitle">Add New Row</h3>
      <button type="button" class="btn btn-icon" onclick="closeModal('rowModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form id="rowForm" enctype="multipart/form-data">
        <div class="modal-body" id="rowFormBody">
            <!-- Dynamic fields based on schema -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-muted flex-1" onclick="closeModal('rowModal')">Cancel</button>
            <button type="submit" id="saveRowBtn" class="btn btn-primary flex-1">Save Entry</button>
        </div>
    </form>
  </div>
</div>

<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
<script>
let parsedData = null;
const allSchema = <?= json_encode($schema ?? []) ?>;
const currentSchema = Array.isArray(allSchema) ? allSchema : [];
const currentTableName = "<?= $viewTable ?>";

function openRowModal(row = null) {
    const body = document.getElementById('rowFormBody');
    body.innerHTML = '';
    document.getElementById('rowModalTitle').textContent = row ? 'Edit Entry' : 'Add New Entry';
    
    body.innerHTML += `<input type="hidden" name="action" value="save_row">`;
    body.innerHTML += `<input type="hidden" name="table_name" value="${currentTableName}">`;
    body.innerHTML += `<input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">`;
    if (row) body.innerHTML += `<input type="hidden" name="row_id" value="${row.id}">`;

    const rowVal = row ? JSON.parse(row.data_value) : {};

    currentSchema.forEach(col => {
        const val = rowVal[col.name] || '';
        const isImage = col.type === 'image';
        
        const fieldHtml = `
            <div class="form-group mb-15">
                <label class="form-label">${col.name}</label>
                ${isImage 
                    ? `<input type="file" name="images[${col.name}]" class="form-control" accept="image/*">
                       ${val ? `<div class="mt-5"><img src="${val}" style="height:60px; border-radius:8px; border:1px solid var(--border)"></div>` : ''}
                       <input type="hidden" name="row_data[${col.name}]" value="${val}">`
                    : `<input type="text" name="row_data[${col.name}]" value="${val}" class="form-control" placeholder="Enter ${col.name}" required>`
                }
            </div>
        `;
        body.innerHTML += fieldHtml;
    });

    body.innerHTML += `<input type="hidden" name="key_column" value="${currentSchema.length > 0 ? currentSchema[0].name : ''}">`;
    openModal('rowModal');
}

document.getElementById('rowForm').onsubmit = async function(e) {
    e.preventDefault();
    const btn = document.getElementById('saveRowBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

    const formData = new FormData(this);
    try {
        const res = await fetch('whatsapp-data.php', { method: 'POST', body: formData });
        const result = await res.json();
        if (result.success) {
            location.reload();
        } else {
            alert(result.message);
            btn.disabled = false;
            btn.innerHTML = 'Save Entry';
        }
    } catch (err) {
        alert('An error occurred while saving.');
        btn.disabled = false;
    }
};

// Manual Table Creation
function addColumnRow() {
    const row = document.createElement('div');
    row.className = 'column-row';
    row.style = 'display:grid; grid-template-columns: 1fr 1fr 40px; gap:10px; margin-bottom:10px';
    row.innerHTML = `
        <input type="text" class="form-control col-name" placeholder="Column Name">
        <select class="form-control col-type">
            <option value="text">Text / Number</option>
            <option value="image">Image / Picture</option>
        </select>
        <button type="button" class="btn btn-sm text-danger" onclick="this.parentElement.remove()"><i class="fa-solid fa-trash"></i></button>
    `;
    document.getElementById('columnBuilder').appendChild(row);
}

document.getElementById('createTableBtn')?.addEventListener('click', async function() {
    const tableName = document.getElementById('newTableName').value.trim();
    if (!tableName) return alert('Please enter a table name');

    const columnRows = document.querySelectorAll('.column-row');
    const columns = [];
    columnRows.forEach(row => {
        const name = row.querySelector('.col-name').value.trim();
        const type = row.querySelector('.col-type').value;
        if (name) columns.push({ name, type });
    });

    if (columns.length === 0) return alert('Please add at least one column');

    this.disabled = true;
    this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Creating...';

    const formData = new FormData();
    formData.append('action', 'create_table');
    formData.append('table_name', tableName);
    formData.append('columns', JSON.stringify(columns));
    formData.append('csrf_token', '<?= csrf_token() ?>');

    try {
        const res = await fetch('whatsapp-data.php', { method: 'POST', body: formData });
        const result = await res.json();
        if (result.success) location.href = `whatsapp-data.php?view=${encodeURIComponent(tableName)}`;
        else {
            alert(result.message);
            this.disabled = false;
            this.innerHTML = 'Create Table';
        }
    } catch (e) {
        alert('Failed to create table.');
        this.disabled = false;
    }
});

// Bulk Upload Logic
document.getElementById('dropZone')?.addEventListener('click', () => document.getElementById('fileInput').click());
document.getElementById('fileInput')?.addEventListener('change', (e) => handleFile(e.target.files[0]));

function handleFile(file) {
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (e) => {
        const data = new Uint8Array(e.target.result);
        const workbook = XLSX.read(data, {type: 'array'});
        const sheet = workbook.Sheets[workbook.SheetNames[0]];
        parsedData = XLSX.utils.sheet_to_json(sheet);
        
        if (parsedData.length > 0) {
            const columns = Object.keys(parsedData[0]);
            const select = document.getElementById('keyColumnSelect');
            select.innerHTML = columns.map(c => `<option value="${c}">${c}</option>`).join('');
            document.getElementById('uploadPreview').style.display = 'block';
            document.getElementById('startUploadBtn').disabled = false;
        }
    };
    reader.readAsArrayBuffer(file);
}

document.getElementById('startUploadBtn')?.addEventListener('click', async function() {
    const tableName = document.getElementById('uploadTableName').value.trim();
    const keyColumn = document.getElementById('keyColumnSelect').value;
    
    if (!tableName) return alert('Please enter a table name');

    this.disabled = true;
    this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Importing...';

    const formData = new FormData();
    formData.append('action', 'upload_data');
    formData.append('table_name', tableName);
    formData.append('key_column', keyColumn);
    formData.append('data', JSON.stringify(parsedData));
    formData.append('csrf_token', '<?= csrf_token() ?>');

    try {
        const res = await fetch('whatsapp-data.php', { method: 'POST', body: formData });
        const result = await res.json();
        if (result.success) location.reload();
        else {
            alert(result.message);
            this.disabled = false;
            this.innerHTML = 'Import & Synchronize';
        }
    } catch (e) {
        alert('Upload failed.');
        this.disabled = false;
    }
});
</script>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
<?php
} catch (Throwable $e) {
    echo "<div style='padding:20px; border:2px solid red; background:#fff1f1; color:red; font-family:monospace; margin:20px; border-radius:10px; z-index:9999; position:relative;'>";
    echo "<h3>⚠️ PHP Execution Error Caught</h3>";
    echo "<b>Message:</b> " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "<b>File:</b> " . htmlspecialchars($e->getFile()) . "<br>";
    echo "<b>Line:</b> " . $e->getLine() . "<br>";
    echo "<hr><b>Trace:</b> <pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}
?>
