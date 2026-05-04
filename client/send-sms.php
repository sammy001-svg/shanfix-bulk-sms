<?php
$pageTitle = 'Send SMS';
$breadcrumb = [['label'=>'Client'],['label'=>'Send SMS']];
require_once __DIR__ . '/layout.php';

$uid       = $user['id'];
$senderIds = DB::query("SELECT sender_id FROM sender_ids WHERE user_id=? AND status='approved'",[$uid]);
$groups    = DB::query("SELECT id,name FROM contact_groups WHERE user_id=?",[$uid]);
$units     = $user['sms_units'];
?>

/* Custom sample bubble for previews */
.sample-msg-bubble { background: var(--primary-light); border-left: 4px solid var(--primary); padding: 12px 15px; border-radius: 4px; font-size: 13px; color: var(--text-primary); line-height: 1.5; margin-bottom: 12px; }

.sample-msg-bubble { background: var(--primary-light); border-left: 4px solid var(--primary); padding: 12px 15px; border-radius: 4px; font-size: 13px; color: var(--text-primary); line-height: 1.5; margin-bottom: 12px; }

/* Enhanced Table Styling */
.preview-card { border-radius:var(--radius-md); overflow:hidden; border:1px solid var(--border); box-shadow:var(--shadow-md); margin-top:24px; background:var(--card-bg); }
.preview-table-container { width:100%; overflow-x:auto; }
.preview-table { width:100%; border-collapse:collapse; margin:0; font-size:13px; }
.preview-table th { background:var(--bg-muted); color:var(--text-primary); font-weight:700; text-transform:uppercase; font-size:11px; letter-spacing:0.05em; padding:12px 15px; text-align:left; border-bottom:2px solid var(--border); position:sticky; top:0; z-index:10; }
.preview-table td { padding:10px 15px; border-bottom:1px solid var(--border-light); color:var(--text-secondary); }
.preview-table tr:hover { background:rgba(0,0,0,0.02); }

#placeholderWrap { animation:fadeInDown 0.3s ease-out; }
@keyframes fadeInDown { from { opacity:0; transform:translateY(-10px); } to { opacity:1; transform:translateY(0); } }
</style>

