<?php
$pageTitle  = 'Client Detail';
$breadcrumb = [['label'=>'Reseller'],['label'=>'My Clients','url'=>'/reseller/clients.php'],['label'=>'Profile']];
require_once __DIR__ . '/layout.php';

$uid = $user['id'];
$cid = (int)($_GET['id'] ?? 0);

// Ensure this client belongs to the current reseller
$c = DB::queryOne(
    "SELECT * FROM users WHERE id = ? AND parent_id = ? AND role = 'client'",
    [$cid, $uid]
);
if (!$c) {
    flash_set('danger', 'Client not found.');
    redirect('/reseller/clients.php');
}

// ── Summary stats ───────────────────────────────────────────────
$stats = DB::queryOne(
    "SELECT COUNT(DISTINCT id) as campaigns,
            COALESCE(SUM(sent_count),0)   as msgs_sent,
            COALESCE(SUM(failed_count),0) as msgs_failed,
            COALESCE(SUM(units_used),0)   as units_used
     FROM campaigns WHERE user_id = ?", [$cid]
);
$purchStats = DB::queryOne(
    "SELECT COUNT(*) as total_purchases,
            COALESCE(SUM(CASE WHEN status='completed' THEN amount ELSE 0 END),0) as total_spent,
            SUM(status='pending') as pending_purchases
     FROM purchases WHERE user_id = ?", [$cid]
);

// ── Recent campaigns ────────────────────────────────────────────
$campaigns = DB::query(
    "SELECT * FROM campaigns WHERE user_id = ? ORDER BY created_at DESC LIMIT 8", [$cid]
);

// ── Message history (paginated) ─────────────────────────────────
$msgPage   = max(1, (int)($_GET['msg_page'] ?? 1));
$msgPer    = 15;
$msgTotal  = (int)(DB::queryOne("SELECT COUNT(*) as c FROM messages WHERE user_id = ?", [$cid])['c'] ?? 0);
$msgPages  = max(1, (int)ceil($msgTotal / $msgPer));
$msgOffset = ($msgPage - 1) * $msgPer;
$messages  = DB::query(
    "SELECT * FROM messages WHERE user_id = ? ORDER BY created_at DESC LIMIT $msgPer OFFSET $msgOffset",
    [$cid]
);

// ── Purchases ───────────────────────────────────────────────────
$purchases = DB::query(
    "SELECT * FROM purchases WHERE user_id = ? ORDER BY created_at DESC LIMIT 10", [$cid]
);

// ── Sender IDs ──────────────────────────────────────────────────
$senderIds = DB::query(
    "SELECT * FROM sender_ids WHERE user_id = ? ORDER BY created_at DESC", [$cid]
);

// ── Allocation history from this reseller ──────────────────────
$allocations = DB::query(
    "SELECT a.*, uf.name as from_name FROM unit_allocations a
     JOIN users uf ON a.from_user = uf.id
     WHERE a.to_user = ? ORDER BY a.created_at DESC LIMIT 15", [$cid]
);

$pageTitle  = 'Client: ' . htmlspecialchars($c['name']);
$breadcrumb = [['label'=>'Reseller'],['label'=>'My Clients','url'=>'/reseller/clients.php'],['label'=>htmlspecialchars($c['name'])]];
$statusColor = ['active'=>'success','suspended'=>'danger','pending'=>'warning'][$c['status']] ?? 'muted';
?>

<div class="page-header">
  <div>
    <h1 style="display:flex;align-items:center;gap:12px">
      <div style="width:42px;height:42px;border-radius:50%;background:var(--primary);color:#000;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:800;flex-shrink:0">
        <?= strtoupper(substr($c['name'], 0, 1)) ?>
      </div>
      <?= htmlspecialchars($c['name']) ?>
      <span class="badge badge-<?= $statusColor ?>"><?= ucfirst($c['status']) ?></span>
    </h1>
    <div class="subtitle"><?= htmlspecialchars($c['email']) ?> · Joined <?= date('d M Y', strtotime($c['created_at'])) ?></div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <button class="btn btn-primary" onclick="openAllocate(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['name'])) ?>', <?= $c['sms_units'] ?>)">
      <i class="fa-solid fa-coins"></i> Transfer Units
    </button>
    <button class="btn btn-outline" onclick="openEditRate(<?= htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8') ?>)">
      <i class="fa-solid fa-pen"></i> Edit Rate
    </button>
    <form method="POST" action="/reseller/actions/toggle-client-status.php" style="display:inline"
          onsubmit="return confirm('<?= $c['status']==='active' ? 'Suspend' : 'Activate' ?> this client?')">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="id" value="<?= $c['id'] ?>">
      <button type="submit" class="btn <?= $c['status']==='active' ? 'btn-danger' : 'btn-primary' ?>">
        <i class="fa-solid <?= $c['status']==='active' ? 'fa-ban' : 'fa-check' ?>"></i>
        <?= $c['status']==='active' ? 'Suspend' : 'Activate' ?>
      </button>
    </form>
  </div>
