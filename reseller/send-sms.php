<?php
$pageTitle = 'Send SMS';
$breadcrumb = [['label'=>'Reseller'],['label'=>'Send SMS']];
require_once __DIR__ . '/layout.php';

$uid      = $user['id'];
$senderIds= DB::query("SELECT sender_id FROM sender_ids WHERE user_id=? AND status='approved'",[$uid]);
$groups   = DB::query("SELECT id,name FROM contact_groups WHERE user_id=?",[$uid]);
$units    = $user['sms_units'];
?>

<div class="page-header">
  <div><h1>Send SMS</h1><div class="subtitle">Send a single message or broadcast to contacts</div></div>
  <div style="background:var(--primary-light);border:1px solid var(--primary);padding:8px 18px;border-radius:var(--radius-md)">
    <span style="font-size:12px;color:var(--primary);font-weight:600">Balance:</span>
    <strong style="font-size:16px;color:var(--primary);margin-left:6px"><?=number_format($units,2)?></strong> units
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:22px;align-items:start">
  <div class="card">
    <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-paper-plane" style="color:var(--primary)"></i> Compose Message</h3></div>
    <div class="card-body">
      <form method="POST" action="/reseller/actions/quick-send.php">
        <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">

        <div class="form-group">
          <label class="form-label">Sender ID <span class="required">*</span></label>
          <select name="sender_id" class="form-control" required>
            <option value="">-- Select Sender ID --</option>
            <?php foreach ($senderIds as $s): ?>
              <option value="<?=htmlspecialchars($s['sender_id'])?>"><?=htmlspecialchars($s['sender_id'])?></option>
            <?php endforeach; ?>
          </select>
          <?php if (empty($senderIds)): ?>
            <div class="form-hint"><a href="/reseller/sender-ids.php?new=1">Request a Sender ID first →</a></div>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label">Recipient(s)</label>
          <div class="tabs">
            <button type="button" class="tab-btn active" onclick="switchTab(this,'rs-single')">Single</button>
            <button type="button" class="tab-btn" onclick="switchTab(this,'rs-multi')">Multiple</button>
            <button type="button" class="tab-btn" onclick="switchTab(this,'rs-group')">Group</button>
          </div>
          <div class="tab-panel active" id="rs-single">
            <input type="text" name="recipient" class="form-control" placeholder="+254712345678">
          </div>
          <div class="tab-panel" id="rs-multi">
            <textarea name="numbers" class="form-control" rows="3" placeholder="+254712345678, +254798765432&#10;One per line or comma-separated"></textarea>
          </div>
          <div class="tab-panel" id="rs-group">
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
          <textarea name="message" id="rsMsgArea" class="form-control" placeholder="Type your SMS message..." maxlength="918" required></textarea>
          <div class="sms-counter"><span id="rsChars">0</span>/160 · <span id="rsSegs">1</span> SMS · Est: <strong id="rsCost" style="color:var(--primary)">1</strong> unit/recipient</div>
        </div>

        <div class="form-group">
          <label class="form-label">Schedule (optional)</label>
          <input type="datetime-local" name="scheduled_at" class="form-control">
          <div class="form-hint">Leave empty to send immediately</div>
        </div>

        <button type="submit" class="btn btn-primary btn-full btn-lg" <?=empty($senderIds)?'disabled':''?>>
          <i class="fa-solid fa-paper-plane"></i> Send SMS
        </button>
      </form>
    </div>
  </div>

  <!-- Info Panel -->
  <div style="display:flex;flex-direction:column;gap:16px">
    <div class="card">
      <div class="card-body">
        <h4 style="font-size:14px;font-weight:700;margin-bottom:14px"><i class="fa-solid fa-circle-info" style="color:var(--info)"></i> SMS Info</h4>
        <div style="display:flex;flex-direction:column;gap:10px;font-size:13px">
          <div style="display:flex;justify-content:space-between"><span class="text-muted">Max chars:</span><strong>160 / SMS</strong></div>
          <div style="display:flex;justify-content:space-between"><span class="text-muted">Max parts:</span><strong>6 parts</strong></div>
          <div style="display:flex;justify-content:space-between"><span class="text-muted">Cost per part:</span><strong>1 unit</strong></div>
          <div style="display:flex;justify-content:space-between"><span class="text-muted">Your balance:</span><strong style="color:var(--primary)"><?=number_format($units,2)?> units</strong></div>
        </div>
      </div>
    </div>
    <div class="card">
      <div class="card-body">
        <h4 style="font-size:14px;font-weight:700;margin-bottom:12px"><i class="fa-solid fa-lightbulb" style="color:var(--warning)"></i> Tips</h4>
        <ul style="font-size:12.5px;color:var(--text-secondary);display:flex;flex-direction:column;gap:8px;list-style:disc;padding-left:16px">
          <li>Keep under 160 chars to use 1 unit per recipient</li>
          <li>Use groups for broadcast campaigns</li>
          <li>Schedule during business hours (8AM–8PM)</li>
          <li>Use Campaigns for large batches with reports</li>
        </ul>
      </div>
    </div>
    <?php if ($units < 10): ?>
      <div class="alert alert-warning"><i class="fa-solid fa-coins"></i> Low balance. <a href="/reseller/purchases.php" style="font-weight:700">Buy more units →</a></div>
    <?php endif; ?>
  </div>
</div>

<?php
$extraScript = <<<'JS'
<script>
const ta = document.getElementById('rsMsgArea');
if (ta) {
  ta.addEventListener('input', () => {
    const l = ta.value.length, s = Math.ceil(l/160)||1;
    document.getElementById('rsChars').textContent = l;
    document.getElementById('rsSegs').textContent = s;
    document.getElementById('rsCost').textContent = s;
  });
}
</script>
JS;
include __DIR__ . '/../includes/layout-footer.php';
?>
