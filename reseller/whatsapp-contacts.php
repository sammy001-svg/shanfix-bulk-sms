<?php
try {
$pageTitle = 'WhatsApp Contacts';
$breadcrumb = [['label'=>'WhatsApp'],['label'=>'Contacts & Groups']];
require_once __DIR__ . '/layout.php';

$uid     = $user['id'];
$groupId = (int)($_GET['group']??0);
$search  = sanitize($_GET['q']??'');
$page    = max(1,(int)($_GET['page']??1));
$perPage = 20; $offset=($page-1)*$perPage;

// Fetch Groups
$groups  = DB::query("SELECT g.*, COUNT(c.id) as cnt FROM whatsapp_contact_groups g LEFT JOIN whatsapp_contacts c ON c.group_id=g.id WHERE g.user_id=? GROUP BY g.id ORDER BY g.name", [$uid]) ?: [];

// Fetch Contacts with Filters
$where='WHERE c.user_id=?'; $params=[$uid];
if ($groupId) { $where.=' AND c.group_id=?'; $params[]=$groupId; }
if ($search)  { $where.=' AND (c.phone LIKE ? OR c.name LIKE ?)'; $params[]="%$search%"; $params[]="%$search%"; }

$totalCountRow = DB::queryOne("SELECT COUNT(*) as c FROM whatsapp_contacts c $where", $params);
$total = (int)($totalCountRow['c'] ?? 0);
$contacts = DB::query("SELECT c.*, g.name as group_name FROM whatsapp_contacts c LEFT JOIN whatsapp_contact_groups g ON g.id=c.group_id $where ORDER BY c.created_at DESC LIMIT $perPage OFFSET $offset", $params) ?: [];
$totalPages = ceil($total/$perPage);
?>

<div class="page-header">
  <div><h1>WhatsApp Contacts</h1><div class="subtitle">Manage your WhatsApp-specific contact lists and groups</div></div>
  <div class="btn-group">
    <button class="btn btn-secondary" onclick="openModal('importModal')"><i class="fa-solid fa-upload"></i> Import</button>
    <button class="btn btn-primary" onclick="openModal('addContactModal')"><i class="fa-solid fa-plus"></i> Add Contact</button>
  </div>
</div>

<div style="display:grid; grid-template-columns: 240px 1fr; gap:24px; align-items:start">
  <!-- Groups Sidebar -->
  <div class="card">
    <div class="card-header" style="padding:15px 20px"><h4 style="margin:0; font-size:14px">Contact Groups</h4></div>
    <div class="card-body" style="padding:8px 0">
      <a href="/reseller/whatsapp-contacts.php" class="nav-link <?= $groupId === 0 ? 'active' : '' ?>" style="padding:10px 20px; color:var(--text-primary)">
        <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
        <span class="nav-text">All Contacts <span class="badge badge-muted ml-auto"><?= $total ?></span></span>
      </a>
      <?php foreach ($groups as $g): ?>
        <a href="?group=<?= $g['id'] ?>" class="nav-link <?= $groupId === $g['id'] ? 'active' : '' ?>" style="padding:10px 20px; color:var(--text-primary)">
          <span class="nav-icon"><i class="fa-solid fa-layer-group"></i></span>
          <span class="nav-text"><?= htmlspecialchars($g['name']) ?> <span class="badge badge-muted ml-auto"><?= $g['cnt'] ?></span></span>
          <button class="btn btn-icon btn-xs text-danger ml-5" onclick="event.preventDefault(); deleteGroup(<?= $g['id'] ?>, '<?= addslashes($g['name']) ?>')"><i class="fa-solid fa-trash-can"></i></button>
        </a>
      <?php endforeach; ?>
      <div style="padding:12px 20px"><button class="btn btn-outline btn-sm btn-full" onclick="openModal('addGroupModal')"><i class="fa-solid fa-plus"></i> New Group</button></div>
    </div>
  </div>

  <!-- Contacts Table -->
  <div class="card">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center">
      <h3 class="card-title">
        <i class="fa-solid fa-address-book" style="color:var(--primary)"></i> 
        <?= $groupId ? htmlspecialchars(array_values(array_filter($groups, function($g) use ($groupId) { return $g['id']===$groupId; }))[0]['name']??'Contacts') : 'All Contacts' ?>
      </h3>
      <form method="GET" style="display:flex; gap:10px">
        <input type="hidden" name="group" value="<?= $groupId ?>">
        <div class="input-group">
          <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
          <input type="text" name="q" class="form-control" placeholder="Search phone or name..." value="<?= htmlspecialchars($search) ?>" style="width:220px">
        </div>
        <button class="btn btn-primary"><i class="fa-solid fa-search"></i></button>
      </form>
    </div>
    <div class="table-wrapper">
      <table class="data-table">
        <thead><tr><th>Phone</th><th>Name</th><th>Group</th><th>Email</th><th>Created</th><th>Actions</th></tr></thead>
        <tbody>
          <?php if (empty($contacts)): ?>
            <tr><td colspan="6" class="text-center" style="padding:60px 0">
              <div style="font-size:40px; color:var(--bg-muted); margin-bottom:15px"><i class="fa-solid fa-user-slash"></i></div>
              <h3 class="text-muted">No contacts found</h3>
              <p class="text-muted">Start adding contacts to your WhatsApp audience</p>
            </td></tr>
          <?php else: foreach ($contacts as $c): ?>
            <tr>
              <td><strong><?= htmlspecialchars($c['phone']) ?></strong></td>
              <td><?= htmlspecialchars($c['name']??'—') ?></td>
              <td><?= $c['group_name'] ? '<span class="badge badge-success">'.htmlspecialchars($c['group_name']).'</span>' : '—' ?></td>
              <td style="font-size:12px; color:var(--text-secondary)"><?= htmlspecialchars($c['email']??'—') ?></td>
              <td style="font-size:12px"><?= date('d M Y', strtotime($c['created_at'])) ?></td>
              <td>
                <div style="display:flex; gap:8px">
                  <button class="btn btn-icon btn-sm" onclick='editContact(<?= htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8') ?>)'><i class="fa-solid fa-edit"></i></button>
                  <button class="btn btn-icon btn-sm text-danger" onclick="deleteContact(<?= $c['id'] ?>)"><i class="fa-solid fa-trash"></i></button>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($totalPages > 1): ?>
      <div class="card-footer" style="display:flex; justify-content:center">
        <div class="pagination">
          <?php for($p=1; $p<=$totalPages; $p++): ?>
            <a href="?page=<?= $p ?>&group=<?= $groupId ?>&q=<?= urlencode($search) ?>" class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
          <?php endfor; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Add Contact Modal -->
