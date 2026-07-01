<?php
$pageTitle = 'Reports';
$breadcrumb = [['label'=>'Reseller'],['label'=>'Reports']];
require_once __DIR__ . '/layout.php';

$uid  = $user['id'];
$from = sanitize($_GET['from'] ?? date('Y-m-01'));
$to   = sanitize($_GET['to']   ?? date('Y-m-d'));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');

$allowedPerPage = [10, 50, 100, 1000, 10000];
$perPage = in_array((int)($_GET['per_page'] ?? 50), $allowedPerPage, true) ? (int)$_GET['per_page'] : 50;

$validStatuses = ['sent', 'delivered', 'failed', 'queued', 'undelivered'];
$statusFilter  = in_array($_GET['status'] ?? '', $validStatuses, true) ? $_GET['status'] : '';

// ── Build the set of user IDs in this reseller's network ────────────────────
// Reseller's own account + all their clients
$clientRows   = DB::query("SELECT id, name FROM users WHERE role='client' AND parent_id=? ORDER BY name", [$uid]);
$clients      = [];   // id => name map for dropdown + JOIN display
foreach ($clientRows as $c) {
    $clients[(int)$c['id']] = $c['name'];
}

$clientFilter = (int)($_GET['client_id'] ?? 0);
$showSelf     = ($clientFilter === -1);          // -1 = reseller's own messages only

if ($showSelf) {
    $networkIds = [$uid];
} elseif ($clientFilter > 0 && isset($clients[$clientFilter])) {
    $networkIds = [$clientFilter];
} else {
    // Everyone: reseller + all clients
    $networkIds = array_merge([$uid], array_keys($clients));
}

if (empty($networkIds)) {
    $networkIds = [0]; // fallback — prevents SQL syntax error, returns 0 rows
}

$inPlaceholders = implode(',', array_fill(0, count($networkIds), '?'));

// ── Date-range base WHERE (reused across all queries) ────────────────────────
$baseWhere  = "m.user_id IN ($inPlaceholders) AND m.created_at >= ? AND m.created_at < DATE_ADD(?, INTERVAL 1 DAY)";
$baseParams = array_merge($networkIds, [$from, $to]);

$logWhere  = $baseWhere;
$logParams = $baseParams;
if ($statusFilter) {
    $logWhere  .= " AND m.status = ?";
    $logParams[] = $statusFilter;
}

// ── Aggregates ───────────────────────────────────────────────────────────────
$summary = DB::queryOne(
    "SELECT COUNT(*) as total,
            SUM(status='sent') as sent,
            SUM(status='delivered') as delivered,
            SUM(status='failed') as failed,
            COALESCE(SUM(units_charged), 0) as units
     FROM messages m WHERE $baseWhere",
    $baseParams
);

// ── Daily trend ──────────────────────────────────────────────────────────────
$trend   = DB::query(
    "SELECT DATE(created_at) as day, COUNT(*) as total
     FROM messages m WHERE $baseWhere
     GROUP BY day ORDER BY day",
    $baseParams
);
$tLabels = json_encode(array_column($trend, 'day'));
$tValues = json_encode(array_column($trend, 'total'));

// ── Paginated message log ────────────────────────────────────────────────────
$page       = max(1, (int)($_GET['page'] ?? 1));
$total      = (int)(DB::queryOne("SELECT COUNT(*) as c FROM messages m WHERE $logWhere", $logParams)['c'] ?? 0);
$totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

// Join users so we can show the sender name in the table
$messages = DB::query(
    "SELECT m.*, u.name as sender_name
     FROM messages m
     JOIN users u ON m.user_id = u.id
     WHERE $logWhere
     ORDER BY m.created_at DESC
     LIMIT $perPage OFFSET $offset",
    $logParams
);

$rangeStart = $total > 0 ? $offset + 1 : 0;
$rangeEnd   = min($offset + $perPage, $total);

// ── Filter change detection (for "Clear" button) ─────────────────────────────
$defaults = ['from' => date('Y-m-01'), 'to' => date('Y-m-d'), 'status' => '', 'per_page' => 50, 'client_id' => 0];
$changed  = $from !== $defaults['from'] || $to !== $defaults['to']
          || $statusFilter !== $defaults['status'] || $perPage !== $defaults['per_page']
          || ($clientFilter !== 0 && $clientFilter !== $defaults['client_id']);

