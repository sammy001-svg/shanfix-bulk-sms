<?php
$pageTitle = 'Send from File';
$breadcrumb = [['label'=>'Client'],['label'=>'Send from File']];
require_once __DIR__ . '/layout.php';

$uid = $user['id'];
?>

<div class="page-header">
  <div><h1>Send from File</h1><div class="subtitle">Directly send personalized SMS from an Excel or CSV file</div></div>
  <div style="background:var(--primary-light);border:1px solid var(--primary);padding:8px 18px;border-radius:var(--radius-md)">
    <span style="font-size:12px;color:var(--primary);font-weight:600">Balance:</span>
    <strong style="font-size:16px;color:var(--primary);margin-left:6px"><?=number_format($user['sms_units'],2)?></strong> units
  </div>
</div>

        <form method="POST" action="/client/actions/send-from-file.php" id="sendFromFileForm" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <input type="hidden" name="csv_data" id="csvDataInput">
  <div style="display:grid;grid-template-columns:1fr 380px;gap:22px;align-items:start">
    <!-- Left Column: Composition & Preview -->
    <div style="display:flex; flex-direction:column; gap:22px">
      <div class="card" id="compositionCard">
        <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-paper-plane" style="color:var(--primary)"></i> 1. Compose Message</h3></div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Sender ID <span class="required">*</span></label>
            <?php $senderIds = DB::query("SELECT sender_id FROM sender_ids WHERE user_id=? AND status='approved'",[$uid]); ?>
            <select name="sender_id" class="form-control" required>
              <option value="">-- Select Sender ID --</option>
              <?php foreach ($senderIds as $s): ?>
                <option value="<?=htmlspecialchars($s['sender_id'])?>"><?=htmlspecialchars($s['sender_id'])?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group sms-composer" id="messageSection" style="display:none">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px">
              <label class="form-label" style="margin-bottom:0">Message Body <span class="required">*</span></label>
              <div class="dropdown" id="placeholderDropdown">
                <button type="button" class="btn btn-sm btn-muted" style="font-size:11px; padding:4px 10px; border:1px solid var(--border)" onclick="togglePlaceholders()">
                  <i class="fa-solid fa-plus-circle" style="color:var(--primary)"></i> Insert Placeholder <i class="fa-solid fa-caret-down"></i>
                </button>
                <div id="placeholderMenu" style="display:none; position:absolute; right:0; top:100%; background:var(--card-bg); border:1px solid var(--border); border-radius:var(--radius-sm); box-shadow:var(--shadow-lg); z-index:100; min-width:160px; max-height:200px; overflow-y:auto; margin-top:5px">
                   <!-- Populated via JS -->
                </div>
              </div>
            </div>
            <div style="position:relative">
              <textarea name="message" id="smsMsg" class="form-control" placeholder="Hello ##Username##, your balance is ##Balance##." style="padding-bottom:35px" rows="6" required></textarea>
              <div class="sms-counter" style="position:absolute; bottom:12px; right:15px; font-size:11px; color:var(--text-muted); pointer-events:none; background:rgba(255,255,255,0.05); padding:2px 8px; border-radius:4px">
                <span id="chars">0</span>/160 · <span id="segs">1</span> SMS part(s) · <span id="contactCount">0</span> contacts · Est. cost: <strong id="cost" style="color:var(--primary)">0.00</strong> unit/recipient
              </div>
            </div>
            
            <button type="submit" class="btn btn-primary btn-lg" style="width:100%; height:55px; font-size:16px; font-weight:700">
              <i class="fa-solid fa-paper-plane"></i> Send Messages Now
            </button>
          </div>
          
          <div id="noUploadMsg" style="text-align:center; padding:30px; color:var(--text-muted); font-style:italic">
             <i class="fa-solid fa-arrow-right" style="margin-right:8px"></i> Please upload a file first to start composing
          </div>
        </div>
      </div>

      <!-- Data Preview Section (Initially Hidden) -->
      <div class="card" id="previewCard" style="display:none">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center">
          <h3 class="card-title"><i class="fa-solid fa-table-list" style="color:var(--primary)"></i> File Data Preview</h3>
          <span id="rowCount" style="font-size:12px; color:var(--text-muted); font-weight:600"></span>
        </div>
        <div class="card-body" style="padding:0; overflow-x:auto">
          <style>
            .preview-table { width: 100%; border-collapse: collapse; font-size: 13px; }
            .preview-table th { background: var(--bg-muted); padding: 12px 15px; text-align: left; font-weight: 700; color: var(--text-primary); border-bottom: 2px solid var(--border); text-transform: uppercase; letter-spacing: 0.05em; font-size: 11px; }
            .preview-table td { padding: 10px 15px; border-bottom: 1px solid var(--border-light); color: var(--text-secondary); }
            .preview-table tr:hover { background: rgba(0,0,0,0.02); }
          </style>
          <table class="preview-table" id="previewTable">
            <thead><tr id="previewHead"></tr></thead>
            <tbody id="previewBody"></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Right Column: Requirements & Upload -->
    <div style="display:flex;flex-direction:column;gap:22px">
      <div class="card">
        <div class="card-body">
          <h4 style="font-size:14px;font-weight:700;margin-bottom:12px"><i class="fa-solid fa-lightbulb" style="color:var(--warning)"></i> CSV/Excel Requirements</h4>
          <ul style="font-size:12.5px;color:var(--text-secondary);display:flex;flex-direction:column;gap:8px;list-style:disc;padding-left:16px">
            <li>First column must be <strong>phone</strong> numbers.</li>
            <li>Headers are used as placeholders (e.g. {amount}).</li>
            <li>Supported: <strong>Excel (.xlsx, .xls)</strong> and <strong>CSV</strong>.</li>
            <li>Max file size: 5MB</li>
          </ul>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-file-excel" style="color:var(--primary)"></i> 2. Upload File</h3></div>
        <div class="card-body">
          <div class="upload-zone" id="dz" onclick="document.getElementById('fileInput').click()" ondrop="handleDrop(event)" ondragover="event.preventDefault()" style="padding:25px; border:2px dashed var(--border); border-radius:var(--radius-lg); text-align:center; cursor:pointer; transition:var(--transition)">
            <i class="fa-solid fa-cloud-arrow-up upload-icon" style="font-size:32px; color:var(--text-muted); margin-bottom:12px"></i>
            <div style="font-size:14px; font-weight:600">Drop file here or click to browse</div>
            <div class="text-muted" style="font-size:12px; margin-top:5px">.csv, .xls, .xlsx</div>
            <div id="fn" style="margin-top:12px;font-size:13px;color:var(--primary);font-weight:700;word-break:break-all"></div>
          </div>
          <input type="file" id="fileInput" name="csv_file" accept=".csv,.xls,.xlsx" style="display:none" onchange="handleFileSelect(this)" required>
          
          <div style="margin-top:15px; padding-top:15px; border-top:1px solid var(--border)">
             <div style="font-size:12px; color:var(--text-muted); margin-bottom:8px">Download starter template:</div>
             <a href="/assets/templates/sms-template.csv" class="btn btn-sm btn-muted" style="width:100%; font-size:11px"><i class="fa-solid fa-file-csv"></i> Download CSV Template</a>
             <div style="font-size:10px; color:var(--text-muted); margin-top:5px; text-align:center">(Works with both Excel and CSV)</div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <h4 style="font-size:14px;font-weight:700;margin-bottom:14px"><i class="fa-solid fa-circle-info" style="color:var(--info)"></i> Quick Help</h4>
          <div style="display:flex;flex-direction:column;gap:10px;font-size:12px;color:var(--text-secondary)">
            <p>1. Upload your file on the right.</p>
            <p>2. Headers become variables (e.g. <code>{name}</code>).</p>
            <p>3. Preview the data on the left.</p>
            <p>4. Write your message and click Send.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</form>

