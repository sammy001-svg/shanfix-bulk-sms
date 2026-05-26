<?php
// Client reports page
$pageTitle = 'Reports';
$breadcrumb = [['label'=>'Client'],['label'=>'Reports']];
require_once __DIR__ . '/layout.php';

$uid        = $user['id'];
$from       = sanitize($_GET['from']        ?? date('Y-m-01'));
$to         = sanitize($_GET['to']          ?? date('Y-m-d'));
$campaignId = (int)($_GET['campaign_id']    ?? 0);
$statusFilter = sanitize($_GET['status']    ?? '');
$validStatuses = ['sent','delivered','failed','queued','undelivered'];
if (!in_array($statusFilter, $validStatuses, true)) $statusFilter = '';

$allowedPerPage = [10, 50, 100, 1000, 10000];
$perPage = (int)($_GET['per_page'] ?? 50);
if (!in_array($perPage, $allowedPerPage, true)) $perPage = 50;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// Base WHERE — used for summary stats and trend chart (no status filter)
$baseWhere  = "WHERE m.user_id=? AND m.created_at >= ? AND m.created_at < DATE_ADD(?, INTERVAL 1 DAY)";
$baseParams = [$uid, $from, $to];

if ($campaignId > 0) {
    $baseWhere   .= " AND m.campaign_id=?";
    $baseParams[] = $campaignId;
    $campaignRow  = DB::queryOne("SELECT name FROM campaigns WHERE id=? AND user_id=?", [$campaignId, $uid]);
    $campaignName = $campaignRow['name'] ?? '';
}

$summary = DB::queryOne(
    "SELECT COUNT(*) as total,
            SUM(status='sent') as sent,
            SUM(status='sent' OR status='delivered') as delivered,
            SUM(status='failed' OR status='undelivered') as failed,
            COALESCE(SUM(units_charged),0) as units
     FROM messages m $baseWhere",
    $baseParams
);

$trendWhere  = "user_id=? AND created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)";
$trendParams = [$uid, $from, $to];
if ($campaignId > 0) { $trendWhere .= " AND campaign_id=?"; $trendParams[] = $campaignId; }
$trend   = DB::query("SELECT DATE(created_at) as day, COUNT(*) as total FROM messages WHERE $trendWhere GROUP BY day ORDER BY day", $trendParams);
$tLabels = json_encode(array_column($trend, 'day'));
$tValues = json_encode(array_column($trend, 'total'));

// Log WHERE — extends base with optional status filter
$logWhere  = $baseWhere;
$logParams = $baseParams;
if ($statusFilter) {
    $logWhere   .= " AND m.status = ?";
    $logParams[] = $statusFilter;
}

$messages   = DB::query("SELECT m.* FROM messages m $logWhere ORDER BY m.created_at DESC LIMIT $perPage OFFSET $offset", $logParams);
$logTotal   = (int)(DB::queryOne("SELECT COUNT(*) as c FROM messages m $logWhere", $logParams)['c'] ?? 0);
$totalPages = $perPage > 0 ? (int)ceil($logTotal / $perPage) : 1;
$fromRow    = $logTotal > 0 ? $offset + 1 : 0;
$toRow      = min($offset + $perPage, $logTotal);

// Query-string fragments that must persist across all links
$extraQs = ($campaignId > 0 ? '&campaign_id=' . $campaignId : '')
         . ($statusFilter    ? '&status=' . urlencode($statusFilter) : '')
         . '&per_page=' . $perPage;
?>
<div class="page-header">
  <div>
    <h1>Reports<?= $campaignId > 0 ? ': ' . htmlspecialchars($campaignName) : '' ?></h1>
    <div class="subtitle"><?= $campaignId > 0
        ? '<a href="/client/campaigns.php">← Back to Campaigns</a>'
        : 'Your message delivery reports' ?></div>
  </div>
  <div style="display:flex;gap:10px;flex-wrap:wrap">
    <a href="/client/actions/download-report.php?from=<?=$from?>&to=<?=$to?><?=$extraQs?>&format=csv"
       class="btn btn-outline" title="Download CSV">
      <i class="fa-solid fa-file-csv"></i> CSV
    </a>
    <a href="/client/actions/download-report.php?from=<?=$from?>&to=<?=$to?><?=$extraQs?>&format=excel"
       class="btn btn-outline" title="Download Excel">
      <i class="fa-solid fa-file-excel"></i> Excel
    </a>
    <button onclick="downloadPDF(event)" class="btn btn-secondary">
      <i class="fa-solid fa-file-pdf"></i> PDF
    </button>
  </div>
</div>

<div id="report-content">

