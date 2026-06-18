<?php
// CSV export must run before layout.php emits headers
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_role('admin');

$from   = sanitize($_GET['from'] ?? date('Y-m-01'));
$to     = sanitize($_GET['to']   ?? date('Y-m-d'));
$userId = (int)($_GET['user_id'] ?? 0);

$allowedPerPage = [10, 50, 100, 1000, 10000];
$perPage = in_array((int)($_GET['per_page'] ?? 50), $allowedPerPage, true) ? (int)($_GET['per_page'] ?? 50) : 50;

$validStatuses = ['sent', 'delivered', 'failed', 'queued', 'undelivered'];
$statusFilter  = in_array($_GET['status'] ?? '', $validStatuses, true) ? $_GET['status'] : '';

// Sargable date range — uses idx_user_created / created_at index
$baseWhere  = "m.created_at >= ? AND m.created_at < DATE_ADD(?, INTERVAL 1 DAY)";
$baseParams = [$from, $to];
if ($userId) {
    $baseWhere   .= " AND m.user_id = ?";
    $baseParams[] = $userId;
}

$logWhere  = $baseWhere;
$logParams = $baseParams;
if ($statusFilter) {
    $logWhere   .= " AND m.status = ?";
    $logParams[] = $statusFilter;
}

// CSV export — exits before any HTML is sent
if (($_GET['export'] ?? '') === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="admin_report_' . $from . '_to_' . $to . '.csv"');
    $fh = fopen('php://output', 'w');
    fputcsv($fh, ['ID', 'User', 'Sender ID', 'Recipient', 'Message', 'Units', 'Status', 'Gateway ID', 'Sent At', 'Created At']);
    $rows = DB::query(
        "SELECT m.id, u.name as user_name, m.sender_id, m.recipient, m.message,
                m.units_charged, m.status, m.gateway_msg_id, m.sent_at, m.created_at
         FROM messages m JOIN users u ON m.user_id = u.id
         WHERE $logWhere ORDER BY m.created_at DESC",
        $logParams
    );
    foreach ($rows as $r) {
        fputcsv($fh, [
            $r['id'], $r['user_name'], $r['sender_id'], $r['recipient'],
            $r['message'], $r['units_charged'], $r['status'],
            $r['gateway_msg_id'] ?? '', $r['sent_at'] ?? '', $r['created_at'],
        ]);
    }
    fclose($fh);
    exit;
}

$summary = DB::queryOne(
    "SELECT COUNT(*) as total,
            SUM(status='sent') as sent,
            SUM(status='delivered') as delivered,
            SUM(status='failed') as failed,
            COALESCE(SUM(units_charged), 0) as units
     FROM messages m WHERE $baseWhere",
    $baseParams
);

$page       = max(1, (int)($_GET['page'] ?? 1));
$total      = (int)(DB::queryOne("SELECT COUNT(*) as c FROM messages m WHERE $logWhere", $logParams)['c'] ?? 0);
$totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$messages = DB::query(
    "SELECT m.*, u.name as user_name
     FROM messages m JOIN users u ON m.user_id = u.id
     WHERE $logWhere ORDER BY m.created_at DESC LIMIT $perPage OFFSET $offset",
    $logParams
);

$rangeStart = $total > 0 ? $offset + 1 : 0;
$rangeEnd   = min($offset + $perPage, $total);

$users = DB::query("SELECT id, name FROM users WHERE role != 'admin' ORDER BY name");

$defaults = ['from' => date('Y-m-01'), 'to' => date('Y-m-d'), 'user_id' => 0, 'status' => '', 'per_page' => 50];
$changed  = ($from !== $defaults['from'] || $to !== $defaults['to'] || $userId !== $defaults['user_id']
          || $statusFilter !== $defaults['status'] || $perPage !== $defaults['per_page']);