<!-- SIMPLE CONFIRMATION MODAL (Standardized) -->
<div id="confirmModal" class="modal-overlay">
  <div class="modal" style="max-width:600px">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center">
      <h3 class="card-title"><i class="fa-solid fa-circle-check" style="color:var(--success)"></i> Confirm Campaign Dispatch</h3>
      <button type="button" class="btn btn-icon" onclick="closeModal('confirmModal')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="card-body" style="padding:25px">
      <div style="margin-bottom:20px">
        <h4 style="font-size:13px; font-weight:700; color:var(--text-muted); text-transform:uppercase; margin-bottom:12px; letter-spacing:0.05em">Sample Previews (First 2 Contacts)</h4>
        <div id="samplePreviews" style="display:flex; flex-direction:column; gap:12px">
           <!-- Populated via JS -->
        </div>
      </div>

      <div style="background:var(--bg-muted); border-radius:var(--radius-md); padding:20px; display:grid; grid-template-columns:repeat(2, 1fr); gap:15px">
        <div><div style="font-size:11px; color:var(--text-muted)">Sender ID</div><div id="confSenderId" style="font-weight:700; color:var(--text-primary)">-</div></div>
        <div><div style="font-size:11px; color:var(--text-muted)">Total Numbers</div><div id="confTotalNumbers" style="font-weight:700; color:var(--text-primary)">-</div></div>
        <div><div style="font-size:11px; color:var(--text-muted)">Message Length</div><div id="confChars" style="font-weight:700; color:var(--text-primary)">-</div></div>
        <div><div style="font-size:11px; color:var(--text-muted)">Cost per SMS Part</div><div style="font-weight:700; color:var(--text-primary)"><span id="confRate">1.00</span> units</div></div>
        <div style="grid-column: span 2; padding-top:10px; border-top:1px solid var(--border); margin-top:5px; display:flex; justify-content:space-between; align-items:center">
           <div style="font-size:13px; font-weight:700">Estimated Total Cost</div>
           <div id="confTotalCost" style="font-size:20px; font-weight:800; color:var(--primary)">0.00 units</div>
        </div>
      </div>

      <div style="margin-top:25px; display:flex; gap:15px">
        <button type="button" class="btn btn-muted" style="flex:1; height:50px" onclick="closeModal('confirmModal')">Cancel & Edit</button>
        <button type="button" class="btn btn-primary" style="flex:2; height:50px; font-weight:700" onclick="finalSubmit()">
          <i class="fa-solid fa-paper-plane" style="margin-right:8px"></i> Confirm & Send Now
        </button>
      </div>
    </div>
  </div>
