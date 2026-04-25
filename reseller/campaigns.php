<?php
$pageTitle = 'Campaigns';
$breadcrumb = [['label'=>'Reseller'],['label'=>'Campaigns']];
require_once __DIR__ . '/layout.php';

$uid  = $user['id'];
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;
$status  = sanitize($_GET['status'] ?? '');
$search  = sanitize($_GET['q'] ?? '');
$view    = sanitize($_GET['view'] ?? 'campaigns');

$where   = ($view === 'history') ? 'WHERE user_id = ?' : 'WHERE c.user_id = ?';
$params  = [$uid];

if ($status) { $where .= ' AND status = ?'; $params[] = $status; }
if ($search)  { $where .= ($view === 'history' ? ' AND recipient LIKE ?' : ' AND c.name LIKE ?'); $params[] = "%$search%"; }

if ($view === 'history') {
    $total     = DB::queryOne("SELECT COUNT(*) as c FROM messages $where", $params)['c'] ?? 0;
    $items     = DB::query("SELECT * FROM messages $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset", $params);
} else {
    $total     = DB::queryOne("SELECT COUNT(*) as c FROM campaigns c $where", $params)['c'] ?? 0;
    $items     = DB::query("SELECT c.* FROM campaigns c $where ORDER BY c.created_at DESC LIMIT $perPage OFFSET $offset", $params);
}
$totalPages= ceil($total / $perPage);

// Sender IDs for the create modal
$senderIds = DB::query("SELECT sender_id FROM sender_ids WHERE user_id=? AND status='approved'", [$uid]);
$groups    = DB::query("SELECT id, name FROM contact_groups WHERE user_id=?", [$uid]);
?>

<div class="page-header">
  <div>
    <h1>Campaigns</h1>
    <div class="subtitle">Create and manage your bulk SMS campaigns</div>
  </div>
  <button class="btn btn-primary" onclick="openModal('campaignModal')">
    <i class="fa-solid fa-plus"></i> New Campaign
  </button>
</div>