</div>

<!-- Profile + Stats -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">

  <div class="card">
    <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-circle-info" style="color:var(--primary)"></i> Account Info</h3></div>
    <div class="card-body" style="padding:0">
      <table style="width:100%;border-collapse:collapse">
        <?php foreach ([
          ['Email',    htmlspecialchars($c['email'])],
          ['Phone',    htmlspecialchars($c['phone'] ?? '—')],
          ['Company',  htmlspecialchars($c['company'] ?? '—')],
          ['Status',   ucfirst($c['status'])],
          ['SMS Rate', 'KES ' . number_format($c['custom_unit_price'] ?? 1.00, 4) . ' / unit'],
          ['Last Login',$c['last_login'] ? date('d M Y H:i', strtotime($c['last_login'])) : '—'],
          ['Joined',   date('d M Y', strtotime($c['created_at']))],
        ] as [$label, $val]): ?>
          <tr style="border-bottom:1px solid var(--border)">
            <td style="padding:9px 16px;font-size:12px;font-weight:600;color:var(--text-secondary);width:120px"><?= $label ?></td>
            <td style="padding:9px 16px;font-size:13px"><?= $val ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>

  <div class="stats-grid" style="grid-template-columns:1fr 1fr;gap:12px;align-content:start;margin:0">
    <div class="stat-card" style="margin:0">
      <div class="stat-icon green"><i class="fa-solid fa-coins"></i></div>
      <div class="stat-info"><div class="stat-label">SMS Balance</div><div class="stat-value"><?= number_format($c['sms_units'], 2) ?></div></div>
    </div>
    <div class="stat-card" style="margin:0">
      <div class="stat-icon orange"><i class="fa-solid fa-bullhorn"></i></div>
      <div class="stat-info"><div class="stat-label">Campaigns</div><div class="stat-value"><?= number_format($stats['campaigns'] ?? 0) ?></div></div>
    </div>
    <div class="stat-card" style="margin:0">
      <div class="stat-icon blue"><i class="fa-solid fa-paper-plane"></i></div>
      <div class="stat-info"><div class="stat-label">Msgs Sent</div><div class="stat-value"><?= number_format($stats['msgs_sent'] ?? 0) ?></div></div>
    </div>
    <div class="stat-card" style="margin:0">
      <div class="stat-icon orange"><i class="fa-solid fa-coins"></i></div>
      <div class="stat-info"><div class="stat-label">Units Used</div><div class="stat-value"><?= number_format($stats['units_used'] ?? 0, 2) ?></div></div>
    </div>
    <div class="stat-card" style="margin:0">
      <div class="stat-icon green"><i class="fa-solid fa-money-bill-wave"></i></div>
      <div class="stat-info"><div class="stat-label">Total Spent</div><div class="stat-value">KES <?= number_format($purchStats['total_spent'] ?? 0, 0) ?></div></div>
    </div>
    <div class="stat-card" style="margin:0">
      <div class="stat-icon orange"><i class="fa-solid fa-receipt"></i></div>
      <div class="stat-info">
        <div class="stat-label">Purchases</div>
        <div class="stat-value"><?= number_format($purchStats['total_purchases'] ?? 0) ?></div>
        <?php if (($purchStats['pending_purchases']??0) > 0): ?>
          <div class="stat-trend" style="color:var(--warning)"><?= $purchStats['pending_purchases'] ?> pending</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Campaigns + Sender IDs -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:24px">
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="fa-solid fa-bullhorn" style="color:var(--primary)"></i> Recent Campaigns</h3>
    </div>
    <div class="table-wrapper">
      <table class="data-table">
        <thead><tr><th>Name</th><th>Sender</th><th>Sent</th><th>Failed</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
          <?php if (empty($campaigns)): ?>
            <tr><td colspan="6" class="text-center text-muted" style="padding:24px">No campaigns yet</td></tr>
          <?php else: foreach ($campaigns as $camp):
            $cs = ['draft'=>'muted','scheduled'=>'warning','queued'=>'warning','sending'=>'info','running'=>'info','completed'=>'success','failed'=>'danger'][$camp['status']]??'muted';
          ?>
            <tr>
              <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><strong><?= htmlspecialchars($camp['name']) ?></strong></td>
              <td><code style="font-size:11px"><?= htmlspecialchars($camp['sender_id']) ?></code></td>
              <td style="color:var(--success)"><?= number_format($camp['sent_count']) ?></td>
              <td style="color:var(--danger)"><?= number_format($camp['failed_count']) ?></td>
              <td><span class="badge badge-<?= $cs ?>"><?= ucfirst($camp['status']) ?></span></td>
              <td style="font-size:11px"><?= date('d M Y', strtotime($camp['created_at'])) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-id-badge" style="color:var(--primary)"></i> Sender IDs</h3></div>
    <div class="card-body" style="padding:0">
      <?php if (empty($senderIds)): ?>
        <div class="empty-state" style="padding:24px 16px"><div class="empty-icon">🪪</div><p>No sender IDs</p></div>
      <?php else: foreach ($senderIds as $s):
        $ss = ['approved'=>'success','pending'=>'warning','rejected'=>'danger'][$s['status']]??'muted';
      ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 16px;border-bottom:1px solid var(--border)">
          <div>
            <code style="font-size:13px;font-weight:700"><?= htmlspecialchars($s['sender_id']) ?></code>
            <div style="font-size:10px;color:var(--text-secondary);margin-top:1px"><?= date('d M Y', strtotime($s['created_at'])) ?></div>
          </div>
          <span class="badge badge-<?= $ss ?>"><?= ucfirst($s['status']) ?></span>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<!-- Messages + Purchases + Allocations -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:24px">
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="fa-solid fa-envelope-open-text" style="color:var(--primary)"></i> Message History <span class="badge badge-muted"><?= number_format($msgTotal) ?></span></h3>
    </div>
    <div class="table-wrapper">
      <table class="data-table">
        <thead><tr><th>Recipient</th><th>Sender ID</th><th>Message</th><th>Units</th><th>Status</th><th>Sent At</th></tr></thead>
        <tbody>
          <?php if (empty($messages)): ?>
            <tr><td colspan="6" class="text-center text-muted" style="padding:24px">No messages yet</td></tr>
          <?php else: foreach ($messages as $m):
            $ms = ['sent'=>'success','delivered'=>'success','failed'=>'danger','queued'=>'warning','undelivered'=>'warning'][$m['status']]??'muted';
          ?>
            <tr>
              <td style="font-size:13px"><?= htmlspecialchars($m['recipient']) ?></td>
              <td><code style="font-size:11px"><?= htmlspecialchars($m['sender_id']) ?></code></td>
              <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;color:var(--text-secondary)" title="<?= htmlspecialchars($m['message']) ?>">
                <?= htmlspecialchars(mb_strimwidth($m['message'], 0, 55, '…')) ?>
              </td>
              <td><?= $m['units_charged'] ?></td>
              <td><span class="badge badge-<?= $ms ?>"><?= ucfirst($m['status']) ?></span></td>
              <td style="font-size:11px"><?= $m['sent_at'] ? date('d M Y H:i', strtotime($m['sent_at'])) : '—' ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($msgPages > 1): ?>
      <div class="card-footer"><div class="pagination">
        <?php for ($p = 1; $p <= $msgPages; $p++): ?>
          <a href="?id=<?= $cid ?>&msg_page=<?= $p ?>" class="page-btn <?= $p===$msgPage?'active':'' ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div></div>
    <?php endif; ?>
  </div>

  <div style="display:flex;flex-direction:column;gap:20px">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-receipt" style="color:var(--primary)"></i> Purchases</h3>
      </div>
      <div class="table-wrapper">
        <table class="data-table">
          <thead><tr><th>Units</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
            <?php if (empty($purchases)): ?>
              <tr><td colspan="4" class="text-center text-muted" style="padding:18px">No purchases</td></tr>
            <?php else: foreach ($purchases as $p):
              $ps = ['completed'=>'success','pending'=>'warning','failed'=>'danger','refunded'=>'muted'][$p['status']]??'muted';
            ?>
              <tr>
                <td><?= number_format($p['units']) ?></td>
                <td style="font-weight:600;color:var(--primary)">KES <?= number_format($p['amount'],2) ?></td>
                <td><span class="badge badge-<?= $ps ?>"><?= ucfirst($p['status']) ?></span></td>
                <td style="font-size:11px"><?= date('d M Y', strtotime($p['created_at'])) ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-history" style="color:var(--primary)"></i> Unit Allocations</h3></div>
      <div class="table-wrapper">
        <table class="data-table">
          <thead><tr><th>From</th><th>Units</th><th>Note</th><th>Date</th></tr></thead>
          <tbody>
            <?php if (empty($allocations)): ?>
              <tr><td colspan="4" class="text-center text-muted" style="padding:18px">No allocations yet</td></tr>
            <?php else: foreach ($allocations as $a): ?>
              <tr>
                <td style="font-size:12px"><?= htmlspecialchars($a['from_name']) ?></td>
                <td><strong style="color:<?= $a['units'] > 0 ? 'var(--success)' : 'var(--danger)' ?>"><?= ($a['units']>0?'+':'') . number_format($a['units'],2) ?></strong></td>
                <td style="font-size:11px;color:var(--text-secondary)"><?= htmlspecialchars($a['note'] ?? '—') ?></td>
                <td style="font-size:11px"><?= date('d M Y', strtotime($a['created_at'])) ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Transfer Units Modal -->
