<?php
$pageTitle  = 'User Detail';
$breadcrumb = [['label'=>'Admin'],['label'=>'Users','url'=>'/admin/resellers.php'],['label'=>'Profile']];
require_once __DIR__ . '/layout.php';

$id = (int)($_GET['id'] ?? 0);
$u  = DB::queryOne("SELECT u.*, p.name as parent_name, p.email as parent_email
                    FROM users u LEFT JOIN users p ON u.parent_id = p.id
                    WHERE u.id = ? AND u.role != 'admin'", [$id]);
if (!$u) {
    flash_set('danger', 'User not found.');
    redirect('/admin/resellers.php');
}

// ── Activity summary ────────────────────────────────────────────
$stats = DB::queryOne(
    "SELECT COUNT(DISTINCT c.id)    as campaigns,
            COALESCE(SUM(c.sent_count),0)   as msgs_sent,
            COALESCE(SUM(c.failed_count),0) as msgs_failed,
            COALESCE(SUM(c.units_used),0)   as units_used
     FROM campaigns c WHERE c.user_id = ?", [$id]
);

$purchStats = DB::queryOne(
    "SELECT COUNT(*) as total_purchases,
            COALESCE(SUM(CASE WHEN status='completed' THEN amount ELSE 0 END),0) as total_spent,
            SUM(status='pending') as pending_purchases
     FROM purchases WHERE user_id = ?", [$id]
);

// Reseller: count of sub-clients
$subClients = 0;
if ($u['role'] === 'reseller') {
    $subClients = (int)(DB::queryOne("SELECT COUNT(*) as c FROM users WHERE parent_id = ?", [$id])['c'] ?? 0);
}

// ── Recent campaigns (last 8) ───────────────────────────────────
$campaigns = DB::query(
    "SELECT * FROM campaigns WHERE user_id = ? ORDER BY created_at DESC LIMIT 8", [$id]
);

// ── Recent messages (last 20, paginated) ───────────────────────
$msgPage   = max(1, (int)($_GET['msg_page'] ?? 1));
$msgPer    = 15;
$msgTotal  = (int)(DB::queryOne("SELECT COUNT(*) as c FROM messages WHERE user_id = ?", [$id])['c'] ?? 0);
$msgPages  = max(1, (int)ceil($msgTotal / $msgPer));
$msgOffset = ($msgPage - 1) * $msgPer;
$messages  = DB::query(
    "SELECT * FROM messages WHERE user_id = ? ORDER BY created_at DESC LIMIT $msgPer OFFSET $msgOffset", [$id]
);

// ── Purchases ───────────────────────────────────────────────────
$purchases = DB::query(
    "SELECT * FROM purchases WHERE user_id = ? ORDER BY created_at DESC LIMIT 10", [$id]
);

// ── Sender IDs ──────────────────────────────────────────────────
$senderIds = DB::query(
    "SELECT * FROM sender_ids WHERE user_id = ? ORDER BY created_at DESC", [$id]
);

// ── Unit allocation history ─────────────────────────────────────
$allocations = DB::query(
    "SELECT a.*, uf.name as from_name FROM unit_allocations a
     JOIN users uf ON a.from_user = uf.id
     WHERE a.to_user = ? ORDER BY a.created_at DESC LIMIT 15", [$id]
);

$breadcrumb = [['label'=>'Admin'],['label'=>'Users','url'=>'/admin/resellers.php'],['label'=>htmlspecialchars($u['name'])]];
$pageTitle  = 'Profile: ' . htmlspecialchars($u['name']);
?>

<div class="page-header">
  <div>
    <h1 style="display:flex;align-items:center;gap:12px">
      <div style="width:42px;height:42px;border-radius:50%;background:var(--primary);color:#000;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:800;flex-shrink:0">
        <?= strtoupper(substr($u['name'], 0, 1)) ?>
      </div>
      <?= htmlspecialchars($u['name']) ?>
      <span class="badge <?= $u['role']==='reseller'?'badge-success':'badge-info' ?>"><?= ucfirst($u['role']) ?></span>
      <?php $sc = ['active'=>'success','suspended'=>'danger','pending'=>'warning'][$u['status']]??'muted'; ?>
      <span class="badge badge-<?= $sc ?>"><?= ucfirst($u['status']) ?></span>
    </h1>
    <div class="subtitle"><?= htmlspecialchars($u['email']) ?> · Joined <?= date('d M Y', strtotime($u['created_at'])) ?></div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="/admin/edit-user.php?id=<?= $u['id'] ?>" class="btn btn-secondary"><i class="fa-solid fa-pen"></i> Edit</a>
    <button class="btn btn-primary" onclick="openAllocate(<?= $u['id'] ?>,'<?= htmlspecialchars(addslashes($u['name'])) ?>',<?= $u['sms_units'] ?>)">
      <i class="fa-solid fa-coins"></i> Allocate Units
    </button>
    <?php if ($u['role']==='reseller'): ?>
      <button class="btn btn-outline" onclick="openWhiteLabel(<?=$u['id']?>,'<?=htmlspecialchars(addslashes($u['name']))?>')">
        <i class="fa-solid fa-brush"></i> White-Label
      </button>
    <?php endif; ?>
  </div>
</div>

<!-- Profile + Balance Cards -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">

  <!-- Profile Info -->
  <div class="card">
    <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-circle-info" style="color:var(--primary)"></i> Account Info</h3></div>
    <div class="card-body" style="padding:0">
      <table style="width:100%;border-collapse:collapse">
        <?php
        $rows = [
            ['Email',    htmlspecialchars($u['email'])],
            ['Phone',    htmlspecialchars($u['phone']   ?? '—')],
            ['Company',  htmlspecialchars($u['company'] ?? '—')],
            ['Role',     ucfirst($u['role'])],
            ['Status',   ucfirst($u['status'])],
            ['Parent',   $u['parent_name'] ? htmlspecialchars($u['parent_name']) . ' <span style="font-size:11px;color:var(--text-secondary)">(' . htmlspecialchars($u['parent_email']) . ')</span>' : '<span class="text-muted">Direct (no reseller)</span>'],
            ['SMS Rate', 'KES ' . number_format($u['custom_unit_price'] ?? 1.00, 4) . ' / unit'],
            ['Last Login', $u['last_login'] ? date('d M Y H:i', strtotime($u['last_login'])) : '—'],
            ['Joined',   date('d M Y', strtotime($u['created_at']))],
        ];
        foreach ($rows as [$label, $val]): ?>
          <tr style="border-bottom:1px solid var(--border)">
            <td style="padding:9px 16px;font-size:12px;font-weight:600;color:var(--text-secondary);width:130px"><?= $label ?></td>
            <td style="padding:9px 16px;font-size:13px"><?= $val ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>

  <!-- Stats -->
  <div style="display:flex;flex-direction:column;gap:16px">
    <div class="stats-grid" style="grid-template-columns:1fr 1fr;gap:12px;margin:0">
      <div class="stat-card" style="margin:0">
        <div class="stat-icon green"><i class="fa-solid fa-coins"></i></div>
        <div class="stat-info">
          <div class="stat-label">SMS Balance</div>
          <div class="stat-value"><?= number_format($u['sms_units'], 2) ?></div>
        </div>
      </div>
      <?php if ($u['role'] === 'reseller'): ?>
        <div class="stat-card" style="margin:0">
          <div class="stat-icon blue"><i class="fa-solid fa-users"></i></div>
          <div class="stat-info">
            <div class="stat-label">Sub-Clients</div>
            <div class="stat-value"><?= number_format($subClients) ?></div>
          </div>
        </div>
      <?php else: ?>
        <div class="stat-card" style="margin:0">
          <div class="stat-icon blue"><i class="fa-brands fa-whatsapp"></i></div>
          <div class="stat-info">
            <div class="stat-label">WA Balance</div>
            <div class="stat-value">KES <?= number_format($u['whatsapp_balance'] ?? 0, 2) ?></div>
          </div>
        </div>
      <?php endif; ?>
      <div class="stat-card" style="margin:0">
        <div class="stat-icon orange"><i class="fa-solid fa-bullhorn"></i></div>
        <div class="stat-info">
          <div class="stat-label">Campaigns</div>
          <div class="stat-value"><?= number_format($stats['campaigns'] ?? 0) ?></div>
        </div>
      </div>
      <div class="stat-card" style="margin:0">
        <div class="stat-icon green"><i class="fa-solid fa-paper-plane"></i></div>
        <div class="stat-info">
          <div class="stat-label">Msgs Sent</div>
          <div class="stat-value"><?= number_format($stats['msgs_sent'] ?? 0) ?></div>
        </div>
      </div>
      <div class="stat-card" style="margin:0">
        <div class="stat-icon orange"><i class="fa-solid fa-coins"></i></div>
        <div class="stat-info">
          <div class="stat-label">Units Used</div>
          <div class="stat-value"><?= number_format($stats['units_used'] ?? 0, 2) ?></div>
        </div>
      </div>
      <div class="stat-card" style="margin:0">
        <div class="stat-icon green"><i class="fa-solid fa-money-bill-wave"></i></div>
        <div class="stat-info">
          <div class="stat-label">Total Spent</div>
          <div class="stat-value">KES <?= number_format($purchStats['total_spent'] ?? 0, 0) ?></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Campaigns + Sender IDs -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:24px">

  <!-- Recent Campaigns -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="fa-solid fa-bullhorn" style="color:var(--primary)"></i> Recent Campaigns</h3>
      <a href="/admin/campaigns.php?user_id=<?= $u['id'] ?>" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="table-wrapper">
      <table class="data-table">
        <thead><tr><th>Name</th><th>Sender</th><th>Sent</th><th>Failed</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
          <?php if (empty($campaigns)): ?>
            <tr><td colspan="6" class="text-center text-muted" style="padding:24px">No campaigns yet</td></tr>
          <?php else: foreach ($campaigns as $c):
            $cs = ['draft'=>'muted','scheduled'=>'warning','queued'=>'warning','sending'=>'info','running'=>'info','completed'=>'success','failed'=>'danger'][$c['status']]??'muted';
          ?>
            <tr>
              <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><strong><?= htmlspecialchars($c['name']) ?></strong></td>
              <td><code style="font-size:11px"><?= htmlspecialchars($c['sender_id']) ?></code></td>
              <td style="color:var(--success)"><?= number_format($c['sent_count']) ?></td>
              <td style="color:var(--danger)"><?= number_format($c['failed_count']) ?></td>
              <td><span class="badge badge-<?= $cs ?>"><?= ucfirst($c['status']) ?></span></td>
              <td style="font-size:11px"><?= date('d M Y', strtotime($c['created_at'])) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Sender IDs -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="fa-solid fa-id-badge" style="color:var(--primary)"></i> Sender IDs</h3>
    </div>
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

<!-- Messages + Purchases -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:24px">

  <!-- Message History -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="fa-solid fa-envelope-open-text" style="color:var(--primary)"></i> Message History <span class="badge badge-muted"><?= number_format($msgTotal) ?></span></h3>
      <a href="/admin/reports.php?user_id=<?= $u['id'] ?>" class="btn btn-outline btn-sm">Full Report</a>
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
      <div class="card-footer">
        <div class="pagination">
          <?php for ($p = 1; $p <= $msgPages; $p++): ?>
            <a href="?id=<?= $u['id'] ?>&msg_page=<?= $p ?>" class="page-btn <?= $p===$msgPage?'active':'' ?>"><?= $p ?></a>
          <?php endfor; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- Purchases + Allocations -->
  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- Purchases -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-receipt" style="color:var(--primary)"></i> Purchases</h3>
        <?php if (($purchStats['pending_purchases']??0) > 0): ?>
          <span class="badge badge-warning"><?= $purchStats['pending_purchases'] ?> pending</span>
        <?php endif; ?>
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

    <!-- Unit Allocation History -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-history" style="color:var(--primary)"></i> Unit Allocations</h3>
      </div>
      <div class="table-wrapper">
        <table class="data-table">
          <thead><tr><th>From</th><th>Units</th><th>Note</th><th>Date</th></tr></thead>
          <tbody>
            <?php if (empty($allocations)): ?>
              <tr><td colspan="4" class="text-center text-muted" style="padding:18px">No allocations</td></tr>
            <?php else: foreach ($allocations as $a): ?>
              <tr>
                <td style="font-size:12px"><?= htmlspecialchars($a['from_name']) ?></td>
                <td><strong style="color:<?= $a['units'] > 0 ? 'var(--success)' : 'var(--danger)' ?>"><?= ($a['units'] > 0 ? '+' : '') . number_format($a['units'], 2) ?></strong></td>
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

<!-- Allocate Units Modal -->
<div class="modal-overlay" id="allocateModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fa-solid fa-coins" style="color:var(--primary)"></i> Allocate Units</h3>
      <button class="modal-close" onclick="closeModal('allocateModal')">×</button>
    </div>
    <form method="POST" action="/admin/actions/allocate-units.php">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="to_user" id="allocUserId">
      <div class="modal-body">
        <div style="background:var(--bg-muted);padding:12px;border-radius:var(--radius-md);margin-bottom:16px;font-size:13px">
          Allocating to: <strong id="allocUserName">—</strong> · Balance: <strong id="allocUserUnits" style="color:var(--primary)">—</strong>
        </div>
        <div class="form-group">
          <label class="form-label">Action <span class="required">*</span></label>
          <select name="action" class="form-control" onchange="toggleAction(this.value)">
            <option value="add">Add Units (+)</option>
            <option value="remove">Remove Units (-)</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" id="unitsLabel">Units to Add <span class="required">*</span></label>
          <input type="number" name="units" class="form-control" min="1" step="0.01" required>
        </div>
        <div class="form-group">
          <label class="form-label">Reason / Note <span class="required">*</span></label>
          <input type="text" name="note" class="form-control" placeholder="e.g. Manual top-up" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('allocateModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" id="allocSubmitBtn"><i class="fa-solid fa-check"></i> Process</button>
      </div>
    </form>
  </div>
</div>

<?php if ($u['role'] === 'reseller'): ?>
<!-- White-Label Modal -->
<div class="modal-overlay" id="whiteLabelModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fa-solid fa-brush" style="color:var(--primary)"></i> White-Label Management</h3>
      <button class="modal-close" onclick="closeModal('whiteLabelModal')">×</button>
    </div>
    <form method="POST" action="/admin/actions/save-white-label.php">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="reseller_id" id="wlResellerId">
      <div class="modal-body">
        <div id="wlResellerName" style="background:var(--bg-muted);padding:12px;border-radius:var(--radius-md);margin-bottom:16px;font-weight:700">Reseller: —</div>
        <div class="form-group">
          <label class="form-label">Custom Domain</label>
          <input type="text" name="custom_domain" id="wlDomain" class="form-control" placeholder="e.g. sms.reseller.com">
        </div>
        <div class="form-group">
          <label class="form-label">SSL Status</label>
          <select name="ssl_enabled" id="wlSSL" class="form-control">
            <option value="0">Disabled (HTTP)</option>
            <option value="1">Enabled (HTTPS)</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('whiteLabelModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php
$extraScript = <<<'JS'
<script>
function openAllocate(id, name, units) {
    document.getElementById('allocUserId').value = id;
    document.getElementById('allocUserName').textContent = name;
    document.getElementById('allocUserUnits').textContent = parseFloat(units).toLocaleString();
    openModal('allocateModal');
}
function toggleAction(val) {
    const label = document.getElementById('unitsLabel');
    const btn   = document.getElementById('allocSubmitBtn');
    if (val === 'remove') {
        label.innerHTML = 'Units to Remove <span class="required">*</span>';
        btn.classList.replace('btn-primary','btn-danger');
    } else {
        label.innerHTML = 'Units to Add <span class="required">*</span>';
        btn.classList.replace('btn-danger','btn-primary');
    }
}
function openWhiteLabel(id, name) {
    document.getElementById('wlResellerId').value = id;
    document.getElementById('wlResellerName').textContent = 'Reseller: ' + name;
    fetch('/admin/actions/get-white-label.php?id=' + id)
        .then(r => r.json())
        .then(data => {
            document.getElementById('wlDomain').value = data.custom_domain || '';
            document.getElementById('wlSSL').value    = data.ssl_enabled || 0;
            openModal('whiteLabelModal');
        });
}
</script>
JS;
include __DIR__ . '/../includes/layout-footer.php';
?>