// ── Query-string helper ───────────────────────────────────────────────────────
function resellerRptQs(array $overrides = []): string {
    global $from, $to, $statusFilter, $perPage, $clientFilter;
    $base = ['from' => $from, 'to' => $to];
    if ($statusFilter)        $base['status']    = $statusFilter;
    if ($perPage !== 50)      $base['per_page']  = $perPage;
    if ($clientFilter !== 0)  $base['client_id'] = $clientFilter;
    return http_build_query(array_filter(array_merge($base, $overrides), fn($v) => $v !== null && $v !== ''));
}
?>
<?php
$csvQs = http_build_query(array_filter([
    'from'      => $from,
    'to'        => $to,
    'status'    => $statusFilter ?: null,
    'client_id' => $clientFilter ?: null,
]));
?>
<div class="page-header">
  <div>
    <h1>Reports</h1>
    <div class="subtitle">
      <?php if ($showSelf): ?>
        Your own messages
      <?php elseif ($clientFilter > 0 && isset($clients[$clientFilter])): ?>
        Messages by <strong><?= htmlspecialchars($clients[$clientFilter]) ?></strong>
      <?php else: ?>
        All messages across your account &amp; clients
      <?php endif; ?>
    </div>
  </div>
  <div class="btn-group">
    <a href="/reseller/actions/download-report.php?<?= $csvQs ?>" class="btn btn-secondary"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
    <button onclick="downloadPDF(event)" class="btn btn-secondary"><i class="fa-solid fa-file-pdf"></i> Download PDF</button>
  </div>
</div>
<div id="report-content">

<!-- Filters -->
<div class="card" style="margin-bottom:18px">
  <div class="card-body" style="padding:14px 18px">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
      <div class="form-group" style="margin:0"><label class="form-label" style="font-size:11px">From</label><input type="date" name="from" class="form-control" value="<?=$from?>" style="width:150px"></div>
      <div class="form-group" style="margin:0"><label class="form-label" style="font-size:11px">To</label><input type="date" name="to" class="form-control" value="<?=$to?>" style="width:150px"></div>
      <div class="form-group" style="margin:0">
        <label class="form-label" style="font-size:11px">Status</label>
        <select name="status" class="form-control" style="width:140px">
          <option value="">All Statuses</option>
          <?php foreach ($validStatuses as $s): ?>
            <option value="<?=$s?>" <?=$statusFilter===$s?'selected':''?>><?=ucfirst($s)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if (!empty($clients)): ?>
      <div class="form-group" style="margin:0">
        <label class="form-label" style="font-size:11px">Account</label>
        <select name="client_id" class="form-control" style="width:180px">
          <option value="0" <?=$clientFilter===0?'selected':''?>>All (me + clients)</option>
          <option value="-1" <?=$showSelf?'selected':''?>>My own messages</option>
          <?php foreach ($clients as $cid => $cname): ?>
            <option value="<?=$cid?>" <?=$clientFilter===$cid?'selected':''?>><?=htmlspecialchars($cname)?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <button type="submit" class="btn btn-primary" style="align-self:flex-end"><i class="fa-solid fa-filter"></i> Filter</button>
      <?php if ($changed): ?><a href="?" class="btn btn-secondary" style="align-self:flex-end">Clear</a><?php endif; ?>
    </form>
  </div>
</div>

<!-- Summary cards -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:20px">
  <div class="stat-card"><div class="stat-icon blue"><i class="fa-solid fa-message"></i></div><div class="stat-info"><div class="stat-label">Total</div><div class="stat-value"><?=number_format($summary['total']??0)?></div></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fa-solid fa-check"></i></div><div class="stat-info"><div class="stat-label">Sent</div><div class="stat-value"><?=number_format($summary['sent']??0)?></div></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fa-solid fa-check-double"></i></div><div class="stat-info"><div class="stat-label">Delivered</div><div class="stat-value"><?=number_format($summary['delivered']??0)?></div></div></div>
  <div class="stat-card"><div class="stat-icon orange"><i class="fa-solid fa-xmark"></i></div><div class="stat-info"><div class="stat-label">Failed</div><div class="stat-value"><?=number_format($summary['failed']??0)?></div></div></div>
  <div class="stat-card"><div class="stat-icon orange"><i class="fa-solid fa-coins"></i></div><div class="stat-info"><div class="stat-label">Units Used</div><div class="stat-value"><?=number_format($summary['units']??0,2)?></div></div></div>