<div class="page-header">
  <div><h1>Send SMS</h1><div class="subtitle">Send a single SMS or to a group of contacts</div></div>
  <div style="background:var(--primary-light);border:1px solid var(--primary);padding:8px 18px;border-radius:var(--radius-md)">
    <span style="font-size:12px;color:var(--primary);font-weight:600">Balance:</span>
    <strong style="font-size:16px;color:var(--primary);margin-left:6px"><?=number_format($units,2)?></strong> units
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:22px;align-items:start">
  <div>
    <div class="card">
      <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-paper-plane" style="color:var(--primary)"></i> Compose Message</h3></div>
      <div class="card-body">
        <form method="POST" action="/client/actions/quick-send.php" id="sendForm">
          <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
          <input type="hidden" name="send_mode" id="sendMode" value="single">
          <input type="hidden" name="csv_data" id="mainCsvData">

          <div class="form-group">
            <label class="form-label">Sender ID <span class="required">*</span></label>
            <select name="sender_id" id="mainSenderId" class="form-control" required>
              <option value="">-- Select Sender ID --</option>
              <?php foreach ($senderIds as $s): ?>
                <option value="<?=htmlspecialchars($s['sender_id'])?>"><?=htmlspecialchars($s['sender_id'])?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group" id="sendToSection">
            <label class="form-label">Send To</label>
            <div class="tabs">
              <button type="button" class="tab-btn active" onclick="switchSendTab(this,'tab-single', 'single')">Single Number</button>
              <button type="button" class="tab-btn" onclick="switchSendTab(this,'tab-multiple', 'multiple')">Multiple Numbers</button>
              <button type="button" class="tab-btn" onclick="switchSendTab(this,'tab-grp', 'group')">Contact Group</button>
            </div>

            <div class="tab-panel active" id="tab-single">
              <input type="text" name="recipient" class="form-control" placeholder="+254712345678">
            </div>
            <div class="tab-panel" id="tab-multiple">
              <textarea name="numbers" class="form-control" rows="3" placeholder="+254712345678, +254798765432&#10;One per line or comma-separated"></textarea>
            </div>
            <div class="tab-panel" id="tab-grp">
              <select name="group_id" class="form-control">
                <option value="">-- Select Group --</option>
                <?php foreach ($groups as $g): ?>
                  <option value="<?=$g['id']?>"><?=htmlspecialchars($g['name'])?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form-group sms-composer">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px">
              <label class="form-label" style="margin-bottom:0">Message <span class="required">*</span></label>
              <div style="display:flex; gap:8px">
                  <div id="placeholderWrap" style="display:none">
                      <select class="form-control" style="width:auto; height:32px; font-size:12px; padding:0 10px; border-color:var(--primary); color:var(--primary); font-weight:600" onchange="insertPlaceholder(this)">
                          <option value="">+ Insert Placeholder</option>
                      </select>
                  </div>
                  <?php if (!empty($myTemplates = DB::query("SELECT id, title, message FROM sms_templates WHERE user_id = ?", [$uid]))): ?>
                  <select class="form-control" style="width:auto; height:32px; font-size:12px; padding:0 10px" onchange="loadTemplate(this)">
                      <option value="">-- Quick Templates --</option>
                      <?php foreach($myTemplates as $mt): ?>
                          <option value="<?= htmlspecialchars($mt['message']) ?>"><?= htmlspecialchars($mt['title']) ?></option>
                      <?php endforeach; ?>
                  </select>
                  <?php endif; ?>
              </div>
            </div>
            <textarea name="message" id="smsMsg" class="form-control" placeholder="Type your SMS message here..." maxlength="918" required></textarea>
              <div class="sms-counter" style="position:absolute; bottom:12px; right:15px; font-size:11px; color:var(--text-muted); pointer-events:none; background:rgba(255,255,255,0.05); padding:2px 8px; border-radius:4px">
                <span id="chars">0</span> chars · <span id="segs" style="color:var(--primary); font-weight:700">1</span> Unit(s) · <span id="contactCount">0</span> contacts · Est. cost: <strong style="color:var(--primary)">KES <span id="cost">0.00</span></strong> per recipient
              </div>
          </div>

          <div class="form-group">
            <label class="form-label">Schedule (optional)</label>
            <input type="datetime-local" name="scheduled_at" class="form-control">
          </div>

          <div style="display:flex;gap:12px;margin-top:8px">
            <button type="submit" class="btn btn-primary btn-lg" style="flex:1" <?=empty($senderIds)?'disabled':''?>>
              <i class="fa-solid fa-paper-plane"></i> Send SMS
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- FILE DATA PREVIEW -->
    <div id="mainFilePreviewSection" style="display:none">
        <div class="preview-card">
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center">
                <h3 class="card-title"><i class="fa-solid fa-table-list" style="color:var(--primary)"></i> File Data Preview</h3>
                <div style="display:flex; align-items:center; gap:10px">
                    <span id="mainFileCountBadge" style="font-size:12px; font-weight:600; color:var(--text-muted)"></span>
                    <button type="button" class="btn btn-icon" style="color:var(--danger)" onclick="clearSideFile()"><i class="fa-solid fa-trash-can"></i></button>
                </div>
            </div>
            <div class="preview-table-container" style="max-height:400px; overflow:auto">
                <table class="preview-table">
                    <thead id="mainQpHead"></thead>
                    <tbody id="mainQpBody"></tbody>
                </table>
            </div>
        </div>
    </div>
  </div>

  <!-- Sidebar -->
  <div style="display:flex;flex-direction:column;gap:16px">
    <div class="card">
      <div class="card-body">
        <h4 style="font-size:14px;font-weight:700;margin-bottom:14px"><i class="fa-solid fa-circle-info" style="color:var(--info)"></i> SMS Info</h4>
        <div style="display:flex;flex-direction:column;gap:10px;font-size:13px">
          <div class="d-flex justify-between"><span class="text-muted">Max characters:</span><strong>160 / SMS part</strong></div>
          <div class="d-flex justify-between"><span class="text-muted">Max parts:</span><strong>6 parts</strong></div>
          <div class="d-flex justify-between"><span class="text-muted">Cost per part:</span><strong>1 unit</strong></div>
          <div class="d-flex justify-between"><span class="text-muted">Your balance:</span><strong style="color:var(--primary)"><?=number_format($units,2)?> units</strong></div>
        </div>
      </div>
    </div>

    <div class="card" id="quickUploadCard">
      <div class="card-body">
        <h4 style="font-size:14px;font-weight:700;margin-bottom:12px"><i class="fa-solid fa-file-import" style="color:var(--primary)"></i> Quick File Upload</h4>
        <div id="dz" style="border:2px dashed var(--border); border-radius:var(--radius-md); padding:20px; text-align:center; transition:all 0.2s; cursor:pointer" onclick="document.getElementById('sideFileInput').click()">
          <i class="fa-solid fa-cloud-arrow-up" style="font-size:28px; color:var(--text-muted); margin-bottom:10px"></i>
          <div style="font-size:12px; color:var(--text-secondary); font-weight:600">Click or Drag Excel/CSV</div>
          <input type="file" id="sideFileInput" style="display:none" accept=".csv, .xlsx, .xls" onchange="handleSideFile(this)">
        </div>
        
        <div id="sideFileInfo" style="display:none; margin-top:15px; padding:12px; background:var(--primary-light); border:1px solid var(--primary); border-radius:var(--radius-sm); display:flex; align-items:center; gap:10px">
              <i class="fa-solid fa-file-excel" style="color:var(--primary); font-size:20px"></i>
              <div style="overflow:hidden; flex:1">
                 <div id="sideFileName" style="font-weight:700; font-size:12px; text-overflow:ellipsis; overflow:hidden; white-space:nowrap">File.xlsx</div>
                 <div style="font-size:10px; color:var(--text-secondary)">File Broadcasting Ready</div>
              </div>
              <button type="button" class="btn btn-icon" style="color:var(--danger)" onclick="clearSideFile()"><i class="fa-solid fa-xmark"></i></button>
        </div>
      </div>
    </div>
  </div>