</div>

<style>
@keyframes modalSlideUp {
  from { transform: translateY(20px); opacity: 0; }
  to { transform: translateY(0); opacity: 1; }
}
/* Custom sample bubble for previews */
.sample-msg-bubble { background: var(--primary-light); border-left: 4px solid var(--primary); padding: 12px 15px; border-radius: 4px; font-size: 13px; color: var(--text-primary); line-height: 1.5; margin-bottom: 12px; }
</style>

<?php
$extraScript = <<<'JS'
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
let parsedRows = [];
let headers = [];

function handleFileSelect(input) {
    const file = input.files[0];
    if (file) {
        showFileName(file);
        parseFile(file);
    }
}

function showFileName(f) {
    document.getElementById('fn').innerHTML = '<i class="fa-solid fa-check-circle"></i> ' + f.name + ' (' + (f.size/1024).toFixed(2) + ' KB)';
    document.getElementById('dz').style.borderColor = 'var(--primary)';
    document.getElementById('dz').style.background = 'var(--primary-light)';
}

function parseFile(file) {
    const reader = new FileReader();
    const ext = file.name.split('.').pop().toLowerCase();
    
    reader.onload = function(e) {
        const data = e.target.result;
        let workbook;
        
        if (ext === 'csv') {
            const text = data;
            workbook = XLSX.read(text, { type: 'string' });
        } else {
            const arrayBuffer = data;
            workbook = XLSX.read(arrayBuffer, { type: 'array' });
        }
        
        const firstSheetName = workbook.SheetNames[0];
        const worksheet = workbook.Sheets[firstSheetName];
        const rawJson = XLSX.utils.sheet_to_json(worksheet, { header: 1, defval: '' });
        
        // Find the first non-empty row as header
        let headerRowIndex = 0;
        while (headerRowIndex < rawJson.length && (!rawJson[headerRowIndex] || rawJson[headerRowIndex].every(cell => !cell))) {
            headerRowIndex++;
        }

        if (headerRowIndex >= rawJson.length) {
            alert('Error: File is empty or has no recognizable data.');
            return;
        }

        headers = rawJson[headerRowIndex].map(h => String(h || '').trim().toLowerCase());
        const rawRows = rawJson.slice(headerRowIndex + 1);
        
        // Find phone column
        const phoneIdx = headers.findIndex(h => h.includes('phone') || h.includes('number') || h.includes('mobile') || h.includes('contact'));

        if (phoneIdx === -1) {
            alert('Error: File must have a "phone" or "mobile" column header.');
            return;
        }

        // Clean headers for placeholders (remove spaces, etc.)
        headers = headers.map(h => h.replace(/[^a-z0-9]/g, ''));
        
        parsedRows = rawRows.filter(row => row.length > 0 && row[phoneIdx]);

        if (parsedRows.length === 0) {
            alert('Error: No valid contact rows found below the header.');
            return;
        }

        // Show UI
        const msgSec = document.getElementById('messageSection');
        if (msgSec) msgSec.style.display = 'block';
        
        const noUpload = document.getElementById('noUploadMsg');
        if (noUpload) noUpload.style.display = 'none';
        
        const prevCard = document.getElementById('previewCard');
        if (prevCard) prevCard.style.display = 'block';
        
        const cCount = document.getElementById('contactCount');
        if (cCount) cCount.textContent = parsedRows.length;
        
        // Update Placeholder Dropdown
        const menu = document.getElementById('placeholderMenu');
        menu.innerHTML = headers.map(h => {
            if (!h) return '';
            const label = h.charAt(0).toUpperCase() + h.slice(1);
            return `<div style="padding:8px 12px; cursor:pointer; font-size:12px; border-bottom:1px solid var(--border-light)" onclick="insertPlaceholder('##${label}##')">${label}</div>`;
        }).join('');
        
        // Preview
        const previewHead = document.getElementById('previewHead');
        previewHead.innerHTML = headers.map(h => `<th>${h || '-'}</th>`).join('');
        
        const previewBody = document.getElementById('previewBody');
        previewBody.innerHTML = parsedRows.slice(0, 5).map(row => 
            `<tr>${headers.map((_, i) => `<td>${row[i] || ''}</td>`).join('')}</tr>`
        ).join('');
        
        document.getElementById('rowCount').textContent = parsedRows.length + ' contacts found';
    };
    
    if (ext === 'csv') {
        reader.readAsText(file);
    } else {
        reader.readAsArrayBuffer(file);
    }
}

