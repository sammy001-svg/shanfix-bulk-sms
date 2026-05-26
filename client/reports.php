<?php
// Client reports page — mirrors reseller/reports.php
$pageTitle = 'Reports';
$breadcrumb = [['label'=>'Client'],['label'=>'Reports']];
require_once __DIR__ . '/layout.php';

$uid        = $user['id'];
$from       = sanitize($_GET['from'] ?? date('Y-m-01'));
$to         = sanitize($_GET['to']   ?? date('Y-m-d'));
$campaignId = (int)($_GET['campaign_id'] ?? 0);

$where  = "WHERE m.user_id=? AND DATE(m.created_at) BETWEEN ? AND ?";
$params = [$uid, $from, $to];

if ($campaignId > 0) {
    $where  .= " AND m.campaign_id=?";
    $params[] = $campaignId;
    $campaignRow = DB::queryOne("SELECT name FROM campaigns WHERE id=? AND user_id=?", [$campaignId, $uid]);
    $campaignName = $campaignRow['name'] ?? '';
}

$summary = DB::queryOne("SELECT COUNT(*) as total, SUM(status='sent') as sent, SUM(status='sent' OR status='delivered') as delivered, SUM(status='failed') as failed, COALESCE(SUM(units_charged),0) as units FROM messages m $where", $params);

$trendParams = [$uid, $from, $to];
$trendWhere  = "user_id=? AND DATE(created_at) BETWEEN ? AND ?";
if ($campaignId > 0) { $trendWhere .= " AND campaign_id=?"; $trendParams[] = $campaignId; }
$trend   = DB::query("SELECT DATE(created_at) as day, COUNT(*) as total FROM messages WHERE $trendWhere GROUP BY day ORDER BY day", $trendParams);
$tLabels = json_encode(array_column($trend,'day'));
$tValues = json_encode(array_column($trend,'total'));

$page   = max(1,(int)($_GET['page']??1));
$perPage= 25; $offset=($page-1)*$perPage;
$messages= DB::query("SELECT m.* FROM messages m $where ORDER BY m.created_at DESC LIMIT $perPage OFFSET $offset",$params);
$total   = DB::queryOne("SELECT COUNT(*) as c FROM messages m $where",$params)['c']??0;
$totalPages=ceil($total/$perPage);
$extraQs = ($campaignId > 0 ? "&campaign_id=$campaignId" : '');
?>
<div class="page-header">
  <div>
    <h1>Reports<?= $campaignId > 0 ? ': ' . htmlspecialchars($campaignName) : '' ?></h1>
    <div class="subtitle"><?= $campaignId > 0 ? '<a href="/client/campaigns.php">← Back to Campaigns</a>' : 'Your message delivery reports' ?></div>
  </div>
  <div style="display:flex; gap:10px">
    <a href="/client/actions/download-report.php?from=<?=$from?>&to=<?=$to?><?=$extraQs?>&format=csv" class="btn btn-outline" title="Download CSV"><i class="fa-solid fa-file-csv"></i> CSV</a>
    <a href="/client/actions/download-report.php?from=<?=$from?>&to=<?=$to?><?=$extraQs?>&format=excel" class="btn btn-outline" title="Download Excel"><i class="fa-solid fa-file-excel"></i> Excel</a>
    <button onclick="downloadPDF(event)" class="btn btn-secondary"><i class="fa-solid fa-file-pdf"></i> Download PDF</button>
  </div>
</div>
<div id="report-content">
<div class="card" style="margin-bottom:18px">
  <div class="card-body" style="padding:14px 18px">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
      <?php if ($campaignId > 0): ?><input type="hidden" name="campaign_id" value="<?=$campaignId?>"><?php endif; ?>
      <div class="form-group" style="margin:0"><label class="form-label" style="font-size:11px">From</label><input type="date" name="from" class="form-control" value="<?=$from?>" style="width:150px"></div>
      <div class="form-group" style="margin:0"><label class="form-label" style="font-size:11px">To</label><input type="date" name="to" class="form-control" value="<?=$to?>" style="width:150px"></div>
      <button type="submit" class="btn btn-primary" style="align-self:flex-end"><i class="fa-solid fa-filter"></i> Filter</button>
    </form>
  </div>