<!-- Filter bar -->
<div class="card" style="margin-bottom:18px">
  <div class="card-body" style="padding:14px 18px">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
      <?php if ($campaignId > 0): ?>
        <input type="hidden" name="campaign_id" value="<?=$campaignId?>">
      <?php endif; ?>
      <input type="hidden" name="per_page" value="<?=$perPage?>">
      <div class="form-group" style="margin:0">
        <label class="form-label" style="font-size:11px">From</label>
        <input type="date" name="from" class="form-control" value="<?=$from?>" style="width:148px">
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label" style="font-size:11px">To</label>
        <input type="date" name="to" class="form-control" value="<?=$to?>" style="width:148px">
      </div>
      <div class="form-group" style="margin:0">
        <label class="form-label" style="font-size:11px">Status</label>
        <select name="status" class="form-control" style="width:148px">
          <option value="">All Statuses</option>
          <?php foreach (['sent'=>'Sent','delivered'=>'Delivered','failed'=>'Failed','queued'=>'Queued','undelivered'=>'Undelivered'] as $v=>$l): ?>
            <option value="<?=$v?>" <?=$statusFilter===$v?'selected':''?>><?=$l?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
      <?php if ($statusFilter || $from !== date('Y-m-01') || $to !== date('Y-m-d')): ?>
        <a href="?<?=$campaignId>0?'campaign_id='.$campaignId.'&':''?>from=<?=date('Y-m-01')?>&to=<?=date('Y-m-d')?>"
           class="btn btn-outline" title="Clear filters">
          <i class="fa-solid fa-xmark"></i> Clear
        </a>
      <?php endif; ?>
    </form>
  </div>
</div>

<!-- Summary stats -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:20px">
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fa-solid fa-message"></i></div>
    <div class="stat-info"><div class="stat-label">Total</div><div class="stat-value"><?=number_format($summary['total']??0)?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fa-solid fa-check"></i></div>
    <div class="stat-info"><div class="stat-label">Sent</div><div class="stat-value"><?=number_format($summary['sent']??0)?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fa-solid fa-check-double"></i></div>
    <div class="stat-info"><div class="stat-label">Delivered</div><div class="stat-value"><?=number_format($summary['delivered']??0)?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange"><i class="fa-solid fa-xmark"></i></div>
    <div class="stat-info"><div class="stat-label">Failed</div><div class="stat-value"><?=number_format($summary['failed']??0)?></div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange"><i class="fa-solid fa-coins"></i></div>
    <div class="stat-info"><div class="stat-label">Units Used</div><div class="stat-value"><?=number_format($summary['units']??0,2)?></div></div>
  </div>
</div>

<!-- Trend chart -->
<div class="card" style="margin-bottom:20px">
  <div class="card-header">
    <h3 class="card-title">
      <i class="fa-solid fa-chart-bar" style="color:var(--primary)"></i>
      Messages &mdash; <?=htmlspecialchars($from)?> to <?=htmlspecialchars($to)?>
    </h3>
  </div>
  <div class="card-body">
    <div class="chart-container" style="height:200px"><canvas id="repChart"></canvas></div>
  </div>
</div>