function adminRptQs(array $overrides = []): string {
    global $from, $to, $userId, $statusFilter, $perPage;
    $base = ['from' => $from, 'to' => $to];
    if ($userId)        $base['user_id']  = $userId;
    if ($statusFilter)  $base['status']   = $statusFilter;
    if ($perPage !== 50) $base['per_page'] = $perPage;
    return http_build_query(array_filter(array_merge($base, $overrides), fn($v) => $v !== null && $v !== ''));
}

$exportUrl = '?' . adminRptQs(['export' => '1', 'page' => null]);

$pageTitle  = 'Reports';
$breadcrumb = [['label'=>'Admin'],['label'=>'Reports']];
require_once __DIR__ . '/layout.php';
?>
<div class="page-header">
  <div><h1>Reports</h1><div class="subtitle">Message log and delivery reports</div></div>
  <div style="display:flex;gap:8px">
    <button onclick="downloadPDF(event)" class="btn btn-secondary"><i class="fa-solid fa-file-pdf"></i> Download PDF</button>
    <a href="<?=htmlspecialchars($exportUrl)?>" class="btn btn-secondary"><i class="fa-solid fa-download"></i> Export CSV</a>
  </div>
</div>
<div id="report-content">

<!-- Filters -->
<div class="card" style="margin-bottom:18px">
  <div class="card-body" style="padding:14px 18px">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
      <div class="form-group" style="margin:0">
        <label class="form-label" style="font-size:11px">From</label>
        <input type="date" name="from" class="form-control" value="<?=$from?>" style="width:150px">
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label" style="font-size:11px">To</label>
        <input type="date" name="to" class="form-control" value="<?=$to?>" style="width:150px">
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label" style="font-size:11px">User</label>
        <select name="user_id" class="form-control" style="width:200px">
          <option value="">All Users</option>
          <?php foreach ($users as $u): ?>
            <option value="<?=$u['id']?>" <?=$userId==$u['id']?'selected':''?>><?=htmlspecialchars($u['name'])?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label" style="font-size:11px">Status</label>
        <select name="status" class="form-control" style="width:140px">
          <option value="">All Statuses</option>
          <?php foreach ($validStatuses as $s): ?>
            <option value="<?=$s?>" <?=$statusFilter===$s?'selected':''?>><?=ucfirst($s)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary" style="align-self:flex-end"><i class="fa-solid fa-filter"></i> Filter</button>
      <?php if ($changed): ?><a href="?" class="btn btn-secondary" style="align-self:flex-end">Clear</a><?php endif; ?>
    </form>
  </div>
</div>

<!-- Summary -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:20px">
  <div class="stat-card"><div class="stat-icon blue"><i class="fa-solid fa-message"></i></div><div class="stat-info"><div class="stat-label">Total</div><div class="stat-value"><?=number_format($summary['total']??0)?></div></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fa-solid fa-check"></i></div><div class="stat-info"><div class="stat-label">Sent</div><div class="stat-value"><?=number_format($summary['sent']??0)?></div></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fa-solid fa-check-double"></i></div><div class="stat-info"><div class="stat-label">Delivered</div><div class="stat-value"><?=number_format($summary['delivered']??0)?></div></div></div>
  <div class="stat-card"><div class="stat-icon orange"><i class="fa-solid fa-xmark"></i></div><div class="stat-info"><div class="stat-label">Failed</div><div class="stat-value"><?=number_format($summary['failed']??0)?></div></div></div>
  <div class="stat-card"><div class="stat-icon orange"><i class="fa-solid fa-coins"></i></div><div class="stat-info"><div class="stat-label">Units Used</div><div class="stat-value"><?=number_format($summary['units']??0,2)?></div></div></div>
</div>