</div>
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:20px">
  <div class="stat-card"><div class="stat-icon blue"><i class="fa-solid fa-message"></i></div><div class="stat-info"><div class="stat-label">Total</div><div class="stat-value"><?=number_format($summary['total']??0)?></div></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fa-solid fa-check"></i></div><div class="stat-info"><div class="stat-label">Sent</div><div class="stat-value"><?=number_format($summary['sent']??0)?></div></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fa-solid fa-check-double"></i></div><div class="stat-info"><div class="stat-label">Delivered</div><div class="stat-value"><?=number_format($summary['delivered']??0)?></div></div></div>
  <div class="stat-card"><div class="stat-icon orange"><i class="fa-solid fa-xmark"></i></div><div class="stat-info"><div class="stat-label">Failed</div><div class="stat-value"><?=number_format($summary['failed']??0)?></div></div></div>
  <div class="stat-card"><div class="stat-icon orange"><i class="fa-solid fa-coins"></i></div><div class="stat-info"><div class="stat-label">Units Used</div><div class="stat-value"><?=number_format($summary['units']??0,2)?></div></div></div>
</div>
<div class="card" style="margin-bottom:20px">
  <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-chart-bar" style="color:var(--primary)"></i> Messages (<?=htmlspecialchars($from)?> – <?=htmlspecialchars($to)?>)</h3></div>
  <div class="card-body"><div class="chart-container" style="height:200px"><canvas id="repChart"></canvas></div></div>
</div>
<div class="card" id="message-log-section">
  <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-file-lines" style="color:var(--primary)"></i> Message Log <span class="badge badge-muted"><?=$total?></span></h3></div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead><tr><th>Sender ID</th><th>Recipient</th><th>Message</th><th>Units</th><th>Status</th><th>Failure Reason</th><th>Delivered At</th><th>Created</th></tr></thead>
      <tbody>
        <?php if (empty($messages)): ?>
          <tr><td colspan="8" class="text-center text-muted" style="padding:30px">No messages found for selected period</td></tr>
        <?php else: foreach ($messages as $m):
          $sc=['sent'=>'success','delivered'=>'success','failed'=>'danger','queued'=>'warning','undelivered'=>'warning'][$m['status']]??'muted'; ?>
          <tr>
            <td><code><?=htmlspecialchars($m['sender_id'])?></code></td>
            <td><?=htmlspecialchars($m['recipient'])?></td>
            <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;color:var(--text-secondary)"><?=htmlspecialchars($m['message'])?></td>
            <td><?=$m['units_charged']?></td>
            <td><span class="badge badge-<?=$sc?>"><?=ucfirst($m['status'])?></span></td>
            <td style="font-size:11px;color:var(--danger);max-width:180px"><?=htmlspecialchars($m['failed_reason']??'')?></td>
            <td style="font-size:11px"><?=$m['sent_at']?date('d M Y H:i',strtotime($m['sent_at'])):'—'?></td>
            <td style="font-size:11px"><?=$m['created_at']?date('d M Y H:i',strtotime($m['created_at'])):'—'?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($totalPages>1): ?>
    <div class="card-footer"><div class="pagination"><?php for($p=1;$p<=$totalPages;$p++): ?><a href="?page=<?=$p?>&from=<?=$from?>&to=<?=$to?><?=$extraQs?>" class="page-btn <?=$p===$page?'active':''?>"><?=$p?></a><?php endfor; ?></div></div>
  <?php endif; ?>
</div>
</div>
<?php
$extraScript = <<<JS
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadPDF(event) {
    const element = document.getElementById('message-log-section');
    const btn = event.currentTarget;
    const originalContent = btn.innerHTML;
    
    // Use the dates from PHP
    const fromDate = '{$from}';
    const toDate = '{$to}';
    
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating...';
    btn.disabled = true;

    const opt = {
        margin:       [10, 10],
        filename:     'SMS_Report_' + fromDate + '_to_' + toDate + '.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true, logging: false },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    html2pdf().set(opt).from(element).toPdf().get('pdf').then((pdf) => {
        window.open(pdf.output('bloburl'), '_blank');
        btn.innerHTML = originalContent;
        btn.disabled = false;
    }).catch(err => {
        console.error('PDF Error:', err);
        btn.innerHTML = originalContent;
        btn.disabled = false;
        alert('Failed to generate PDF. Please try again.');
    });
}

new Chart(document.getElementById('repChart'),{type:'bar',data:{labels:{$tLabels}||[],datasets:[{label:'Messages',data:{$tValues}||[],backgroundColor:'rgba(0,200,150,0.7)',borderRadius:6,borderSkipped:false}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{grid:{display:false}},y:{beginAtZero:true}}}});
</script>
JS;
include __DIR__ . '/../includes/layout-footer.php';
?>