<!-- Message log -->
<div class="card" id="message-log-section">
  <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
    <h3 class="card-title" style="margin:0">
      <i class="fa-solid fa-file-lines" style="color:var(--primary)"></i>
      Message Log
      <span class="badge badge-muted" style="margin-left:6px"><?=number_format($logTotal)?></span>
      <?php if ($statusFilter): ?>
        <span class="badge badge-<?=['sent'=>'success','delivered'=>'success','failed'=>'danger','queued'=>'warning','undelivered'=>'warning'][$statusFilter]??'muted'?>"
              style="margin-left:4px"><?=ucfirst($statusFilter)?></span>
      <?php endif; ?>
    </h3>
    <div style="display:flex;align-items:center;gap:8px">
      <span style="font-size:12px;color:var(--text-muted)">Rows per page:</span>
      <div style="display:flex;gap:4px">
        <?php foreach ([10, 50, 100, 1000, 10000] as $opt): ?>
          <a href="?page=1&from=<?=$from?>&to=<?=$to?>&per_page=<?=$opt?><?= ($campaignId>0?'&campaign_id='.$campaignId:'') . ($statusFilter?'&status='.urlencode($statusFilter):'') ?>"
             class="per-page-btn <?=$perPage===$opt?'active':''?>"><?=number_format($opt)?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Sender ID</th>
          <th>Recipient</th>
          <th>Message</th>
          <th>Units</th>
          <th>Status</th>
          <th>Failure Reason</th>
          <th>Delivered At</th>
          <th>Created</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($messages)): ?>
          <tr>
            <td colspan="9" style="text-align:center;padding:40px;color:var(--text-muted)">
              <i class="fa-regular fa-folder-open" style="font-size:28px;display:block;margin-bottom:10px;opacity:.4"></i>
              No messages found for the selected filters
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($messages as $i => $m):
            $sc = ['sent'=>'success','delivered'=>'success','failed'=>'danger','queued'=>'warning','undelivered'=>'warning'][$m['status']] ?? 'muted';
            $fullMsg = $m['message'];
            $shortMsg = mb_strlen($fullMsg) > 60 ? mb_substr($fullMsg, 0, 60) . '…' : $fullMsg;
          ?>
          <tr>
            <td style="color:var(--text-muted);font-size:11px"><?=number_format($offset + $i + 1)?></td>
            <td><code style="font-size:12px"><?=htmlspecialchars($m['sender_id'])?></code></td>
            <td style="font-size:13px;white-space:nowrap"><?=htmlspecialchars($m['recipient'])?></td>
            <td style="max-width:220px">
              <span class="msg-cell" title="<?=htmlspecialchars($fullMsg)?>"
                    style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;color:var(--text-secondary);cursor:default">
                <?=htmlspecialchars($shortMsg)?>
              </span>
            </td>
            <td style="font-size:12px;white-space:nowrap"><?=htmlspecialchars($m['units_charged'])?></td>
            <td><span class="badge badge-<?=$sc?>"><?=ucfirst($m['status'])?></span></td>
            <td style="font-size:11px;color:var(--danger);max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                title="<?=htmlspecialchars($m['failed_reason']??'')?>">
              <?=htmlspecialchars($m['failed_reason']??'')?>&nbsp;
            </td>
            <td style="font-size:11px;white-space:nowrap;color:var(--text-muted)">
              <?=$m['delivered_at'] ? date('d M Y H:i', strtotime($m['delivered_at'])) : '—'?>
            </td>
            <td style="font-size:11px;white-space:nowrap;color:var(--text-muted)">
              <?=$m['created_at'] ? date('d M Y H:i', strtotime($m['created_at'])) : '—'?>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination footer -->
  <?php if ($logTotal > 0): ?>
  <div class="card-footer" style="display:flex;align-items:center;justify-content:space-between;padding:12px 20px;gap:12px;flex-wrap:wrap">
    <div style="font-size:13px;color:var(--text-muted)">
      Showing <strong><?=number_format($fromRow)?></strong> – <strong><?=number_format($toRow)?></strong>
      of <strong><?=number_format($logTotal)?></strong> messages
      <?php if ($logTotal !== (int)($summary['total'] ?? 0)): ?>
        <span style="font-size:11px">(filtered)</span>
      <?php endif; ?>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
      <?php if ($page > 1): ?>
        <a href="?page=<?=$page-1?>&from=<?=$from?>&to=<?=$to?><?=$extraQs?>" class="btn btn-outline btn-sm">
          <i class="fa-solid fa-chevron-left"></i> Previous
        </a>
      <?php else: ?>
        <button class="btn btn-outline btn-sm" disabled style="opacity:.4">
          <i class="fa-solid fa-chevron-left"></i> Previous
        </button>
      <?php endif; ?>

      <span style="font-size:12px;color:var(--text-muted);padding:0 4px">
        Page <?=$page?> of <?=number_format($totalPages)?>
      </span>

      <?php if ($page < $totalPages): ?>
        <a href="?page=<?=$page+1?>&from=<?=$from?>&to=<?=$to?><?=$extraQs?>" class="btn btn-outline btn-sm">
          Next <i class="fa-solid fa-chevron-right"></i>
        </a>
      <?php else: ?>
        <button class="btn btn-outline btn-sm" disabled style="opacity:.4">
          Next <i class="fa-solid fa-chevron-right"></i>
        </button>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div><!-- /message-log-section -->
</div><!-- /report-content -->

<?php
$extraScript = <<<JS
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<style>
.per-page-btn {
  display:inline-flex; align-items:center; justify-content:center;
  padding:4px 10px; border-radius:5px; font-size:12px; font-weight:600;
  border:1px solid var(--border); color:var(--text-muted); text-decoration:none;
  background:transparent; transition:all .15s;
}
.per-page-btn:hover { border-color:var(--primary); color:var(--primary); }
.per-page-btn.active { background:var(--primary); border-color:var(--primary); color:#fff; }
</style>
<script>
function downloadPDF(event) {
    const element = document.getElementById('report-content');
    const btn = event.currentTarget;
    const orig = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating...';
    btn.disabled = true;
    const opt = {
        margin: [8, 8],
        filename: 'SMS_Report_{$from}_to_{$to}.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, logging: false },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' }
    };
    html2pdf().set(opt).from(element).toPdf().get('pdf').then(pdf => {
        window.open(pdf.output('bloburl'), '_blank');
        btn.innerHTML = orig;
        btn.disabled = false;
    }).catch(() => {
        btn.innerHTML = orig;
        btn.disabled = false;
        alert('PDF generation failed. Try downloading as CSV instead.');
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