</div>

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
        <div id="samplePreviews"></div>
      </div>

      <div style="background:var(--bg-muted); border-radius:var(--radius-md); padding:20px; display:grid; grid-template-columns:repeat(2, 1fr); gap:15px">
        <div><div style="font-size:11px; color:var(--text-muted)">Sender ID</div><div id="mSender" style="font-weight:700; color:var(--text-primary)">-</div></div>
        <div><div style="font-size:11px; color:var(--text-muted)">Total Numbers</div><div id="mContacts" style="font-weight:700; color:var(--text-primary)">-</div></div>
        <div><div style="font-size:11px; color:var(--text-muted)">Message Length</div><div id="mChars" style="font-weight:700; color:var(--text-primary)">-</div></div>
        <div><div style="font-size:11px; color:var(--text-muted)">Cost per SMS Part</div><div style="font-weight:700; color:var(--text-primary)"><span id="mRate">1.00</span> units</div></div>
        <div style="grid-column: span 2; padding-top:10px; border-top:1px solid var(--border); margin-top:5px; display:flex; justify-content:space-between; align-items:center">
           <div style="font-size:13px; font-weight:700">Estimated Total Cost</div>
           <div id="mTotal" style="font-size:20px; font-weight:800; color:var(--primary)">0.00 units</div>
        </div>
      </div>

      <div style="margin-top:25px; display:flex; gap:15px">
        <button type="button" class="btn btn-muted" style="flex:1; height:50px" onclick="closeModal('confirmModal')">Cancel & Edit</button>
        <button type="button" class="btn btn-primary" style="flex:2; height:50px; font-weight:700" onclick="confirmAndSend()">
          <i class="fa-solid fa-paper-plane" style="margin-right:8px"></i> Confirm & Send Now
        </button>
      </div>
    </div>
  </div>
