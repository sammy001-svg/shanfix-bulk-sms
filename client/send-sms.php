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
      <form method="POST" action="/client/actions/quick-send.php" id="sendForm">
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
          <label class="form-label">Message <span class="required">*</span></label>
          <textarea name="message" id="smsMsg" class="form-control" placeholder="Type your SMS message here..." maxlength="918" required></textarea>
          <div class="sms-counter"><span id="chars">0</span>/160 · <span id="segs">1</span> SMS part(s) · Est. cost: <strong id="cost" style="color:var(--primary)">1</strong> unit/recipient</div>
          
          <!-- Personalization Guide (hidden by default) -->
          <div id="personalizationGuide" style="display:none; margin-top:15px; padding:15px; background:rgba(255,255,255,0.03); border:1px dashed var(--border); border-radius:var(--radius-sm)">
            <div style="font-size:12px; font-weight:700; color:var(--primary); margin-bottom:8px; text-transform:uppercase; letter-spacing:0.05em">
              <i class="fa-solid fa-wand-magic-sparkles"></i> Personalization Guide
            </div>
            <div style="font-size:12px; color:var(--text-muted); line-height:1.5">
              Use headers from your imported CSV as placeholders. For example:
              <div style="margin-top:8px; display:flex; flex-wrap:wrap; gap:8px">
                <code style="background:var(--bg-muted); padding:2px 6px; border-radius:4px; color:var(--primary)">{name}</code>
                <code style="background:var(--bg-muted); padding:2px 6px; border-radius:4px; color:var(--primary)">{amount}</code>
                <code style="background:var(--bg-muted); padding:2px 6px; border-radius:4px; color:var(--primary)">{date}</code>
                <code style="background:var(--bg-muted); padding:2px 6px; border-radius:4px; color:var(--primary)">{balance}</code>
              </div>
              <div style="margin-top:8px; font-style:italic">"Hello {name}, your balance is {balance}."</div>
            </div>
          </div>
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
    const parent = btn.closest('.form-group');
    parent.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    parent.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(tabId).classList.add('active');
    document.getElementById('sendMode').value = mode;

    // Show/hide personalization guide for group mode
    const guide = document.getElementById('personalizationGuide');
    if (guide) {
        guide.style.display = (mode === 'group') ? 'block' : 'none';
    }
}

const ta = document.getElementById('smsMsg');
if (ta) {
  const updateCounter = () => {
    const text = ta.value;
    const l = text.length;
    
    // Standard SMS is 160 chars. 
    // Note: If multi-part (concatenated), most gateways use 153 chars per part.
    // However, per user request, we enforce a strict 160/part rule.
    const parts = Math.ceil(l / 160) || 1;
    
    const charEl = document.getElementById('chars');
    const segEl  = document.getElementById('segs');
    const costEl = document.getElementById('cost');
    
    charEl.textContent = l;
    segEl.textContent = parts;
    costEl.textContent = parts;

    // Visual feedback
    if (l > 160) {
        charEl.style.color = 'var(--warning)';
        segEl.style.color = 'var(--warning)';
    } else {
        charEl.style.color = 'inherit';
        segEl.style.color = 'inherit';
    }
  };

  ta.addEventListener('input', updateCounter);
  // Initial run in case there's default text (e.g. from drafts)
  updateCounter();
}
</script>
JS;
include __DIR__ . '/../includes/layout-footer.php';
?>