<div id="addContactModal" class="modal-overlay">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Add WhatsApp Contact</h3>
      <button type="button" class="btn btn-icon" onclick="closeModal('addContactModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form action="/reseller/actions/whatsapp-actions.php?action=add_contact" method="POST">
        <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="form-group mb-16"><label class="form-label">Phone Number <span class="required">*</span></label><input type="text" name="phone" class="form-control" placeholder="e.g. 254712345678" required></div>
            <div class="form-group mb-16"><label class="form-label">Contact Name</label><input type="text" name="name" class="form-control" placeholder="Full Name"></div>
            <div class="form-group mb-16"><label class="form-label">Email Address</label><input type="email" name="email" class="form-control" placeholder="email@example.com"></div>
            <div class="form-group"><label class="form-label">Assign to Group</label><select name="group_id" class="form-control"><option value="">-- No Group --</option><?php foreach ($groups as $g): ?><option value="<?= $g['id'] ?>" <?= $groupId===$g['id']?'selected':'' ?>><?= htmlspecialchars($g['name']) ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-muted flex-1" onclick="closeModal('addContactModal')">Cancel</button>
            <button type="submit" class="btn btn-primary flex-1">Save Contact</button>
        </div>
    </form>
  </div>
</div>

<!-- Edit Contact Modal -->
<div id="editContactModal" class="modal-overlay">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Edit Contact</h3>
      <button type="button" class="btn btn-icon" onclick="closeModal('editContactModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form action="/reseller/actions/whatsapp-actions.php?action=edit_contact" method="POST">
        <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group mb-16"><label class="form-label">Phone Number <span class="required">*</span></label><input type="text" name="phone" id="edit_phone" class="form-control" required></div>
            <div class="form-group mb-16"><label class="form-label">Contact Name</label><input type="text" name="name" id="edit_name" class="form-control"></div>
            <div class="form-group mb-16"><label class="form-label">Email Address</label><input type="email" name="email" id="edit_email" class="form-control"></div>
            <div class="form-group"><label class="form-label">Assign to Group</label><select name="group_id" id="edit_group" class="form-control"><option value="">-- No Group --</option><?php foreach ($groups as $g): ?><option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-muted flex-1" onclick="closeModal('editContactModal')">Cancel</button>
            <button type="submit" class="btn btn-primary flex-1">Update Contact</button>
        </div>
    </form>
  </div>
</div>

<!-- Add Group Modal -->
<div id="addGroupModal" class="modal-overlay">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title">Create WhatsApp Group</h3>
      <button type="button" class="btn btn-icon" onclick="closeModal('addGroupModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form action="/reseller/actions/whatsapp-actions.php?action=add_group" method="POST">
        <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="form-group"><label class="form-label">Group Name <span class="required">*</span></label><input type="text" name="name" class="form-control" placeholder="e.g. Premium Clients" required></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-muted flex-1" onclick="closeModal('addGroupModal')">Cancel</button>
            <button type="submit" class="btn btn-primary flex-1">Create Group</button>
        </div>
    </form>
  </div>
