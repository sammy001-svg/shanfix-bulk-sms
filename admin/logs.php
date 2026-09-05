<?php
$pageTitle = 'Message Logs';
$breadcrumb = [['label'=>'Admin'],['label'=>'Message Logs']];
require_once __DIR__ . '/layout.php';

$search = sanitize($_GET['q']??'');
$status = sanitize($_GET['status']??'');
$page   = max(1,(int)($_GET['page']??1));
$perPage= 30; $offset=($page-1)*$perPage;

$where='WHERE 1=1';$params=[];
if ($status){$where.=' AND m.status=?';$params[]=$status;}
if ($search){$where.=' AND (m.recipient LIKE ? OR m.sender_id LIKE ?)';$params[]="%$search%";$params[]="%$search%";}

// Unfiltered COUNT(*) is a full index scan on InnoDB — cache it for 60 s so
// paging through the log doesn't rescan the messages table on every click.
if (!$status && !$search) {
    if (!isset($_SESSION['_logs_total_ts']) || (time() - $_SESSION['_logs_total_ts']) > 60) {
        $_SESSION['_logs_total']    = (int)(DB::queryOne("SELECT COUNT(*) as c FROM messages m")['c'] ?? 0);
        $_SESSION['_logs_total_ts'] = time();
    }
    $total = $_SESSION['_logs_total'];
} else {
    $total = DB::queryOne("SELECT COUNT(*) as c FROM messages m $where",$params)['c']??0;
}
$messages= DB::query("SELECT m.*, u.name as user_name FROM messages m LEFT JOIN users u ON m.user_id=u.id $where ORDER BY m.created_at DESC LIMIT $perPage OFFSET $offset",$params);
$totalPages=ceil($total/$perPage);
?>
<div class="page-header">
  <div><h1>Message Logs</h1><div class="subtitle">Live message delivery log for all users</div></div>
  <div style="font-size:13px;color:var(--text-secondary)">Total: <strong><?=number_format($total)?></strong> messages</div>
</div>

<div class="card" style="margin-bottom:18px">
  <div class="card-body" style="padding:14px 18px">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
      <div class="input-group" style="flex:1;min-width:180px">
        <div class="input-group-text input-addon-left"><i class="fa-solid fa-magnifying-glass"></i></div>
        <input type="text" name="q" class="form-control with-left" placeholder="Recipient or Sender ID..." value="<?=htmlspecialchars($search)?>">
      </div>
      <select name="status" class="form-control" style="width:160px">
        <option value="">All Statuses</option>
        <?php foreach (['sent','delivered','failed','queued','undelivered'] as $s): ?>
          <option value="<?=$s?>" <?=$status===$s?'selected':''?>><?=ucfirst($s)?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
      <?php if($status||$search) echo '<a href="/admin/logs.php" class="btn btn-secondary">Clear</a>'; ?>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-wrapper">
    <table class="data-table">
      <thead><tr><th>Sent By</th><th>Sender ID</th><th>Recipient</th><th>Message</th><th>Units</th><th>Status</th><th>Sent At</th></tr></thead>
      <tbody>
        <?php if (empty($messages)): ?>
          <tr><td colspan="7" class="text-center text-muted" style="padding:30px">No messages found</td></tr>
        <?php else: ?>
          <?php foreach ($messages as $m): ?>
            <?php $sc=['sent'=>'success','delivered'=>'success','failed'=>'danger','queued'=>'warning','undelivered'=>'warning'][$m['status']]??'muted'; ?>
            <tr>
              <td style="font-size:12px"><?=htmlspecialchars($m['user_name']??'—')?></td>
              <td><code style="font-size:12px"><?=htmlspecialchars($m['sender_id'])?></code></td>
              <td style="font-size:13px"><?=htmlspecialchars($m['recipient'])?></td>
              <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;color:var(--text-secondary)" title="<?=htmlspecialchars($m['message'])?>"><?=htmlspecialchars($m['message'])?></td>
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
      <?php render_pagination($page, (int)$totalPages, array_filter(['q' => $search, 'status' => $status])); ?>
    </div></div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
