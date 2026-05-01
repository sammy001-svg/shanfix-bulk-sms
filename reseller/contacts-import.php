<?php
$pageTitle = 'Import Contacts';
$breadcrumb = [['label'=>'Reseller'],['label'=>'Import CSV']];
require_once __DIR__ . '/layout.php';

$uid    = $user['id'];
$groups = DB::query("SELECT id,name FROM contact_groups WHERE user_id=? ORDER BY name",[$uid]);
?>
<div class="page-header">
  <div><h1>Import Contacts</h1><div class="subtitle">Upload a CSV file to bulk-import contacts</div></div>
  <a href="/reseller/contacts.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back to Contacts</a>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:22px;align-items:start">
  <div class="card">
    <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-file-csv" style="color:var(--primary)"></i> Upload CSV File</h3></div>
    <div class="card-body">
      <div class="alert alert-info" style="margin-bottom:20px">
        <i class="fa-solid fa-circle-info"></i>
        CSV must have a header row. Required column: <strong>phone</strong>. Optional: <strong>name</strong>, <strong>email</strong>.
      </div>
      <form method="POST" action="/reseller/actions/import-contacts.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
        <div class="form-group">
          <label class="form-label">Select Group <span class="required">*</span></label>
          <select name="group_id" class="form-control" required>
            <option value="">-- Select Target Group --</option>
            <?php foreach ($groups as $g): ?>
              <option value="<?=$g['id']?>"><?=htmlspecialchars($g['name'])?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-hint">Or <a href="/reseller/groups.php" onclick="return false;" style="cursor:pointer" id="newGroupLink">create a new group first</a></div>
        </div>
        <div class="form-group">
          <label class="form-label">CSV File <span class="required">*</span></label>
          <div class="upload-zone" id="csvDropZone" ondrop="handleDrop(event)" ondragover="event.preventDefault()" ondragleave="this.classList.remove('dragover')" onclick="document.getElementById('csvFile').click()">
            <i class="fa-solid fa-cloud-arrow-up upload-icon"></i>
            <div class="upload-title">Drop your CSV file here</div>
            <div class="upload-sub">or click to browse — Max 5MB</div>
            <div id="csvFileName" style="margin-top:8px;font-size:13px;color:var(--primary);font-weight:600"></div>
          </div>
          <input type="file" id="csvFile" name="csv_file" accept=".csv,text/csv" style="display:none" required onchange="showFileName(this)">
        </div>
        <div class="form-group">
          <label class="form-label">Duplicate handling</label>
          <select name="duplicates" class="form-control">
            <option value="skip">Skip duplicates</option>
            <option value="update">Update existing</option>
            <option value="allow">Allow duplicates</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary btn-full btn-lg">
          <i class="fa-solid fa-upload"></i> Import Contacts
        </button>
      </form>
    </div>
  </div>

  <!-- Template & Guide -->
  <div style="display:flex;flex-direction:column;gap:16px">
    <div class="card">
      <div class="card-body">
        <h4 style="font-size:14px;font-weight:700;margin-bottom:12px"><i class="fa-solid fa-file-lines" style="color:var(--primary)"></i> CSV Format</h4>
        <p style="font-size:12.5px;color:var(--text-secondary);margin-bottom:12px">Your CSV file should look like this:</p>
        <div style="background:var(--bg-muted);padding:12px;border-radius:var(--radius-md);font-family:monospace;font-size:12px;color:var(--text-primary)">
          phone,name,email<br>
          +254712345678,John Doe,john@example.com<br>
          +254798765432,Jane Smith,<br>
          +254711222333,,
        </div>
        <div style="margin-top:14px">
          <a href="/includes/actions/download-template.php" class="btn btn-outline btn-full">
            <i class="fa-solid fa-download"></i> Download Template
          </a>
        </div>
      </div>
    </div>
    <div class="card">
      <div class="card-body">
        <h4 style="font-size:14px;font-weight:700;margin-bottom:10px"><i class="fa-solid fa-circle-check" style="color:var(--success)"></i> Requirements</h4>
        <ul style="font-size:12.5px;color:var(--text-secondary);display:flex;flex-direction:column;gap:6px;list-style:disc;padding-left:16px">
          <li>File must be .csv format</li>
          <li>First row must be headers</li>
          <li><strong>phone</strong> column is required</li>
          <li>Phone numbers: include country code (+254...)</li>
          <li>Max 50,000 contacts per import</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php
$extraScript = <<<'JS'
<script>
function showFileName(input) {
  const f = input.files[0];
  if (f) {
    document.getElementById('csvFileName').textContent = '✅ ' + f.name + ' (' + (f.size/1024).toFixed(1) + 'KB)';
    document.getElementById('csvDropZone').classList.add('has-file');
  }
}
function handleDrop(e) {
  e.preventDefault();
  const file = e.dataTransfer.files[0];
  if (file && file.name.endsWith('.csv')) {
    const input = document.getElementById('csvFile');
    const dt = new DataTransfer();
    dt.items.add(file);
    input.files = dt.files;
    showFileName(input);
  }
}
</script>
JS;
include __DIR__ . '/../includes/layout-footer.php';
?>
