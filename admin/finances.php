<?php
// CSV export must happen before layout.php emits headers
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_role('admin');

$from     = sanitize($_GET['from']    ?? date('Y-m-01'));
$to       = sanitize($_GET['to']      ?? date('Y-m-d'));
$userId   = (int)($_GET['user_id']    ?? 0);
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 25;

$validStatuses  = ['pending', 'completed', 'failed', 'refunded'];
$validMethods   = ['mpesa', 'manual_mpesa', 'bank', 'admin_credit'];
$validTypes     = ['sms', 'whatsapp'];
$statusFilter   = in_array($_GET['status'] ?? '', $validStatuses, true)  ? $_GET['status'] : '';
$methodFilter   = in_array($_GET['method'] ?? '', $validMethods, true)   ? $_GET['method'] : '';
$typeFilter     = in_array($_GET['type']   ?? '', $validTypes, true)     ? $_GET['type']   : '';

// Build WHERE
$where  = "WHERE p.created_at >= ? AND p.created_at < DATE_ADD(?, INTERVAL 1 DAY)";
$params = [$from, $to];
if ($statusFilter) { $where .= " AND p.status = ?";         $params[] = $statusFilter; }
if ($methodFilter) { $where .= " AND p.payment_method = ?"; $params[] = $methodFilter; }
if ($typeFilter)   { $where .= " AND p.type = ?";           $params[] = $typeFilter; }
if ($userId)       { $where .= " AND p.user_id = ?";        $params[] = $userId; }

// CSV export
if (($_GET['export'] ?? '') === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="purchases_' . $from . '_to_' . $to . '.csv"');
    $fh = fopen('php://output', 'w');
    fputcsv($fh, ['ID', 'User', 'Role', 'Type', 'Units', 'Amount (KES)', 'Method', 'Ref', 'Status', 'Date']);
    $rows = DB::query(
        "SELECT p.*, u.name as user_name, u.role as user_role
         FROM purchases p JOIN users u ON p.user_id = u.id
         $where ORDER BY p.created_at DESC", $params
    );
    foreach ($rows as $r) {
        fputcsv($fh, [
            $r['id'], $r['user_name'], $r['user_role'], $r['type'],
            $r['units'], $r['amount'], $r['payment_method'],
            $r['transaction_ref'] ?? '', $r['status'], $r['created_at'],
        ]);
    }
    fclose($fh);
    exit;
}

// KPIs (all-time for summary cards, date range for the table)
$allTime = DB::queryOne(
    "SELECT COALESCE(SUM(CASE WHEN status='completed' THEN amount ELSE 0 END),0) as revenue,
            SUM(status='completed') as completed,
            SUM(status='pending')   as pending,
            SUM(status='failed')    as failed,
            SUM(status='refunded')  as refunded,
            COALESCE(SUM(CASE WHEN status='completed' AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW()) THEN amount ELSE 0 END),0) as this_month
     FROM purchases"
);

$pendingList = DB::query(
    "SELECT p.*, u.name as user_name, u.email as user_email, u.role as user_role,
            r.name as reseller_name
     FROM purchases p
     JOIN users u ON p.user_id = u.id
     LEFT JOIN users r ON u.parent_id = r.id
     WHERE p.status = 'pending'
     ORDER BY p.created_at ASC
     LIMIT 20"
);

