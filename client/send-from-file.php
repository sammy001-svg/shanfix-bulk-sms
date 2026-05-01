<?php
$pageTitle = 'Send from File';
$breadcrumb = [['label'=>'Client'],['label'=>'Send from File']];
require_once __DIR__ . '/layout.php';

$uid = $user['id'];
?>

<div class="page-header">
  <div><h1>Send from File</h1><div class="subtitle">Upload a CSV file to create a contact group for personalized messaging</div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:22px;align-items:start">
  <div style="display:flex; flex-direction:column; gap:22px">
    <div class="card">
      <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-file-import" style="color:var(--primary)"></i> Create Group & Import</h3></div>
      <div class="card-body">
        <form method="POST" action="/client/actions/import-group.php" id="importForm" enctype="multipart/form-data">
          <?= csrf_field() ?>
          
          <div class="form-group">
            <label class="form-label">Group Name <span class="required">*</span></label>
            <input type="text" name="group_name" id="groupNameInput" class="form-control" placeholder="e.g. June Invoices, Customer List" required>
            <div class="form-hint">Enter a name to identify this list in your contacts.</div>
          </div>

          <div class="form-group">
            <label class="form-label">Upload CSV File <span class="required">*</span></label>
            <div class="upload-zone" id="dz" onclick="document.getElementById('csvFile').click()" ondrop="handleDrop(event)" ondragover="event.preventDefault()" style="padding:40px; border:2px dashed var(--border); border-radius:var(--radius-lg); text-align:center; cursor:pointer; transition:var(--transition)">
              <i class="fa-solid fa-file-csv upload-icon" style="font-size:48px; color:var(--text-muted); margin-bottom:15px"></i>
              <div style="font-size:15px; font-weight:500">Drop your CSV file here or click to browse</div>
              <div class="text-muted" style="font-size:13px; margin-top:5px">Supported format: .csv</div>
              <div id="fn" style="margin-top:15px;font-size:14px;color:var(--primary);font-weight:700"></div>
            </div>
            <input type="file" id="csvFile" name="csv_file" accept=".csv" style="display:none" onchange="handleFileSelect(this)" required>
            <div class="form-hint" style="margin-top:10px">
              Download <a href="/assets/templates/sms-template.csv" target="_blank" style="color:var(--primary); font-weight:600"><i class="fa-solid fa-download"></i> CSV Template</a>
            </div>
          </div>

          <div style="display:flex;gap:12px;margin-top:20px">
            <button type="submit" class="btn btn-primary btn-lg" style="flex:1; height:50px">
              <i class="fa-solid fa-cloud-arrow-up"></i> Import to Contacts
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Data Preview Section (Initially Hidden) -->
    <div class="card" id="previewCard" style="display:none">
      <div class="card-header" style="display:flex; justify-content:between; align-items:center">
        <h3 class="card-title"><i class="fa-solid fa-table-list" style="color:var(--primary)"></i> File Data Preview</h3>
        <span id="rowCount" style="font-size:12px; color:var(--text-muted); font-weight:600"></span>
      </div>
      <div class="card-body" style="padding:0; overflow-x:auto">
        <table class="data-table" id="previewTable">
          <thead><tr id="previewHead"></tr></thead>
          <tbody id="previewBody"></tbody>
        </table>
      </div>
    </div>
  </div>

  <div style="display:flex;flex-direction:column;gap:16px">
    <div class="card">
      <div class="card-body">
        <h4 style="font-size:14px;font-weight:700;margin-bottom:14px"><i class="fa-solid fa-circle-info" style="color:var(--info)"></i> How it works</h4>
        <div style="display:flex;flex-direction:column;gap:10px;font-size:13px">
          <div style="line-height:1.5">1. Upload your CSV with headers.</div>
          <div style="line-height:1.5">2. We'll create a new contact group.</div>
          <div style="line-height:1.5">3. All data (names, balances, etc.) will be saved for personalization.</div>
          <div style="line-height:1.5">4. Go to <strong>Send SMS</strong> and select this group to start sending.</div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <h4 style="font-size:14px;font-weight:700;margin-bottom:12px"><i class="fa-solid fa-lightbulb" style="color:var(--warning)"></i> CSV Requirements</h4>
        <ul style="font-size:12.5px;color:var(--text-secondary);display:flex;flex-direction:column;gap:8px;list-style:disc;padding-left:16px">
          <li>First column must be <strong>phone</strong> numbers.</li>
          <li>Headers are used as placeholders (e.g. {amount}).</li>
          <li>Max file size: 5MB</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php
$extraScript = <<<'JS'
<script>
function handleFileSelect(input) {
    const file = input.files[0];
    if (file) {
        showFileName(file);
        parseCSV(file);
        
        // Auto-fill group name if empty
        const gn = document.getElementById('groupNameInput');
        if(!gn.value) {
            gn.value = file.name.replace(/\.[^/.]+$/, "").replace(/[_-]/g, ' ');
        }
    }
}

function showFileName(f) {
    document.getElementById('fn').innerHTML = '<i class="fa-solid fa-check-circle"></i> ' + f.name + ' (' + (f.size/1024).toFixed(2) + ' KB)';
    document.getElementById('dz').style.borderColor = 'var(--primary)';
    document.getElementById('dz').style.background = 'var(--primary-light)';
}

function parseCSV(file) {
    const reader = new FileReader();
    reader.onload = function(e) {
        const text = e.target.result;
        const lines = text.split(/\r?\n/).filter(line => line.trim() !== '');
        if (lines.length === 0) return;

        const rows = lines.slice(0, 6).map(line => line.split(',')); // Preview first 5 rows
        const headers = rows[0];
        const data = rows.slice(1);

        // Update UI
        document.getElementById('previewCard').style.display = 'block';
        document.getElementById('rowCount').textContent = (lines.length - 1) + ' contacts found';

        // Headers
        const headTr = document.getElementById('previewHead');
        headTr.innerHTML = headers.map(h => `<th>${h.trim()}</th>`).join('');

        // Body
        const body = document.getElementById('previewBody');
        body.innerHTML = data.map(row => 
            `<tr>${row.map(cell => `<td>${cell.trim()}</td>`).join('')}</tr>`
        ).join('');
    };
    reader.readAsText(file);
}

function handleDrop(e) {
    e.preventDefault();
    const f = e.dataTransfer.files[0];
    if (f && f.name.endsWith('.csv')) {
        const i = document.getElementById('csvFile');
        const dt = new DataTransfer();
        dt.items.add(f);
        i.files = dt.files;
        handleFileSelect(i);
    } else {
        alert('Please upload a valid CSV file.');
    }
}

document.getElementById('dz').addEventListener('dragover', (e) => {
    e.preventDefault();
    e.currentTarget.style.borderColor = 'var(--primary)';
    e.currentTarget.style.background = 'var(--primary-light)';
});

document.getElementById('dz').addEventListener('dragleave', (e) => {
    e.preventDefault();
    e.currentTarget.style.borderColor = 'var(--border)';
    e.currentTarget.style.background = 'transparent';
});

document.getElementById('importForm').addEventListener('submit', function() {
    const btn = this.querySelector('button[type="submit"]');
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Importing...';
    btn.disabled = true;
});
</script>
JS;
include __DIR__ . '/../includes/layout-footer.php';
?>