</div>

<!-- Chart -->
<div class="card" style="margin-bottom:20px">
  <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-chart-bar" style="color:var(--primary)"></i> Daily Message Volume</h3></div>
  <div class="card-body"><div class="chart-container" style="height:200px"><canvas id="repChart"></canvas></div></div>
</div>

<!-- Message log -->
<div class="card" id="message-log-section">
  <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
    <h3 class="card-title"><i class="fa-solid fa-file-lines" style="color:var(--primary)"></i> Message Log <span class="badge badge-muted"><?=number_format($total)?></span></h3>
    <div style="display:flex;gap:4px;align-items:center">
      <span style="font-size:11px;color:var(--text-secondary);margin-right:4px">Show:</span>
      <?php foreach ($allowedPerPage as $pp):
        $qs = resellerRptQs(['per_page' => $pp !== 50 ? $pp : null, 'page' => 1]);
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
          <?php if (!$showSelf && !($clientFilter > 0)): ?><th>Account</th><?php endif; ?>
          <th>Sender ID</th>
          <th>Recipient</th>
          <th>Message</th>
          <th>Units</th>
          <th>Status</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($messages)): ?>
          <tr><td colspan="<?= (!$showSelf && !($clientFilter > 0)) ? 8 : 7 ?>" class="text-center text-muted" style="padding:30px">No messages in selected date range</td></tr>
        <?php else: ?>
          <?php foreach ($messages as $i => $m):
            $sc = ['sent'=>'success','delivered'=>'success','failed'=>'danger','queued'=>'warning','undelivered'=>'warning'][$m['status']] ?? 'muted';
            $isReseller = ((int)$m['user_id'] === $uid);
          ?>
            <tr>
              <td style="font-size:11px;color:var(--text-secondary)"><?=$rangeStart + $i?></td>
              <?php if (!$showSelf && !($clientFilter > 0)): ?>
              <td style="font-size:12px;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                <?php if ($isReseller): ?>
                  <span style="color:var(--primary);font-weight:600">Me</span>
                <?php else: ?>
                  <?=htmlspecialchars($m['sender_name'])?>
                <?php endif; ?>
              </td>
              <?php endif; ?>
              <td><code><?=htmlspecialchars($m['sender_id'])?></code></td>
              <td><?=htmlspecialchars($m['recipient'])?></td>
              <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;color:var(--text-secondary)" title="<?=htmlspecialchars($m['message'])?>">
                <?=htmlspecialchars(mb_strimwidth($m['message'], 0, 60, '…'))?>
              </td>
              <td><?=$m['units_charged']?></td>
              <td><span class="badge badge-<?=$sc?>"><?=ucfirst($m['status'])?></span></td>
              <td style="font-size:11px"><?=$m['created_at'] ? date('d M Y H:i', strtotime($m['created_at'])) : '—'?></td>
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
        <a href="?<?=resellerRptQs(['page' => $page - 1])?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-chevron-left"></i> Prev</a>
      <?php endif; ?>
      <?php if ($page < $totalPages): ?>
        <a href="?<?=resellerRptQs(['page' => $page + 1])?>" class="btn btn-primary btn-sm">Next <i class="fa-solid fa-chevron-right"></i></a>
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
        filename: 'Reseller_Report_' + fromDate + '_to_' + toDate + '.pdf',
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

new Chart(document.getElementById('repChart'), {
    type: 'bar',
    data: {
        labels: {$tLabels} || [],
        datasets: [{
            label: 'Messages',
            data: {$tValues} || [],
            backgroundColor: 'rgba(0,200,150,0.7)',
            borderRadius: 6,
            borderSkipped: false
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { x: { grid: { display: false } }, y: { beginAtZero: true } }
    }
});
</script>
JS;
include __DIR__ . '/../includes/layout-footer.php';
?>
