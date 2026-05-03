<?php
try {
$pageTitle = 'WhatsApp Bulk Messaging';
$breadcrumb = [['label'=>'WhatsApp'],['label'=>'Bulk Messaging']];
require_once __DIR__ . '/layout.php';

$uid = $user['id'];
$accounts = DB::query("SELECT * FROM whatsapp_accounts WHERE user_id = ? AND status = 'active'", [$uid]) ?: [];
$selectedAccountId = isset($_POST['account_id']) ? (int)$_POST['account_id'] : ($accounts[0]['id'] ?? 0);
$account = array_filter($accounts, function($a) use ($selectedAccountId) { return $a['id'] == $selectedAccountId; });
$account = reset($account) ?: ($accounts[0] ?? null);

$groups = DB::query("SELECT g.*, COUNT(c.id) as cnt FROM whatsapp_contact_groups g LEFT JOIN whatsapp_contacts c ON c.group_id = g.id WHERE g.user_id = ? GROUP BY g.id ORDER BY g.name", [$uid]) ?: [];

// Handle Sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_whatsapp'])) {
    if (!csrf_verify()) {
        flash_set('danger', 'Invalid security token.');
    } elseif (!$account) {
        flash_set('danger', 'Please connect and activate your WhatsApp account first.');
    } else {
        $msgTemplate = $_POST['message'] ?? '';
        $mediaUrl = sanitize($_POST['media_url'] ?? '');
        $csvData = $_POST['csv_data'] ?? '';
        $recipientsStr = $_POST['recipients'] ?? '';

        // Handle Media File Upload
        if (!empty($_FILES['media_file']['name'])) {
            $targetDir = __DIR__ . '/../uploads/whatsapp/';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            $fileName = time() . '_' . preg_replace("/[^a-zA-Z0-9\._-]/", "_", basename($_FILES['media_file']['name']));
            $targetPath = $targetDir . $fileName;
            $fileType = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
            $allowTypes = ['jpg', 'png', 'jpeg', 'gif', 'pdf', 'mp4', 'webp'];
            if (in_array($fileType, $allowTypes)) {
                if (move_uploaded_file($_FILES['media_file']['tmp_name'], $targetPath)) {
                    $mediaUrl = rtrim(SITE_URL, '/') . '/uploads/whatsapp/' . $fileName;
                }
            }
        }
        
        $contacts = [];
        $headers = [];

        if (!empty($csvData)) {
            $handle = fopen('php://temp', 'r+');
            fwrite($handle, $csvData);
            rewind($handle);
            $headers = fgetcsv($handle);
            if ($headers) {
                $headers = array_map(function($h) { return strtolower(trim($h)); }, $headers);
                $phoneIdx = array_search('phone', $headers);
                if ($phoneIdx !== false) {
                    while (($row = fgetcsv($handle)) !== false) {
                        $phone = trim($row[$phoneIdx] ?? '');
                        if ($phone) {
                            $data = [];
                            foreach ($headers as $idx => $h) { $data[$h] = trim($row[$idx] ?? ''); }
                            $contacts[] = ['phone' => $phone, 'data' => $data];
                        }
                    }
                }
            }
            fclose($handle);
        } elseif (!empty($_POST['group_id'])) {
            $groupId = (int)$_POST['group_id'];
            $groupRows = DB::query("SELECT phone, name, email FROM whatsapp_contacts WHERE group_id = ? AND user_id = ?", [$groupId, $uid]) ?: [];
            foreach ($groupRows as $gr) {
                $contacts[] = ['phone' => $gr['phone'], 'data' => ['name' => $gr['name'], 'email' => $gr['email']]];
            }
        } else {
            $rawRecipients = explode("\n", str_replace("\r", "", $recipientsStr));
            foreach ($rawRecipients as $num) {
                $num = trim($num);
                if ($num) $contacts[] = ['phone' => $num, 'data' => []];
            }
        }

        if (empty($contacts)) {
            flash_set('danger', 'No valid recipients found.');
        } else {
            $count = 0;
            $rate = (float)($user['whatsapp_rate'] ?? 1.00);
            $totalCost = count($contacts) * $rate;
            
            if ($user['whatsapp_balance'] < $totalCost) {
                flash_set('danger', "Insufficient balance. This campaign requires KES " . number_format($totalCost, 2) . " but you only have KES " . number_format($user['whatsapp_balance'], 2));
            } else {
                require_once __DIR__ . '/../includes/gateways/whatsapp.php';
                $gateway = new WhatsApp_Gateway($account['instance_id'], $account['token'], $account['id']);

                foreach ($contacts as $contact) {
                    $number = $contact['phone'];
                    $data = $contact['data'] ?: [];
                    
                    // Replace Placeholders
                    $personalizedMsg = $msgTemplate;
                    foreach ($data as $key => $val) {
                        $label = ucfirst($key);
                        $personalizedMsg = str_replace('##' . $label . '##', $val, $personalizedMsg);
                    }

                    // Send via Gateway
                    $res = $gateway->sendMessage($number, $personalizedMsg, $mediaUrl);
                    
                    // Log Message
                    $status = $res['success'] ? 'sent' : 'failed';
                    $msgId = DB::insert("
                        INSERT INTO whatsapp_messages (user_id, account_id, recipient, message, media_url, status, external_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ", [$uid, $account['id'], $number, $personalizedMsg, $mediaUrl, $status, $res['message_id'] ?? null]);

                    if ($res['success']) {
                        $count++;
                    }
                }

                // Deduct balance
                DB::execute("UPDATE users SET whatsapp_balance = whatsapp_balance - ? WHERE id = ?", [$totalCost, $uid]);
                
                flash_set('success', "Campaign completed! $count messages sent. Cost: KES " . number_format($totalCost, 2));
                header("Location: whatsapp-logs.php");
                exit;
            }
        }
    }
}
?>

<div class="page-header">
  <div><h1>Bulk WhatsApp</h1><div class="subtitle">Broadcast high-impact personalized messages to your customers</div></div>
</div>

<?php if (empty($accounts)): ?>
<div class="alert alert-warning mb-24">
    <div style="display:flex; gap:15px; align-items:center">
        <i class="fa-solid fa-triangle-exclamation" style="font-size:24px"></i>
        <div>
            <strong>Action Required:</strong> No active WhatsApp accounts found. 
            <a href="whatsapp-connect.php" style="color:inherit; text-decoration:underline">Connect an account</a> to enable bulk messaging.
        </div>
    </div>
</div>
<?php endif; ?>

<form method="POST" id="whatsappBulkForm" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="csv_data" id="csvDataInput">
    <input type="hidden" name="send_whatsapp" value="1">

    <div style="display:grid; grid-template-columns: 1fr 380px; gap:24px; align-items:start">
        <div style="display:flex; flex-direction:column; gap:24px">
            <div class="card">
                <div class="card-header" style="display:flex; justify-content:space-between; align-items:center">
                    <h3 class="card-title"><i class="fa-solid fa-pen-to-square" style="color:var(--primary)"></i> 1. Compose Campaign</h3>
                    <div class="tabs" style="margin:0">
                        <button type="button" class="tab-btn active" onclick="switchInputMode('manual', this)">Manual</button>
                        <button type="button" class="tab-btn" onclick="switchInputMode('group', this)">Saved Groups</button>
                        <button type="button" class="tab-btn" onclick="switchInputMode('file', this)">File Upload</button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="manualInputSection">
                        <div class="form-group mb-16">
                            <label class="form-label">Recipients (One per line)</label>
                            <textarea name="recipients" id="recipientsArea" class="form-control" rows="8" placeholder="254712345678&#10;254787654321"></textarea>
                        </div>
                    </div>
                    <div id="groupInputSection" style="display:none">
                        <div class="form-group mb-16">
                            <label class="form-label">Select Contact Group</label>
                            <select name="group_id" id="groupSelect" class="form-control" onchange="updateGroupRecipientCount(this)">
                                <option value="">-- Choose a Group --</option>
                                <?php foreach ($groups as $g): ?>
                                    <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div id="fileInputSection" style="display:none">
                        <div class="alert alert-info mb-16" style="font-size:12px">
                            <i class="fa-solid fa-info-circle"></i> Headers become placeholders. First column must be <strong>phone</strong>.
                        </div>
                    </div>
                    <div class="form-group mb-16">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px">
                            <label class="form-label" style="margin:0">Message Content</label>
                            <div class="dropdown" id="phDropdown" style="display:none">
                                <button type="button" class="btn btn-sm btn-muted" style="font-size:11px" onclick="togglePlaceholders()">
                                    <i class="fa-solid fa-plus-circle" style="color:var(--primary)"></i> Insert Placeholder <i class="fa-solid fa-caret-down"></i>
                                </button>
                                <div id="phMenu" class="dropdown-menu" style="right:0; top:100%; min-width:150px"></div>
                            </div>
                        </div>
                        <textarea name="message" id="whatsappMsg" class="form-control" rows="6" placeholder="Type your message here..." required></textarea>
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label">Media Attachment (Optional)</label>
                        <div class="tabs tabs-sm mb-12" style="background:var(--bg-muted); padding:4px; border-radius:8px">
                            <button type="button" class="tab-btn active" style="padding:6px 12px; font-size:11px" onclick="switchMediaMode('url', this)">External URL</button>
                            <button type="button" class="tab-btn" style="padding:6px 12px; font-size:11px" onclick="switchMediaMode('upload', this)">Upload File</button>
                        </div>
                        <div id="mediaUrlGroup">
                            <input type="url" name="media_url" class="form-control" placeholder="https://example.com/image.jpg">
                        </div>
                        <div id="mediaUploadGroup" style="display:none">
                            <div class="upload-zone upload-zone-sm" onclick="document.getElementById('mediaFileInput').click()" style="padding:15px">
                                <i class="fa-solid fa-file-arrow-up" style="font-size:20px; color:var(--text-muted)"></i>
                                <div id="mfn" style="font-size:12px; font-weight:600">Click to upload media</div>
                            </div>
                            <input type="file" name="media_file" id="mediaFileInput" accept=".jpg,.jpeg,.png,.gif,.pdf,.mp4,.webp" style="display:none" onchange="handleMediaSelect(this)">
                        </div>
                    </div>
                </div>
            </div>
            <div class="card" id="previewCard" style="display:none">
                <div class="card-header"><h3 class="card-title">File Preview</h3></div>
                <div class="card-body" style="padding:0; overflow-x:auto">
                    <table class="data-table" id="previewTable">
                        <thead><tr id="previewHead"></tr></thead>
                        <tbody id="previewBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
        <div style="display:flex; flex-direction:column; gap:24px">
            <div id="fileUploadCard" class="card" style="display:none">
                <div class="card-header"><h3 class="card-title">2. Upload File</h3></div>
                <div class="card-body">
                    <div class="upload-zone" id="dz" onclick="document.getElementById('fileInput').click()">
                        <i class="fa-solid fa-cloud-arrow-up upload-icon"></i>
                        <div style="font-size:13px; font-weight:600">Drop CSV or Excel file here</div>
                        <div id="fn" style="margin-top:10px; font-size:12px; color:var(--primary)"></div>
                    </div>
                    <input type="file" id="fileInput" accept=".csv,.xls,.xlsx" style="display:none" onchange="handleFileSelect(this)">
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h3 class="card-title">Launch Campaign</h3></div>
                <div class="card-body">
                    <div class="form-group mb-20">
                        <label class="form-label">Send From Account</label>
                        <select name="account_id" class="form-control" required>
                            <?php foreach ($accounts as $acc): ?>
                                <option value="<?= $acc['id'] ?>" <?= ($acc['id'] == $selectedAccountId) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($acc['account_name'] ?: $acc['phone_number']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="background:var(--bg-muted); padding:15px; border-radius:12px; margin-bottom:20px">
                        <div style="display:flex; justify-content:space-between; margin-bottom:8px">
                            <span class="text-muted" style="font-size:12px">Recipients:</span>
                            <span id="recipientCount" style="font-weight:700">0</span>
                        </div>
                        <div style="display:flex; justify-content:space-between">
                            <span class="text-muted" style="font-size:12px">Account Balance:</span>
                            <span style="font-weight:700; color:var(--success)">KES <?= number_format($user['whatsapp_balance'] ?? 0, 2) ?></span>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg btn-full" <?= empty($accounts) ? 'disabled' : '' ?>>
                        <i class="fa-solid fa-paper-plane"></i> Launch Campaign
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
let parsedRows = []; let headers = []; let inputMode = 'manual';
function switchInputMode(mode, btn) {
    inputMode = mode;
    btn.parentElement.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('manualInputSection').style.display = mode === 'manual' ? 'block' : 'none';
    document.getElementById('groupInputSection').style.display = mode === 'group' ? 'block' : 'none';
    document.getElementById('fileInputSection').style.display = mode === 'file' ? 'block' : 'none';
    document.getElementById('fileUploadCard').style.display = mode === 'file' ? 'block' : 'none';
    document.getElementById('phDropdown').style.display = (mode === 'file' || mode === 'group') ? 'block' : 'none';
    if (mode === 'group') {
        document.getElementById('phMenu').innerHTML = '<div class="dropdown-item" onclick="insertPH(\'##Name##\')">Name</div><div class="dropdown-item" onclick="insertPH(\'##Email##\')">Email</div>';
    }
    updateRecipientCount();
}
function updateGroupRecipientCount(select) {
    if (inputMode !== 'group') return;
    const groups = <?= json_encode($groups) ?>;
    const group = groups.find(g => g.id == select.value);
    document.getElementById('recipientCount').textContent = group ? (group.cnt || 0) : '0';
}
function switchMediaMode(mode, btn) {
    btn.parentElement.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('mediaUrlGroup').style.display = mode === 'url' ? 'block' : 'none';
    document.getElementById('mediaUploadGroup').style.display = mode === 'upload' ? 'block' : 'none';
}
function handleMediaSelect(input) {
    const file = input.files[0];
    if (file) document.getElementById('mfn').innerHTML = `<i class="fa-solid fa-check-circle" style="color:var(--success)"></i> ${file.name}`;
}
function handleFileSelect(input) {
    const file = input.files[0]; if (!file) return;
    document.getElementById('fn').innerHTML = `<i class="fa-solid fa-file-check"></i> ${file.name}`;
    const reader = new FileReader(); const ext = file.name.split('.').pop().toLowerCase();
    reader.onload = function(e) {
        const data = e.target.result; let workbook = XLSX.read(data, { type: ext === 'csv' ? 'string' : 'array' });
        const worksheet = workbook.Sheets[workbook.SheetNames[0]];
        const json = XLSX.utils.sheet_to_json(worksheet, { header: 1 });
        if (json.length < 2) return;
        headers = json[0].map(h => String(h || '').trim().toLowerCase());
        const phoneIdx = headers.findIndex(h => h.includes('phone') || h.includes('number'));
        if (phoneIdx === -1) return alert('Phone column not found');
        parsedRows = json.slice(1).filter(r => r[phoneIdx]);
        renderPreview(); updateRecipientCount(); populatePlaceholders();
    };
    if (ext === 'csv') reader.readAsText(file); else reader.readAsArrayBuffer(file);
}
function renderPreview() {
    document.getElementById('previewHead').innerHTML = headers.map(h => `<th>${h}</th>`).join('');
    document.getElementById('previewBody').innerHTML = parsedRows.slice(0, 5).map(row => `<tr>${headers.map((_, i) => `<td>${row[i] || ''}</td>`).join('')}</tr>`).join('');
    document.getElementById('previewCard').style.display = 'block';
}
function populatePlaceholders() {
    document.getElementById('phMenu').innerHTML = headers.map(h => `<div class="dropdown-item" onclick="insertPH('##${h.charAt(0).toUpperCase() + h.slice(1)}##')">${h}</div>`).join('');
}
function insertPH(ph) {
    const ta = document.getElementById('whatsappMsg'); const start = ta.selectionStart;
    ta.value = ta.value.substring(0, start) + ph + ta.value.substring(ta.selectionEnd);
    ta.focus(); togglePlaceholders();
}
function togglePlaceholders() { document.getElementById('phMenu').classList.toggle('show'); }
function updateRecipientCount() {
    const countEl = document.getElementById('recipientCount');
    if (inputMode === 'manual') countEl.textContent = document.getElementById('recipientsArea').value.split('\n').filter(l => l.trim()).length;
    else countEl.textContent = parsedRows.length;
}
document.getElementById('recipientsArea').addEventListener('input', updateRecipientCount);
document.getElementById('whatsappBulkForm').addEventListener('submit', function(e) {
    if (inputMode === 'file') {
        const csvContent = [headers, ...parsedRows].map(row => row.map(cell => `"${String(cell || '').replace(/"/g, '""')}"`).join(',')).join('\n');
        document.getElementById('csvDataInput').value = csvContent;
    }
});
window.onclick = function(e) { if (!e.target.closest('#phDropdown')) document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('show')); }
</script>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
<?php
} catch (Throwable $e) {
    echo "<div style='padding:20px; border:2px solid red; background:#fff1f1; color:red; font-family:monospace; margin:20px; border-radius:10px;'>";
    echo "<h3>⚠️ PHP Execution Error</h3><b>Message:</b> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>
