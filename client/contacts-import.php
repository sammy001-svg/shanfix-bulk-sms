<?php
// Client contacts CSV import — mirrors reseller/contacts-import.php
$pageTitle = 'Import Contacts';
$breadcrumb = [['label'=>'Client'],['label'=>'Import CSV']];
require_once __DIR__ . '/layout.php';

$uid    = $user['id'];
$groups = DB::query("SELECT id,name FROM contact_groups WHERE user_id=? ORDER BY name",[$uid]);
?>
<div class="page-header">
  <div><h1>Import Contacts</h1><div class="subtitle">Upload a CSV file to bulk-import contacts</div></div>
  <a href="/client/contacts.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back to Contacts</a>
</div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:22px;align-items:start">
  <div class="card">
    <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-file-csv" style="color:var(--primary)"></i> Upload CSV File</h3></div>
    <div class="card-body">
      <div class="alert alert-info" style="margin-bottom:20px"><i class="fa-solid fa-circle-info"></i> CSV must have a header row. Required column: <strong>phone</strong>. Optional: <strong>name</strong>, <strong>email</strong>.</div>
      <form method="POST" action="/client/actions/import-contacts.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
        <div class="form-group">
          <label class="form-label">Select Group <span class="required">*</span></label>
          <select name="group_id" class="form-control" required>
            <option value="">-- Select Group --</option>
            <?php foreach ($groups as $g): ?><option value="<?=$g['id']?>"><?=htmlspecialchars($g['name'])?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">CSV File <span class="required">*</span></label>
          <div class="upload-zone" id="dz" onclick="document.getElementById('csvFile').click()" ondrop="handleDrop(event)" ondragover="event.preventDefault()">
            <i class="fa-solid fa-cloud-arrow-up upload-icon"></i>
            <div class="upload-title">Drop CSV here or click to browse</div>
            <div class="upload-sub">Max 5MB</div>
            <div id="fn" style="margin-top:8px;font-size:13px;color:var(--primary);font-weight:600"></div>
          </div>
          <input type="file" id="csvFile" name="csv_file" accept=".csv" style="display:none" required onchange="showFn(this)">
        </div>
        <div class="form-group">
          <label class="form-label">Duplicates</label>
          <select name="duplicates" class="form-control"><option value="skip">Skip duplicates</option><option value="update">Update existing</option><option value="allow">Allow duplicates</option></select>
        </div>
        <button type="submit" class="btn btn-primary btn-full btn-lg"><i class="fa-solid fa-upload"></i> Import</button>
      </form>
    </div>
  </div>
  <div style="display:flex;flex-direction:column;gap:16px">
    <div class="card"><div class="card-body">
      <h4 style="font-size:14px;font-weight:700;margin-bottom:12px"><i class="fa-solid fa-file-lines" style="color:var(--primary)"></i> CSV Format</h4>
      <div style="background:var(--bg-muted);padding:12px;border-radius:var(--radius-md);font-family:monospace;font-size:12px">phone,name,email<br>+254712345678,John,<br>+254798765432,,jane@x.com</div>
      <a href="/reseller/actions/download-template.php" class="btn btn-outline btn-full" style="margin-top:12px"><i class="fa-solid fa-download"></i> Template</a>
    </div></div>
    <div class="card"><div class="card-body">
      <ul style="font-size:12.5px;color:var(--text-secondary);display:flex;flex-direction:column;gap:6px;list-style:disc;padding-left:16px">
        <li>First row must be headers</li><li><strong>phone</strong> column required</li><li>Include country code (+254...)</li><li>Max 50,000 contacts per import</li>
      </ul>
    </div></div>
  </div>
</div>
<?php
$extraScript = <<<'JS'
<script>
function showFn(i){const f=i.files[0];if(f){document.getElementById('fn').textContent='✅ '+f.name;}}
function handleDrop(e){e.preventDefault();const f=e.dataTransfer.files[0];if(f&&f.name.endsWith('.csv')){const i=document.getElementById('csvFile');const dt=new DataTransfer();dt.items.add(f);i.files=dt.files;showFn(i);}}
</script>
JS;
include __DIR__ . '/../includes/layout-footer.php';
?>
