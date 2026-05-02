<?php
$pageTitle = 'Reports';
$breadcrumb = [['label'=>'Admin'],['label'=>'Reports']];
require_once __DIR__ . '/layout.php';

$from   = sanitize($_GET['from'] ?? date('Y-m-01'));
$to     = sanitize($_GET['to']   ?? date('Y-m-d'));
$userId = (int)($_GET['user_id']??0);

$where  = "WHERE DATE(m.created_at) BETWEEN ? AND ?";
$params = [$from, $to];
if ($userId){ $where .= ' AND m.user_id=?'; $params[] = $userId; }

$summary = DB::queryOne("SELECT COUNT(*) as total, SUM(status='sent') as sent, SUM(status='delivered') as delivered, SUM(status='failed') as failed, COALESCE(SUM(units_charged),0) as units FROM messages m $where", $params);

$page    = max(1,(int)($_GET['page']??1));
$perPage = 25; $offset = ($page-1)*$perPage;
$messages= DB::query("SELECT m.*, u.name as user_name FROM messages m JOIN users u ON m.user_id=u.id $where ORDER BY m.created_at DESC LIMIT $perPage OFFSET $offset", $params);
$total   = DB::queryOne("SELECT COUNT(*) as c FROM messages m $where", $params)['c']??0;
$totalPages = ceil($total/$perPage);

$users = DB::query("SELECT id, name FROM users WHERE role!='admin' ORDER BY name");
?>
<div class="page-header">
  <div><h1>Reports</h1><div class="subtitle">Message log and delivery reports</div></div>
  <div style="display:flex;gap:8px">
    <button onclick="downloadPDF(event)" class="btn btn-secondary"><i class="fa-solid fa-file-pdf"></i> Download PDF</button>
    <a href="?export=1&from=<?=$from?>&to=<?=$to?>&user_id=<?=$userId?>" class="btn btn-secondary"><i class="fa-solid fa-download"></i> Export CSV</a>
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
      <button type="submit" class="btn btn-primary" style="align-self:flex-end"><i class="fa-solid fa-filter"></i> Filter</button>
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

<!-- Table -->
<div class="card" id="message-log-section">
  <div class="card-header">
    <h3 class="card-title"><i class="fa-solid fa-file-lines" style="color:var(--primary)"></i> Messages <span class="badge badge-muted"><?=$total?></span></h3>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead><tr><th>User</th><th>Sender ID</th><th>Recipient</th><th>Message</th><th>Units</th><th>Status</th><th>Sent At</th></tr></thead>
      <tbody>
        <?php if (empty($messages)): ?>
          <tr><td colspan="7" class="text-center text-muted" style="padding:30px">No messages found for selected filters</td></tr>
        <?php else: ?>
          <?php foreach ($messages as $m): ?>
            <?php $sc=['sent'=>'success','delivered'=>'success','failed'=>'danger','queued'=>'warning','undelivered'=>'warning'][$m['status']]??'muted'; ?>
            <tr>
              <td style="font-size:12px"><?=htmlspecialchars($m['user_name'])?></td>
              <td><code><?=htmlspecialchars($m['sender_id'])?></code></td>
              <td><?=htmlspecialchars($m['recipient'])?></td>
              <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px" title="<?=htmlspecialchars($m['message'])?>"><?=htmlspecialchars($m['message'])?></td>
              <td><?=$m['units_charged']?></td>
              <td><span class="badge badge-<?=$sc?>"><?=ucfirst($m['status'])?></span></td>
              <td style="font-size:11px"><?=$m['sent_at']?date('d M Y H:i',strtotime($m['sent_at'])):'—'?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($totalPages>1): ?>
    <div class="card-footer"><div class="pagination">
      <?php for($p=1;$p<=$totalPages;$p++): ?><a href="?page=<?=$p?>&from=<?=$from?>&to=<?=$to?>&user_id=<?=$userId?>" class="page-btn <?=$p===$page?'active':''?>"><?=$p?></a><?php endfor; ?>
    </div></div>
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
        filename:     'Admin_Full_Report_' + fromDate + '_to_' + toDate + '.pdf',
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
</script>
JS;
include __DIR__ . '/../includes/layout-footer.php';
?>