$total      = (int)(DB::queryOne(
    "SELECT COUNT(*) as c FROM purchases p JOIN users u ON p.user_id=u.id $where", $params
)['c'] ?? 0);
$totalPages = max(1, (int)ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$purchases = DB::query(
    "SELECT p.*, u.name as user_name, u.email as user_email, u.role as user_role
     FROM purchases p JOIN users u ON p.user_id = u.id
     $where ORDER BY p.created_at DESC LIMIT $perPage OFFSET $offset",
    $params
);

$users = DB::query("SELECT id, name FROM users WHERE role != 'admin' ORDER BY name");

function adminFinQs(array $ov = []): string {
    global $from, $to, $userId, $statusFilter, $methodFilter, $typeFilter;
    $base = ['from' => $from, 'to' => $to];
    if ($userId)       $base['user_id'] = $userId;
    if ($statusFilter) $base['status']  = $statusFilter;
    if ($methodFilter) $base['method']  = $methodFilter;
    if ($typeFilter)   $base['type']    = $typeFilter;
    return http_build_query(array_filter(array_merge($base, $ov), fn($v) => $v !== null && $v !== ''));
}

$exportUrl = '?' . adminFinQs(['export' => '1', 'page' => null]);

$defaults = ['from' => date('Y-m-01'), 'to' => date('Y-m-d'), 'user_id' => 0, 'status' => '', 'method' => '', 'type' => ''];
$changed  = ($from !== $defaults['from'] || $to !== $defaults['to'] || $userId !== $defaults['user_id'] ||
             $statusFilter !== $defaults['status'] || $methodFilter !== $defaults['method'] || $typeFilter !== $defaults['type']);

$pageTitle  = 'Financial Management';
$breadcrumb = [['label' => 'Admin'], ['label' => 'Finances']];
require_once __DIR__ . '/layout.php';
?>

<div class="page-header">
  <div><h1>Financial Management</h1><div class="subtitle">Monitor purchases, approve payments, and track revenue</div></div>
  <a href="<?= htmlspecialchars($exportUrl) ?>" class="btn btn-secondary"><i class="fa-solid fa-download"></i> Export CSV</a>
</div>

<!-- KPI Strip -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:24px">
  <div class="stat-card">
    <div class="stat-icon green"><i class="fa-solid fa-circle-dollar-to-slot"></i></div>
    <div class="stat-info">
      <div class="stat-label">Total Revenue</div>
      <div class="stat-value">KES <?= number_format($allTime['revenue'] ?? 0, 0) ?></div>
      <div class="stat-trend up"><i class="fa-solid fa-check-double"></i> <?= number_format($allTime['completed'] ?? 0) ?> completed</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fa-solid fa-calendar-days"></i></div>
    <div class="stat-info">
      <div class="stat-label">This Month</div>
      <div class="stat-value">KES <?= number_format($allTime['this_month'] ?? 0, 0) ?></div>
    </div>
  </div>
  <div class="stat-card" style="<?= ($allTime['pending'] ?? 0) > 0 ? 'border:1px solid var(--warning);background:rgba(255,193,7,.04)' : '' ?>">
    <div class="stat-icon orange"><i class="fa-solid fa-hourglass-half"></i></div>
    <div class="stat-info">
      <div class="stat-label">Pending Approval</div>
      <div class="stat-value"><?= number_format($allTime['pending'] ?? 0) ?></div>
      <?php if (($allTime['pending'] ?? 0) > 0): ?>
        <div class="stat-trend" style="color:var(--warning)"><i class="fa-solid fa-triangle-exclamation"></i> Needs review</div>
      <?php endif; ?>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange"><i class="fa-solid fa-xmark-circle"></i></div>
    <div class="stat-info">
      <div class="stat-label">Failed</div>
      <div class="stat-value"><?= number_format($allTime['failed'] ?? 0) ?></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fa-solid fa-rotate-left"></i></div>
    <div class="stat-info">
      <div class="stat-label">Refunded</div>
      <div class="stat-value"><?= number_format($allTime['refunded'] ?? 0) ?></div>
    </div>
  </div>
</div>

<?php if (!empty($pendingList)): ?>
<!-- Pending Approvals Banner -->
<div class="card" style="margin-bottom:24px;border:1px solid var(--warning)">
  <div class="card-header" style="background:rgba(255,193,7,.06)">
    <h3 class="card-title" style="color:var(--warning)">
      <i class="fa-solid fa-triangle-exclamation"></i>
      Pending Payments Awaiting Approval
      <span class="badge badge-warning" style="margin-left:6px"><?= count($pendingList) ?></span>
    </h3>
    <div style="font-size:12px;color:var(--text-secondary)">These clients submitted payment references manually — verify and approve or reject.</div>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr><th>User</th><th>Reseller</th><th>Type</th><th>Units</th><th>Amount</th><th>Payment Ref</th><th>Method</th><th>Submitted</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($pendingList as $p): ?>
          <tr>
            <td>
              <div style="font-weight:700;font-size:13px"><?= htmlspecialchars($p['user_name']) ?></div>
              <div style="font-size:11px;color:var(--text-secondary)"><?= htmlspecialchars($p['user_email']) ?></div>
            </td>
            <td style="font-size:12px"><?= htmlspecialchars($p['reseller_name'] ?? '—') ?></td>
            <td><span class="badge <?= $p['type']==='whatsapp'?'badge-success':'badge-info' ?>"><?= ucfirst($p['type']) ?></span></td>
            <td><strong><?= number_format($p['units']) ?></strong></td>
            <td><strong style="color:var(--primary)">KES <?= number_format($p['amount'], 2) ?></strong></td>
            <td>
              <?php if ($p['transaction_ref']): ?>
                <code style="font-size:12px"><?= htmlspecialchars($p['transaction_ref']) ?></code>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td style="font-size:12px"><?= ucfirst(str_replace('_', ' ', $p['payment_method'])) ?></td>
            <td style="font-size:11px;color:var(--text-secondary)"><?= date('d M Y H:i', strtotime($p['created_at'])) ?></td>
            <td>
              <div class="btn-group">
                <form method="POST" action="/admin/actions/approve-purchase.php" style="display:inline"
                      onsubmit="return confirm('Approve this payment and credit <?= number_format($p['units']) ?> units to <?= htmlspecialchars(addslashes($p['user_name'])) ?>?')">
                  <input type="hidden" name="id" value="<?= $p['id'] ?>">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check"></i> Approve</button>
                </form>
                <button class="btn btn-danger btn-sm" onclick="openRejectModal(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['user_name'])) ?>')">
                  <i class="fa-solid fa-xmark"></i> Reject
                </button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Status tabs -->
<div class="tabs" style="margin-bottom:12px">
  <?php foreach ([''=>'All', 'pending'=>'Pending', 'completed'=>'Completed', 'failed'=>'Failed', 'refunded'=>'Refunded'] as $k=>$v): ?>
    <a href="?<?= adminFinQs(['status'=>$k,'page'=>1]) ?>" class="tab-btn <?= $statusFilter===$k?'active':'' ?>"><?= $v ?></a>
  <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom:18px">
  <div class="card-body" style="padding:14px 18px">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
      <div class="form-group" style="margin:0;flex:1;min-width:200px">
        <label class="form-label" style="font-size:11px">User</label>
        <select name="user_id" class="form-control">
          <option value="">All Users</option>
          <?php foreach ($users as $u): ?>
            <option value="<?= $u['id'] ?>" <?= $userId==$u['id']?'selected':'' ?>><?= htmlspecialchars($u['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label" style="font-size:11px">Type</label>
        <select name="type" class="form-control" style="width:130px">
          <option value="">All Types</option>
          <option value="sms" <?= $typeFilter==='sms'?'selected':'' ?>>SMS</option>
          <option value="whatsapp" <?= $typeFilter==='whatsapp'?'selected':'' ?>>WhatsApp</option>
        </select>
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label" style="font-size:11px">Method</label>
        <select name="method" class="form-control" style="width:160px">
          <option value="">All Methods</option>
          <option value="mpesa" <?= $methodFilter==='mpesa'?'selected':'' ?>>M-Pesa (STK)</option>
          <option value="manual_mpesa" <?= $methodFilter==='manual_mpesa'?'selected':'' ?>>Manual M-Pesa</option>
          <option value="bank" <?= $methodFilter==='bank'?'selected':'' ?>>Bank Transfer</option>
          <option value="admin_credit" <?= $methodFilter==='admin_credit'?'selected':'' ?>>Admin Credit</option>
        </select>
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label" style="font-size:11px">From</label>
        <input type="date" name="from" class="form-control" value="<?= $from ?>" style="width:145px">
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label" style="font-size:11px">To</label>
        <input type="date" name="to" class="form-control" value="<?= $to ?>" style="width:145px">
      </div>
      <?php if ($statusFilter): ?><input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>"><?php endif; ?>
      <button type="submit" class="btn btn-primary" style="align-self:flex-end"><i class="fa-solid fa-filter"></i> Filter</button>
      <?php if ($changed): ?><a href="/admin/finances.php" class="btn btn-secondary" style="align-self:flex-end">Clear</a><?php endif; ?>
    </form>
  </div>
</div>

<!-- All Purchases Table -->
<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fa-solid fa-receipt" style="color:var(--primary)"></i> Purchase History <span class="badge badge-muted"><?= number_format($total) ?></span></h3>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr><th>#</th><th>User</th><th>Type</th><th>Units</th><th>Amount</th><th>Method</th><th>Reference</th><th>Status</th><th>Date</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if (empty($purchases)): ?>
          <tr><td colspan="10" class="text-center text-muted" style="padding:40px">No purchases found for selected filters</td></tr>
        <?php else: ?>
          <?php foreach ($purchases as $p):
            $sc = ['completed'=>'success','pending'=>'warning','failed'=>'danger','refunded'=>'muted'][$p['status']] ?? 'muted';
          ?>
            <tr>
              <td style="font-size:11px;color:var(--text-secondary)"><?= $p['id'] ?></td>
              <td>
                <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($p['user_name']) ?></div>
                <div style="font-size:11px;color:var(--text-secondary)"><?= htmlspecialchars($p['user_email']) ?></div>
                <span class="badge <?= $p['user_role']==='reseller'?'badge-success':'badge-info' ?>" style="font-size:10px"><?= ucfirst($p['user_role']) ?></span>
              </td>
              <td><span class="badge <?= $p['type']==='whatsapp'?'badge-success':'badge-info' ?>"><?= ucfirst($p['type']) ?></span></td>
              <td><?= number_format($p['units']) ?></td>
              <td><strong style="color:var(--primary)">KES <?= number_format($p['amount'], 2) ?></strong></td>
              <td style="font-size:12px"><?= ucfirst(str_replace('_', ' ', $p['payment_method'])) ?></td>
              <td>
                <?php if ($p['transaction_ref']): ?>
                  <code style="font-size:11px"><?= htmlspecialchars($p['transaction_ref']) ?></code>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td><span class="badge badge-<?= $sc ?>"><?= ucfirst($p['status']) ?></span></td>
              <td style="font-size:11px"><?= date('d M Y H:i', strtotime($p['created_at'])) ?></td>
              <td>
                <?php if ($p['status'] === 'pending'): ?>
                  <div class="btn-group">
                    <form method="POST" action="/admin/actions/approve-purchase.php" style="display:inline"
                          onsubmit="return confirm('Approve and credit <?= number_format($p['units']) ?> units?')">
                      <input type="hidden" name="id" value="<?= $p['id'] ?>">
                      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                      <button class="btn btn-primary btn-sm btn-icon" title="Approve"><i class="fa-solid fa-check"></i></button>
                    </form>
                    <button class="btn btn-danger btn-sm btn-icon" title="Reject"
                            onclick="openRejectModal(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['user_name'])) ?>')">
                      <i class="fa-solid fa-xmark"></i>
                    </button>
                  </div>
                <?php elseif ($p['status'] === 'completed'): ?>
                  <span style="font-size:11px;color:var(--success)"><i class="fa-solid fa-check-double"></i> Paid</span>
                <?php else: ?>
                  <span style="font-size:11px;color:var(--text-secondary)">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="card-footer" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
    <div style="font-size:12px;color:var(--text-secondary)">
      <?php if ($total > 0): ?>
        Showing <?= number_format(($page-1)*$perPage+1) ?> – <?= number_format(min($page*$perPage,$total)) ?> of <?= number_format($total) ?>
      <?php else: ?>
        No records found
      <?php endif; ?>
    </div>
    <?php if ($totalPages > 1): ?>
      <div style="display:flex;gap:6px">
        <?php if ($page > 1): ?>
          <a href="?<?= adminFinQs(['page'=>$page-1]) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-chevron-left"></i> Prev</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
          <a href="?<?= adminFinQs(['page'=>$page+1]) ?>" class="btn btn-primary btn-sm">Next <i class="fa-solid fa-chevron-right"></i></a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Reject Modal -->
<div class="modal-overlay" id="rejectModal">
  <div class="modal" style="max-width:440px">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fa-solid fa-xmark-circle" style="color:var(--danger)"></i> Reject Payment</h3>
      <button class="modal-close" onclick="closeModal('rejectModal')">×</button>
    </div>
    <form method="POST" action="/admin/actions/reject-purchase.php">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="id" id="rejectPurchaseId">
      <div class="modal-body">
        <div id="rejectUserInfo" style="background:var(--bg-muted);padding:12px;border-radius:var(--radius-md);margin-bottom:16px;font-size:13px">
          Rejecting payment for: <strong id="rejectUserName">—</strong>
        </div>
        <div class="form-group">
          <label class="form-label">Reason for rejection <span class="required">*</span></label>
          <textarea name="reason" class="form-control" rows="3" placeholder="e.g. M-Pesa code not matching, duplicate submission…" required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')">Cancel</button>
        <button type="submit" class="btn btn-danger"><i class="fa-solid fa-xmark"></i> Reject Payment</button>
      </div>
    </form>
  </div>
</div>

<?php
$extraScript = <<<'JS'
<script>
function openRejectModal(id, name) {
    document.getElementById('rejectPurchaseId').value = id;
    document.getElementById('rejectUserName').textContent = name;
    openModal('rejectModal');
}
</script>
JS;
include __DIR__ . '/../includes/layout-footer.php';
?>
