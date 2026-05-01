<?php
$pageTitle = 'Send SMS';
$breadcrumb = [['label'=>'Client'],['label'=>'Send SMS']];
require_once __DIR__ . '/layout.php';

$uid       = $user['id'];
$senderIds = DB::query("SELECT sender_id FROM sender_ids WHERE user_id=? AND status='approved'",[$uid]);
$groups    = DB::query("SELECT id,name FROM contact_groups WHERE user_id=?",[$uid]);
$units     = $user['sms_units'];
?>

<div class="page-header">
  <div><h1>Send SMS</h1><div class="subtitle">Send a single SMS or to a group of contacts</div></div>
  <div style="background:var(--primary-light);border:1px solid var(--primary);padding:8px 18px;border-radius:var(--radius-md)">
    <span style="font-size:12px;color:var(--primary);font-weight:600">Balance:</span>
    <strong style="font-size:16px;color:var(--primary);margin-left:6px"><?=number_format($units,2)?></strong> units
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:22px;align-items:start">
  <div class="card">
    <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-paper-plane" style="color:var(--primary)"></i> Compose Message</h3></div>
    <div class="card-body">
      <form method="POST" action="/client/actions/quick-send.php" id="sendForm" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
        <input type="hidden" name="send_mode" id="sendMode" value="single">

        <div class="form-group">
          <label class="form-label">Sender ID <span class="required">*</span></label>
          <select name="sender_id" class="form-control" required>
            <option value="">-- Select Sender ID --</option>
            <?php foreach ($senderIds as $s): ?>
              <option value="<?=htmlspecialchars($s['sender_id'])?>"><?=htmlspecialchars($s['sender_id'])?></option>
            <?php endforeach; ?>
          </select>
          <?php if (empty($senderIds)): ?>
            <div class="form-hint"><a href="/client/sender-ids.php?new=1">You need an approved Sender ID first →</a></div>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label">Send To</label>
          <div class="tabs">
            <button type="button" class="tab-btn active" onclick="switchSendTab(this,'tab-single', 'single')">Single Number</button>
            <button type="button" class="tab-btn" onclick="switchSendTab(this,'tab-multiple', 'multiple')">Multiple Numbers</button>
            <button type="button" class="tab-btn" onclick="switchSendTab(this,'tab-grp', 'group')">Contact Group</button>
            <button type="button" class="tab-btn" onclick="switchSendTab(this,'tab-file', 'file')">Send from File</button>
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
          <div class="tab-panel" id="tab-file">
            <div class="upload-zone" id="dz" onclick="document.getElementById('csvFile').click()" ondrop="handleDrop(event)" ondragover="event.preventDefault()" style="padding:20px">
              <i class="fa-solid fa-file-csv upload-icon" style="font-size:28px"></i>
              <div style="font-size:13px">Drop CSV or click to browse</div>
              <div id="fn" style="margin-top:5px;font-size:12px;color:var(--primary);font-weight:600"></div>
            </div>
            <input type="file" id="csvFile" name="csv_file" accept=".csv" style="display:none" onchange="showFn(this)">
            <div class="form-hint">Download <a href="/assets/templates/sms-template.csv" target="_blank">CSV Template</a></div>
          </div>
        </div>

        <div class="form-group sms-composer">
          <label class="form-label">Message <span class="required">*</span></label>
          <div id="placeholderGuide" style="display:none; background:var(--bg-muted); padding:8px 12px; border-radius:var(--radius-md); margin-bottom:10px; font-size:11.5px; border:1px dashed var(--border)">
             <strong>Placeholders:</strong> {username}, {order_id}, {currency}, {amount}
          </div>
          <textarea name="message" id="smsMsg" class="form-control" placeholder="Type your SMS message here..." maxlength="918" required></textarea>
          <div class="sms-counter"><span id="chars">0</span>/160 · <span id="segs">1</span> SMS part(s) · Est. cost: <strong id="cost" style="color:var(--primary)">1</strong> unit/recipient</div>
        </div>

        <div class="form-group">
          <label class="form-label">Schedule (optional)</label>
          <input type="datetime-local" name="scheduled_at" class="form-control">
          <div class="form-hint">Leave empty to send immediately</div>
        </div>

        <div style="display:flex;gap:12px;margin-top:8px">
          <button type="submit" class="btn btn-primary btn-lg" style="flex:1" <?=empty($senderIds)?'disabled':''?>>
            <i class="fa-solid fa-paper-plane"></i> Send SMS
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Info Panel -->
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

    <div class="card">
      <div class="card-body">
        <h4 style="font-size:14px;font-weight:700;margin-bottom:12px"><i class="fa-solid fa-lightbulb" style="color:var(--warning)"></i> Tips</h4>
        <ul style="font-size:12.5px;color:var(--text-secondary);display:flex;flex-direction:column;gap:8px;list-style:disc;padding-left:16px">
          <li>Keep messages under 160 chars to use 1 SMS unit</li>
          <li>Use your approved Sender ID for better deliverability</li>
          <li>Include an opt-out instruction for bulk sends</li>
          <li>Schedule campaigns during business hours (8AM–8PM)</li>
        </ul>
      </div>
    </div>

    <?php if (empty($senderIds)): ?>
    <div class="alert alert-warning">
      <i class="fa-solid fa-triangle-exclamation"></i>
      You need an approved Sender ID to send SMS.
      <a href="/client/sender-ids.php?new=1" style="display:block;margin-top:8px;font-weight:700">Request Sender ID →</a>
    </div>
    <?php endif; ?>

    <?php if ($units < 5): ?>
    <div class="alert alert-warning">
      <i class="fa-solid fa-coins"></i>
      Low balance! <a href="/client/purchases.php" style="font-weight:700">Buy more units →</a>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php
$extraScript = <<<'JS'
<script>
function switchSendTab(btn, tabId, mode) {
    // Standard tab switching
    const parent = btn.closest('.form-group');
    parent.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    parent.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(tabId).classList.add('active');
    
    // Update hidden mode field
    document.getElementById('sendMode').value = mode;
    
    // Update form action
    const form = document.getElementById('sendForm');
    if (mode === 'file') {
        form.action = '/client/actions/send-from-file.php';
        document.getElementById('placeholderGuide').style.display = 'block';
    } else {
        form.action = '/client/actions/quick-send.php';
        document.getElementById('placeholderGuide').style.display = 'none';
    }
}

function showFn(i){
    const f=i.files[0];
    if(f){
        document.getElementById('fn').textContent='✅ '+f.name;
    }
}

function handleDrop(e){
    e.preventDefault();
    const f=e.dataTransfer.files[0];
    if(f && f.name.endsWith('.csv')){
        const i=document.getElementById('csvFile');
        const dt=new DataTransfer();
        dt.items.add(f);
        i.files=dt.files;
        showFn(i);
    }
}

const ta = document.getElementById('smsMsg');
if (ta) {
  ta.addEventListener('input', () => {
    const l = ta.value.length;
    const s = Math.ceil(l/160) || 1;
    document.getElementById('chars').textContent = l;
    document.getElementById('segs').textContent = s;
    document.getElementById('cost').textContent = s;
  });
}
</script>
JS;
include __DIR__ . '/../includes/layout-footer.php';
?>
