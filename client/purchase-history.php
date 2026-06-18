<?php
$pageTitle  = 'Purchase History';
$breadcrumb = [['label'=>'Client'],['label'=>'Purchase History']];
require_once __DIR__ . '/layout.php';

$uid = $user['id'];

// ── KPIs ─────────────────────────────────────────────────────────
$kpis = DB::queryOne(
    "SELECT COUNT(*) as total,
            SUM(status='completed') as completed,
            SUM(status='pending') as pending,
            SUM(status='failed') as failed_count,
            COALESCE(SUM(CASE WHEN status='completed' THEN amount END),0) as total_spent,
            COALESCE(SUM(CASE WHEN status='completed' THEN units END),0) as total_units
     FROM purchases WHERE user_id=?",
    [$uid]
);

// ── Filters ───────────────────────────────────────────────────────
$tab    = in_array($_GET['tab'] ?? '', ['completed','pending','failed','refunded']) ? $_GET['tab'] : '';
$from   = sanitize($_GET['from'] ?? '');
$to     = sanitize($_GET['to']   ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where  = 'WHERE user_id = ?';
$params = [$uid];
if ($tab)  { $where .= ' AND status = ?'; $params[] = $tab; }
if ($from) { $where .= ' AND created_at >= ?'; $params[] = $from; }
if ($to)   { $where .= ' AND created_at <= ?'; $params[] = $to . ' 23:59:59'; }

$total  = (int)(DB::queryOne("SELECT COUNT(*) as c FROM purchases $where", $params)['c'] ?? 0);
$pages  = max(1, (int)ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$purchases = DB::query(
    "SELECT * FROM purchases $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset",
    $params
);
?>

<div class="page-header">
  <div>
    <h1>Purchase History</h1>
    <div class="subtitle">All your SMS unit purchase transactions</div>
  </div>
  <a href="/client/purchases.php" class="btn btn-primary"><i class="fa-solid fa-cart-shopping"></i> Buy More Units</a>
</div>

<!-- KPIs -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px">
  <div class="stat-card" style="margin:0">
    <div class="stat-icon green"><i class="fa-solid fa-money-bill-wave"></i></div>
    <div class="stat-info"><div class="stat-label">Total Spent</div><div class="stat-value">KES <?= number_format($kpis['total_spent'] ?? 0, 0) ?></div></div>
  </div>
  <div class="stat-card" style="margin:0">
    <div class="stat-icon blue"><i class="fa-solid fa-coins"></i></div>
    <div class="stat-info"><div class="stat-label">Units Purchased</div><div class="stat-value"><?= number_format($kpis['total_units'] ?? 0, 0) ?></div></div>
  </div>
  <div class="stat-card" style="margin:0">
    <div class="stat-icon orange"><i class="fa-solid fa-clock"></i></div>
    <div class="stat-info">
      <div class="stat-label">Pending</div>
      <div class="stat-value"><?= number_format($kpis['pending'] ?? 0) ?></div>
      <?php if (($kpis['pending']??0) > 0): ?>
        <div class="stat-trend" style="color:var(--warning)">Awaiting approval</div>
      <?php endif; ?>
    </div>
  </div>
  <div class="stat-card" style="margin:0">
    <div class="stat-icon blue"><i class="fa-solid fa-receipt"></i></div>
    <div class="stat-info"><div class="stat-label">Total Purchases</div><div class="stat-value"><?= number_format($kpis['total'] ?? 0) ?></div></div>
  </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom:18px">
  <div class="card-body" style="padding:12px 18px">
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
      <div class="tab-bar" style="flex:none">
        <?php foreach ([''=> 'All', 'completed'=>'Completed', 'pending'=>'Pending', 'failed'=>'Failed', 'refunded'=>'Refunded'] as $k => $label): ?>
          <a href="?tab=<?= $k ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>"
             class="tab-item <?= $tab === $k ? 'active' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
      </div>
      <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
        <div class="form-group" style="margin:0">
          <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($from) ?>" style="width:145px">
        </div>
        <span style="font-size:12px;color:var(--text-secondary)">to</span>
        <div class="form-group" style="margin:0">
          <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($to) ?>" style="width:145px">
        </div>
        <button class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filter</button>
        <?php if ($from || $to): ?>
          <a href="?tab=<?= urlencode($tab) ?>" class="btn btn-secondary btn-sm">Clear</a>
        <?php endif; ?>
      </form>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fa-solid fa-receipt" style="color:var(--primary)"></i> Transactions <span class="badge badge-muted"><?= number_format($total) ?></span></h3>
    <?php if ($total > 0): ?>
      <span style="font-size:12px;color:var(--text-secondary)">
        Showing <?= number_format(($page-1)*$perPage+1) ?>–<?= number_format(min($page*$perPage,$total)) ?> of <?= number_format($total) ?>
      </span>
    <?php endif; ?>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <th>Reference</th>
          <th>Units</th>
          <th>Amount</th>
          <th>Method</th>
          <th>Phone / Note</th>
          <th>Status</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($purchases)): ?>
          <tr><td colspan="7"><div class="empty-state" style="padding:40px 20px">
            <div class="empty-icon">🧾</div>
            <h3>No purchases found</h3>
            <p>
              <?= $tab || $from || $to ? 'Try clearing your filters.' : 'Your purchase history will appear here once you buy units.' ?>
            </p>
            <a href="/client/purchases.php" class="btn btn-primary"><i class="fa-solid fa-cart-shopping"></i> Buy Units</a>
          </div></td></tr>
        <?php else: foreach ($purchases as $p):
          $ps = ['completed'=>'success','pending'=>'warning','failed'=>'danger','refunded'=>'muted'][$p['status']] ?? 'muted';
        ?>
          <tr>
            <td>
              <strong style="font-family:monospace;font-size:13px"><?= htmlspecialchars($p['transaction_ref'] ?? '#' . $p['id']) ?></strong>
            </td>
            <td><strong><?= number_format($p['units']) ?></strong> <span style="font-size:11px;color:var(--text-secondary)">units</span></td>
            <td style="font-weight:700;color:var(--primary)"><?= htmlspecialchars($p['currency'] ?? 'KES') ?> <?= number_format($p['amount'], 2) ?></td>
            <td style="font-size:13px"><?= ucfirst(str_replace('_', ' ', $p['payment_method'] ?? '—')) ?></td>
            <td style="font-size:12px;color:var(--text-secondary)"><?= htmlspecialchars($p['mpesa_phone'] ?? $p['notes'] ?? '—') ?></td>
            <td>
              <span class="badge badge-<?= $ps ?>"><?= ucfirst($p['status']) ?></span>
              <?php if ($p['status'] === 'pending'): ?>
                <div style="font-size:10px;color:var(--text-secondary);margin-top:2px">Awaiting approval</div>
              <?php elseif ($p['status'] === 'failed' && ($p['notes'] ?? '')): ?>
                <div style="font-size:10px;color:var(--danger);margin-top:2px"><?= htmlspecialchars($p['notes']) ?></div>
              <?php endif; ?>
            </td>
            <td style="font-size:12px">
              <div><?= date('d M Y', strtotime($p['created_at'])) ?></div>
              <div style="color:var(--text-secondary)"><?= date('H:i', strtotime($p['created_at'])) ?></div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
    <div class="card-footer"><div class="pagination">
      <?php for ($pg = 1; $pg <= $pages; $pg++): ?>
        <a href="?tab=<?= urlencode($tab) ?>&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&page=<?= $pg ?>"
           class="page-btn <?= $pg === $page ? 'active' : '' ?>"><?= $pg ?></a>
      <?php endfor; ?>
    </div></div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