<!-- Message log -->
<div class="card" id="message-log-section">
  <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
    <h3 class="card-title"><i class="fa-solid fa-file-lines" style="color:var(--primary)"></i> Messages <span class="badge badge-muted"><?=number_format($total)?></span></h3>
    <div style="display:flex;gap:4px;align-items:center">
      <span style="font-size:11px;color:var(--text-secondary);margin-right:4px">Show:</span>
      <?php foreach ($allowedPerPage as $pp):
        $qs = adminRptQs(['per_page' => $pp !== 50 ? $pp : null, 'page' => 1]);
      ?>
        <a href="?<?=$qs?>" class="btn btn-sm <?=$perPage===$pp?'btn-primary':'btn-secondary'?>" style="min-width:42px;text-align:center;padding:3px 8px;font-size:12px"><?=number_format($pp)?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <th style="width:36px">#</th>
          <th>User</th>
          <th>Sender ID</th>
          <th>Recipient</th>
          <th>Message</th>
          <th>Units</th>
          <th>Status</th>
          <th>Sent At</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($messages)): ?>
          <tr><td colspan="8" class="text-center text-muted" style="padding:30px">No messages found for selected filters</td></tr>
        <?php else: ?>
          <?php foreach ($messages as $i => $m):
            $sc = ['sent'=>'success','delivered'=>'success','failed'=>'danger','queued'=>'warning','undelivered'=>'warning'][$m['status']] ?? 'muted';
          ?>
            <tr>
              <td style="font-size:11px;color:var(--text-secondary)"><?=$rangeStart + $i?></td>
              <td style="font-size:12px"><?=htmlspecialchars($m['user_name'])?></td>
              <td><code><?=htmlspecialchars($m['sender_id'])?></code></td>
              <td><?=htmlspecialchars($m['recipient'])?></td>
              <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;color:var(--text-secondary)" title="<?=htmlspecialchars($m['message'])?>">
                <?=htmlspecialchars(mb_strimwidth($m['message'], 0, 60, '…'))?>
              </td>
              <td><?=$m['units_charged']?></td>
              <td><span class="badge badge-<?=$sc?>"><?=ucfirst($m['status'])?></span></td>
              <td style="font-size:11px"><?=$m['sent_at'] ? date('d M Y H:i', strtotime($m['sent_at'])) : '—'?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
    <div style="font-size:12px;color:var(--text-secondary)">
      <?php if ($total > 0): ?>
        Showing <?=number_format($rangeStart)?> – <?=number_format($rangeEnd)?> of <?=number_format($total)?> messages &nbsp;·&nbsp; Page <?=$page?> of <?=number_format($totalPages)?>
      <?php else: ?>
        No messages found
      <?php endif; ?>
    </div>
    <div style="display:flex;gap:6px">
      <?php if ($page > 1): ?>
        <a href="?<?=adminRptQs(['page' => $page - 1])?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-chevron-left"></i> Prev</a>
      <?php endif; ?>
      <?php if ($page < $totalPages): ?>
        <a href="?<?=adminRptQs(['page' => $page + 1])?>" class="btn btn-primary btn-sm">Next <i class="fa-solid fa-chevron-right"></i></a>
      <?php endif; ?>
    </div>
  </div>
</div>
</div>

<?php
$extraScript = <<<JS
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadPDF(event) {
    const element = document.getElementById('report-content');
    const btn = event.currentTarget;
    const originalContent = btn.innerHTML;
    const fromDate = '{$from}';
    const toDate = '{$to}';
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating...';
    btn.disabled = true;
    const opt = {
        margin: [10, 10],
        filename: 'Admin_Full_Report_' + fromDate + '_to_' + toDate + '.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, logging: false },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };
    html2pdf().set(opt).from(element).toPdf().get('pdf').then((pdf) => {
        window.open(pdf.output('bloburl'), '_blank');
        btn.innerHTML = originalContent;
        btn.disabled = false;
    }).catch(err => {
        btn.innerHTML = originalContent;
        btn.disabled = false;
        alert('Failed to generate PDF. Please try again.');
    });
}
</script>
JS;
include __DIR__ . '/../includes/layout-footer.php';
?>