</div>

<?php
$extraScript = <<<'JS'
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
let sideHeaders = [];
let sideRows = [];
let hasFile = false;

function switchSendTab(btn, tabId, mode) {
    if (hasFile && !confirm('Switching tabs will ignore the uploaded file. Continue?')) return;
    if (hasFile) clearSideFile();

    const parent = btn.closest('.form-group');
    parent.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    parent.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(tabId).classList.add('active');
    document.getElementById('sendMode').value = mode;
}

function insertPlaceholder(select) {
    const val = select.value;
    if (!val) return;
    const ta = document.getElementById('smsMsg');
    const start = ta.selectionStart;
    const end = ta.selectionEnd;
    const text = ta.value;
    ta.value = text.substring(0, start) + '##' + val + '##' + text.substring(end);
    ta.focus();
    ta.selectionStart = ta.selectionEnd = start + val.length + 4;
    select.value = '';
    ta.dispatchEvent(new Event('input'));
}

function handleSideFile(input) {
    const file = input.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(e) {
        const data = new Uint8Array(e.target.result);
        const workbook = XLSX.read(data, { type: 'array' });
        const sheet = workbook.Sheets[workbook.SheetNames[0]];
        const rawJson = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '' });

        let hIdx = 0;
        while (hIdx < rawJson.length && (!rawJson[hIdx] || rawJson[hIdx].every(c => !c))) hIdx++;
        if (hIdx >= rawJson.length) { alert('File is empty.'); return; }

        sideHeaders = rawJson[hIdx].map(h => String(h || '').trim());
        const pIdx = sideHeaders.findIndex(h => {
            const low = h.toLowerCase();
            return low.includes('phone') || low.includes('number') || low.includes('mobile');
        });
        if (pIdx === -1) { alert('Missing "phone" column.'); return; }

        sideRows = rawJson.slice(hIdx + 1).filter(r => r.length > 0 && r[pIdx]);
        hasFile = true;

        // UI Updates
        document.getElementById('sideFileName').textContent = file.name;
        document.getElementById('sideFileInfo').style.display = 'flex';
        document.getElementById('dz').style.display = 'none';
        document.getElementById('sendToSection').style.opacity = '0.3';
        document.getElementById('sendToSection').style.pointerEvents = 'none';
        document.getElementById('sendMode').value = 'file';

        // Placeholder Dropdown
        const pWrap = document.getElementById('placeholderWrap');
        const pSel = pWrap.querySelector('select');
        pSel.innerHTML = '<option value="">+ Insert Placeholder</option>';
        sideHeaders.forEach(h => {
            const opt = document.createElement('option');
            opt.value = h;
            opt.textContent = h;
            pSel.appendChild(opt);
        });
        pWrap.style.display = 'block';

        // Main Preview Card
        document.getElementById('mainFilePreviewSection').style.display = 'block';
        document.getElementById('mainFileCountBadge').textContent = sideRows.length + ' contacts found';
        
        // Update the small counter next to the message box
        const cCount = document.getElementById('contactCount');
        if (cCount) cCount.textContent = sideRows.length;

        document.getElementById('mainQpHead').innerHTML = `<tr>${sideHeaders.map(h => `<th>${h}</th>`).join('')}</tr>`;
        document.getElementById('mainQpBody').innerHTML = sideRows.slice(0, 5).map(r => `<tr>${sideHeaders.map((_, i) => `<td>${r[i] || ''}</td>`).join('')}</tr>`).join('');
        
        document.getElementById('mainFilePreviewSection').scrollIntoView({ behavior: 'smooth' });
    };
    reader.readAsArrayBuffer(file);
}