</div>

<!-- Import Modal -->
<div id="importModal" class="modal-overlay">
  <div class="modal" style="max-width:600px">
    <div class="modal-header">
      <h3 class="modal-title">Import Contacts</h3>
      <button type="button" class="btn btn-icon" onclick="closeModal('importModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form action="/reseller/actions/whatsapp-actions.php?action=import_contacts" method="POST" id="importForm">
        <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="contacts_json" id="contacts_json">
            
            <div class="alert alert-info mb-20" style="font-size:12px">
                <i class="fa-solid fa-info-circle"></i> <strong>Tip:</strong> Your file should have headers like <strong>Phone, Name, Email</strong>. Phone numbers should include the country code.
            </div>

            <div class="form-group mb-16">
                <label class="form-label">Select Group</label>
                <select name="group_id" class="form-control" required>
                    <option value="">-- Select Target Group --</option>
                    <?php foreach ($groups as $g): ?>
                        <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-hint">Imported contacts will be added to this group.</div>
            </div>

            <div class="form-group">
                <label class="form-label">Choose File (CSV, XLSX)</label>
                <div class="upload-zone" id="importZone" onclick="document.getElementById('importFile').click()">
                    <i class="fa-solid fa-file-excel" style="font-size:32px; color:var(--primary); margin-bottom:10px"></i>
                    <p id="fileNameDisplay">Click or drag file here to upload</p>
                    <input type="file" id="importFile" hidden accept=".csv,.xlsx,.xls" onchange="handleImportFile(this)">
                </div>
            </div>
            
            <div id="importPreview" style="display:none; margin-top:15px; max-height:200px; overflow-y:auto; border:1px solid var(--border); border-radius:8px">
                <table class="data-table" style="font-size:11px">
                    <thead id="importHead"></thead>
                    <tbody id="importBody"></tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-muted flex-1" onclick="closeModal('importModal')">Cancel</button>
            <button type="submit" id="submitImport" class="btn btn-primary flex-1" disabled>Start Import</button>
        </div>
    </form>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
let importData = [];

function editContact(c) {
    document.getElementById('edit_id').value = c.id;
    document.getElementById('edit_phone').value = c.phone;
    document.getElementById('edit_name').value = c.name || '';
    document.getElementById('edit_email').value = c.email || '';
    document.getElementById('edit_group').value = c.group_id || '';
    openModal('editContactModal');
}

function deleteContact(id) {
    if (confirm('Are you sure you want to delete this contact?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/reseller/actions/whatsapp-actions.php?action=delete_contact';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = id;
        
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = '<?= csrf_token() ?>';
        
        form.appendChild(idInput);
        form.appendChild(csrfInput);
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteGroup(id, name) {
    if (confirm(`Are you sure you want to delete the group "${name}"? Contacts within this group will remain but will be uncategorized.`)) {
        window.location.href = `/reseller/actions/whatsapp-actions.php?action=delete_group&id=${id}&csrf_token=<?= csrf_token() ?>`;
    }
}

function handleImportFile(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    document.getElementById('fileNameDisplay').textContent = "Selected: " + file.name;
    
    const reader = new FileReader();
    reader.onload = function(e) {
        const data = new Uint8Array(e.target.result);
        const workbook = XLSX.read(data, {type: 'array'});
        const firstSheet = workbook.SheetNames[0];
        const rows = XLSX.utils.sheet_to_json(workbook.Sheets[firstSheet], {defval: ""});
        
        if (rows.length > 0) {
            importData = rows;
            document.getElementById('contacts_json').value = JSON.stringify(rows);
            document.getElementById('submitImport').disabled = false;
            showImportPreview(rows);
        }
    };
    reader.readAsArrayBuffer(file);
}

function showImportPreview(rows) {
    const head = document.getElementById('importHead');
    const body = document.getElementById('importBody');
    const preview = document.getElementById('importPreview');
    
    head.innerHTML = "";
    body.innerHTML = "";
    
    if (rows.length === 0) return;
    
    const headers = Object.keys(rows[0]);
    const hr = document.createElement('tr');
    headers.forEach(h => {
        const th = document.createElement('th');
        th.textContent = h;
        hr.appendChild(th);
    });
    head.appendChild(hr);
    
    rows.slice(0, 5).forEach(row => {
        const tr = document.createElement('tr');
        headers.forEach(h => {
            const td = document.createElement('td');
            td.textContent = row[h];
            tr.appendChild(td);
        });
        body.appendChild(tr);
    });
    
    preview.style.display = 'block';
}
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