<div class="modal-overlay" id="allocateModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fa-solid fa-coins" style="color:var(--primary)"></i> Transfer Units</h3>
      <button class="modal-close" onclick="closeModal('allocateModal')">×</button>
    </div>
    <form method="POST" action="/reseller/actions/allocate-units.php">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="to_user" id="allocUserId">
      <div class="modal-body">
        <div style="background:var(--bg-muted);padding:12px;border-radius:var(--radius-md);margin-bottom:16px;font-size:13px">
          Client: <strong id="allocName">—</strong> · Balance: <strong id="allocUnits" style="color:var(--primary)">—</strong><br>
          Your balance: <strong style="color:var(--primary)"><?= number_format($user['sms_units'], 2) ?></strong> units
        </div>
        <div class="form-group">
          <label class="form-label">Units to Transfer <span class="required">*</span></label>
          <input type="number" name="units" class="form-control" min="1" max="<?= floor($user['sms_units']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Note</label>
          <input type="text" name="note" class="form-control" placeholder="Optional note...">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('allocateModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-coins"></i> Transfer</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Rate Modal -->
<div class="modal-overlay" id="editRateModal">
  <div class="modal" style="max-width:400px">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fa-solid fa-pen" style="color:var(--primary)"></i> Edit Client Rate</h3>
      <button class="modal-close" onclick="closeModal('editRateModal')">×</button>
    </div>
    <form method="POST" action="/reseller/actions/edit-client.php">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="id" id="editClientId">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">SMS Rate (Per Unit) <span class="required">*</span></label>
          <input type="number" name="custom_unit_price" id="editRate" class="form-control" step="0.01" min="0.10" required>
          <div class="form-hint">Cost the client pays for 1 SMS unit.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('editRateModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save</button>
      </div>
    </form>
  </div>
</div>

<?php
$extraScript = <<<'JS'
<script>
function openAllocate(id, name, units) {
    document.getElementById('allocUserId').value = id;
    document.getElementById('allocName').textContent = name;
    document.getElementById('allocUnits').textContent = parseFloat(units).toLocaleString();
    openModal('allocateModal');
}
function openEditRate(client) {
    document.getElementById('editClientId').value = client.id;
    document.getElementById('editRate').value = client.custom_unit_price || 1.00;
    openModal('editRateModal');
}
</script>
JS;
include __DIR__ . '/../includes/layout-footer.php';
?>