function clearSideFile() {
    sideHeaders = [];
    sideRows = [];
    hasFile = false;
    document.getElementById('sideFileInput').value = '';
    document.getElementById('sideFileInfo').style.display = 'none';
    document.getElementById('dz').style.display = 'block';
    document.getElementById('sendToSection').style.opacity = '1';
    document.getElementById('sendToSection').style.pointerEvents = 'auto';
    document.getElementById('sendMode').value = 'single';
    document.getElementById('placeholderWrap').style.display = 'none';
    document.getElementById('mainFilePreviewSection').style.display = 'none';
}

// Modal Logic
function openConfirmModal() {
    const msg = document.getElementById('smsMsg').value;
    const sid = document.getElementById('mainSenderId').value;
    
    if (!sid) { alert('Please select a Sender ID.'); return; }
    if (!msg) { alert('Please type a message.'); return; }

    const sampleContainer = document.getElementById('samplePreviews');
    sampleContainer.innerHTML = '';
    sideRows.slice(0, 2).forEach((row, i) => {
        let text = msg;
        sideHeaders.forEach((h, hIdx) => {
            text = text.replace(new RegExp('##' + h + '##', 'g'), row[hIdx] || '');
        });
        const bubble = document.createElement('div');
        bubble.className = 'sample-msg-bubble';
        bubble.innerHTML = `<div style="font-size:10px; font-weight:700; color:var(--primary); margin-bottom:5px">SAMPLE MESSAGE FOR: ${row[0]}</div>${text}`;
        sampleContainer.appendChild(bubble);
    });
    
    const charLen = msg.length;
    const segs = Math.ceil(charLen / 160) || 1;
    const totalContacts = sideRows.length;
    const rate = window.ShanfixConfig.smsRate || 1.00;
    
    document.getElementById('mSender').textContent = sid;
    document.getElementById('mChars').textContent = charLen + ' chars (' + segs + ' part' + (segs > 1 ? 's' : '') + ')';
    document.getElementById('mContacts').textContent = totalContacts;
    document.getElementById('mRate').textContent = rate.toFixed(2);
    document.getElementById('mTotal').textContent = (totalContacts * segs * rate).toFixed(2) + ' units';
    
    openModal('confirmModal');
}


function confirmAndSend() {
    const btn = document.querySelector('#confirmModal .btn-primary');
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Dispatching...';
    btn.disabled = true;

    const csv = [sideHeaders, ...sideRows].map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n');
    document.getElementById('mainCsvData').value = csv;
    document.getElementById('sendForm').action = '/client/actions/send-from-file.php';
    document.getElementById('sendForm').submit();
}

document.getElementById('sendForm').addEventListener('submit', function(e) {
    if (hasFile) {
        e.preventDefault();
        openConfirmModal();
    }
});

// Counter
const smsArea = document.getElementById('smsMsg');
if (smsArea) {
    smsArea.addEventListener('input', () => {
        const text = smsArea.value;
        const l = text.length;
        const parts = Math.ceil(l / 160) || 1;
        const rate = window.ShanfixConfig.smsRate || 1.00;
        document.getElementById('chars').textContent = l;
        document.getElementById('segs').textContent = parts;
        document.getElementById('cost').textContent = (parts * rate).toFixed(2);
    });
}

function loadTemplate(select) {
    if (select.value) {
        smsArea.value = select.value;
        smsArea.dispatchEvent(new Event('input'));
    }
}

// Drag & Drop
const dz = document.getElementById('dz');
if (dz) {
    dz.addEventListener('dragover', (e) => { e.preventDefault(); dz.style.borderColor = 'var(--primary)'; dz.style.background = 'var(--primary-light)'; });
    dz.addEventListener('dragleave', (e) => { e.preventDefault(); dz.style.borderColor = 'var(--border)'; dz.style.background = 'transparent'; });
    dz.addEventListener('drop', (e) => {
        e.preventDefault();
        dz.style.borderColor = 'var(--border)';
        dz.style.background = 'transparent';
        if (e.dataTransfer.files.length) {
            const input = document.getElementById('sideFileInput');
            input.files = e.dataTransfer.files;
            handleSideFile(input);
        }
    });
}
</script>
JS;
include __DIR__ . '/../includes/layout-footer.php';
?>