function handleDrop(e) {
    e.preventDefault();
    const f = e.dataTransfer.files[0];
    if (f) {
        const i = document.getElementById('fileInput');
        const dt = new DataTransfer();
        dt.items.add(f);
        i.files = dt.files;
        handleFileSelect(i);
    }
}

const ta = document.getElementById('smsMsg');
if (ta) {
    ta.addEventListener('input', function() {
        const l = this.value.length;
        const parts = Math.ceil(l / 160) || 1;
        const rate = window.ShanfixConfig.smsRate || 1.00;
        document.getElementById('chars').textContent = l;
        document.getElementById('segs').textContent = parts;
        const costEl = document.getElementById('cost');
        if (costEl) costEl.textContent = (parts * rate).toFixed(2);
    });
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

function togglePlaceholders() {
    const menu = document.getElementById('placeholderMenu');
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}

function insertPlaceholder(ph) {
    const ta = document.getElementById('smsMsg');
    const start = ta.selectionStart;
    const end = ta.selectionEnd;
    const text = ta.value;
    ta.value = text.substring(0, start) + ph + text.substring(end);
    ta.focus();
    ta.selectionStart = ta.selectionEnd = start + ph.length;
    ta.dispatchEvent(new Event('input')); // Update counters
    document.getElementById('placeholderMenu').style.display = 'none';
}



function finalSubmit() {
    const btn = document.querySelector('#confirmModal .btn-primary');
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Dispatching...';
    btn.disabled = true;
    document.getElementById('sendFromFileForm').submit();
}

// Close dropdown when clicking outside
window.addEventListener('click', function(e) {
    if (document.getElementById('placeholderDropdown') && !document.getElementById('placeholderDropdown').contains(e.target)) {
        document.getElementById('placeholderMenu').style.display = 'none';
    }
});

document.getElementById('sendFromFileForm').addEventListener('submit', function(e) {
    e.preventDefault(); // Stop initial submit to show modal
    
    const senderId = this.querySelector('select[name="sender_id"]').value;
    const msgTemplate = document.getElementById('smsMsg').value;
    
    if (!senderId || !msgTemplate) {
        alert('Please select a Sender ID and enter a message.');
        return;
    }

    // Populate Modal
    document.getElementById('confSenderId').textContent = senderId;
    document.getElementById('confTotalNumbers').textContent = parsedRows.length;
    
    const charCount = msgTemplate.length;
    const parts = Math.ceil(charCount / 160) || 1;
    const rate = window.ShanfixConfig.smsRate || 1.00;
    document.getElementById('confChars').textContent = charCount + ' chars (' + parts + ' part' + (parts > 1 ? 's' : '') + ')';
    
    document.getElementById('confRate').textContent = rate.toFixed(2);
    const totalCost = (parsedRows.length * parts * rate).toFixed(2);
    document.getElementById('confTotalCost').textContent = totalCost + ' units';

    // Generate Samples
    const sampleContainer = document.getElementById('samplePreviews');
    sampleContainer.innerHTML = '';
    
    parsedRows.slice(0, 2).forEach((row, i) => {
        let msg = msgTemplate;
        headers.forEach((h, hIdx) => {
            const val = row[hIdx] || '';
            const label = h.charAt(0).toUpperCase() + h.slice(1);
            msg = msg.replace(new RegExp('##' + label + '##', 'g'), val);
        });
        
        const bubble = document.createElement('div');
        bubble.className = 'sample-msg-bubble';
        bubble.innerHTML = `<div style="font-size:10px; font-weight:700; color:var(--primary); margin-bottom:5px">TO: ${row[0]}</div>${msg}`;
        sampleContainer.appendChild(bubble);
    });

    // Show Modal
    openModal('confirmModal');

    // Prepare hidden data
    const allData = [headers, ...parsedRows];
    const csvContent = allData.map(row => 
        row.map(cell => {
            let c = String(cell || '').replace(/"/g, '""');
            return c.includes(',') || c.includes('"') || c.includes('\n') ? `"${c}"` : c;
        }).join(',')
    ).join('\n');
    
    document.getElementById('csvDataInput').value = csvContent;
});
</script>
JS;
include __DIR__ . '/../includes/layout-footer.php';
?>