<!-- Main Tabs -->
<div class="tabs" style="margin-bottom:12px">
  <a href="?view=campaigns" class="tab-btn <?= $view==='campaigns'?'active':'' ?>"><i class="fa-solid fa-bullhorn"></i> Bulk Campaigns</a>
  <a href="?view=history" class="tab-btn <?= $view==='history'?'active':'' ?>"><i class="fa-solid fa-history"></i> Sent History (Single SMS)</a>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom:20px">
  <div class="card-body" style="padding:14px 18px">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
      <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
      <div class="input-group" style="flex:1;min-width:200px">
        <div class="input-group-text input-addon-left"><i class="fa-solid fa-magnifying-glass"></i></div>
        <input type="text" name="q" class="form-control with-left" placeholder="<?= $view==='campaigns'?'Search campaigns...':'Search numbers...' ?>" value="<?= htmlspecialchars($search) ?>">
      </div>
      <select name="status" class="form-control" style="width:160px">
        <option value="">All Statuses</option>
        <?php if ($view === 'campaigns'): ?>
          <?php foreach (['draft','queued','sending','completed','failed'] as $s): ?>
            <option value="<?=$s?>" <?=$status===$s?'selected':''?>><?=ucfirst($s)?></option>
          <?php endforeach; ?>
        <?php else: ?>
          <?php foreach (['sent','delivered','failed','queued'] as $s): ?>
            <option value="<?=$s?>" <?=$status===$s?'selected':''?>><?=ucfirst($s)?></option>
          <?php endforeach; ?>
        <?php endif; ?>
      </select>
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
      <?php if ($status || $search): ?>
        <a href="/reseller/campaigns.php?view=<?=$view?>" class="btn btn-secondary">Clear</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title">
        <i class="fa-solid fa-<?= $view==='campaigns'?'bullhorn':'history' ?>" style="color:var(--primary)"></i> 
        <?= $view==='campaigns'?'All Campaigns':'Message History' ?> 
        <span class="badge badge-muted"><?= $total ?></span>
    </h3>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <?php if ($view === 'campaigns'): ?>
        <thead>
          <tr><th>Campaign Name</th><th>Sender ID</th><th>Total</th><th>Sent</th><th>Delivered</th><th>Failed</th><th>Units</th><th>Status</th><th>Created</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php if (empty($items)): ?>
            <tr><td colspan="10"><div class="empty-state"><div class="empty-icon">📢</div><h3>No campaigns found</h3><p>Create your first campaign to start sending bulk SMS.</p><button class="btn btn-primary" onclick="openModal('campaignModal')">+ New Campaign</button></div></td></tr>
          <?php else: ?>
            <?php foreach ($items as $c): ?>
              <?php
                $sc  = ['completed'=>'success','sending'=>'info','queued'=>'warning','failed'=>'danger','draft'=>'muted'][$c['status']] ?? 'muted';
                $dlv = $c['total_count'] > 0 ? round($c['sent_count'] / max($c['total_count'],1) * 100) : 0;
              ?>
              <tr>
                <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                <td><code><?= htmlspecialchars($c['sender_id']) ?></code></td>
                <td><?= number_format($c['total_count']) ?></td>
                <td style="color:var(--success)"><?= number_format($c['sent_count']) ?></td>
                <td>
                  <div style="display:flex;align-items:center;gap:8px">
                    <div class="progress" style="width:60px"><div class="progress-bar" style="width:<?=$dlv?>%"></div></div>
                    <span style="font-size:11px;color:var(--text-secondary)"><?=$dlv?>%</span>
                  </div>
                </td>
                <td style="color:var(--danger)"><?= number_format($c['failed_count']) ?></td>
                <td><?= number_format($c['units_used'],2) ?></td>
                <td><span class="badge badge-<?=$sc?>"><?=ucfirst($c['status'])?></span></td>
                <td style="font-size:12px"><?= date('d M Y',strtotime($c['created_at'])) ?></td>
                <td>
                  <div class="btn-group">
                    <a href="/reseller/campaigns.php?view=campaigns&view_details=<?=$c['id']?>" class="btn btn-secondary btn-sm btn-icon" title="View"><i class="fa-solid fa-eye"></i></a>
                    <?php if ($c['status']==='draft'): ?>
                      <a href="/reseller/campaigns.php?view=campaigns&edit=<?=$c['id']?>" class="btn btn-outline btn-sm btn-icon" title="Edit"><i class="fa-solid fa-pen"></i></a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      <?php else: ?>
        <thead>
          <tr><th>Recipient</th><th>Sender ID</th><th>Message</th><th>Units</th><th>Status</th><th>Sent At</th></tr>
        </thead>
        <tbody>
          <?php if (empty($items)): ?>
            <tr><td colspan="6"><div class="empty-state"><div class="empty-icon">✉️</div><h3>No messages found</h3><p>You haven't sent any single SMS yet.</p></div></td></tr>
          <?php else: ?>
            <?php foreach ($items as $m): ?>
              <?php $mc=['sent'=>'success','delivered'=>'success','failed'=>'danger','queued'=>'warning'][$m['status']]??'muted'; ?>
              <tr>
                <td><strong><?=htmlspecialchars($m['recipient'])?></strong></td>
                <td><code><?=htmlspecialchars($m['sender_id'])?></code></td>
                <td style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?=htmlspecialchars($m['message'])?>"><?=htmlspecialchars($m['message'])?></td>
                <td><?=$m['units_charged']?></td>
                <td><span class="badge badge-<?=$mc?>"><?=ucfirst($m['status'])?></span></td>
                <td style="font-size:12px"><?=date('d M Y H:i',strtotime($m['created_at']))?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      <?php endif; ?>
    </table>
  </div>
  <?php if ($totalPages > 1): ?>
    <div class="card-footer">
      <div class="pagination">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <a href="?page=<?=$p?>&view=<?=$view?>&status=<?=$status?>&q=<?=urlencode($search)?>" class="page-btn <?=$p===$page?'active':''?>"><?=$p?></a>
        <?php endfor; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- Create Campaign Modal -->
<div class="modal-overlay" id="campaignModal">
  <div class="modal" style="max-width:620px">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fa-solid fa-bullhorn" style="color:var(--primary)"></i> Create New Campaign</h3>
      <button class="modal-close" onclick="closeModal('campaignModal')">×</button>
    </div>
    <form method="POST" action="/reseller/actions/create-campaign.php" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Campaign Name <span class="required">*</span></label>
          <input type="text" name="name" class="form-control" placeholder="e.g. July Promo" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Sender ID <span class="required">*</span></label>
            <select name="sender_id" class="form-control" required>
              <option value="">-- Select --</option>
              <?php foreach ($senderIds as $s): ?>
                <option value="<?= htmlspecialchars($s['sender_id']) ?>"><?= htmlspecialchars($s['sender_id']) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (empty($senderIds)): ?>
              <div class="form-hint"><a href="/reseller/sender-ids.php?new=1">Request a Sender ID first →</a></div>
            <?php endif; ?>
          </div>
          <div class="form-group">
            <label class="form-label">Schedule (optional)</label>
            <input type="datetime-local" name="scheduled_at" class="form-control">
            <div class="form-hint">Leave blank to send immediately after saving</div>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Recipients</label>
          <div class="tabs">
            <button type="button" class="tab-btn active" onclick="switchTab(this,'tab-group')">Contact Group</button>
            <button type="button" class="tab-btn" onclick="switchTab(this,'tab-numbers')">Enter Numbers</button>
            <button type="button" class="tab-btn" onclick="switchTab(this,'tab-upload')">Upload CSV</button>
          </div>
          <div class="tab-panel active" id="tab-group">
            <select name="group_id" class="form-control">
              <option value="">-- Select Group --</option>
              <?php foreach ($groups as $g): ?>
                <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="tab-panel" id="tab-numbers">
            <textarea name="numbers" class="form-control" placeholder="Enter phone numbers separated by commas or new lines&#10;+254712345678, +254798765432" rows="4"></textarea>
          </div>
          <div class="tab-panel" id="tab-upload">
            <div class="upload-zone" id="uploadZone" onclick="document.getElementById('csvFile').click()">
              <div class="upload-icon">📄</div>
              <p>Click or drag a <strong>CSV file</strong> here</p>
              <p style="font-size:11px;margin-top:4px">One phone number per row</p>
            </div>
            <input type="file" name="csv_file" id="csvFile" accept=".csv" style="display:none" onchange="document.getElementById('uploadZone').querySelector('p').textContent='File: '+this.files[0].name">
          </div>
        </div>
        <div class="form-group sms-composer">
          <label class="form-label">Message <span class="required">*</span></label>
          <textarea name="message" class="form-control" id="campMsg" placeholder="Type your SMS message..." required maxlength="918"></textarea>
          <div class="sms-counter"><span id="cmpChar">0</span>/160 · <span id="cmpSeg">1</span> SMS · Est. cost: <span id="cmpCost">0</span> units/recipient</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('campaignModal')">Cancel</button>
        <button type="submit" name="save" value="draft" class="btn btn-outline">Save as Draft</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Create & Send</button>
      </div>
    </form>
  </div>
</div>

<?php
$openModalJs = !empty($_GET['new']) ? "openModal('campaignModal');" : "";
$extraScript = <<<JS
<script>
(function() {
  const campMsg = document.getElementById('campMsg');
  if (campMsg) {
    campMsg.addEventListener('input', function() {
      const l = this.value.length;
      const s = Math.ceil(l/160)||1;
      document.getElementById('cmpChar').textContent = l;
      document.getElementById('cmpSeg').textContent  = s;
      document.getElementById('cmpCost').textContent = s;
    });
  }
  $openModalJs
})();
</script>
JS;
include __DIR__ . '/../includes/layout-footer.php';
?>
