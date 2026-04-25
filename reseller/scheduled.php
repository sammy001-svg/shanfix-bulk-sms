<?php
$pageTitle = 'Scheduled SMS';
$breadcrumb = [['label'=>'Reseller'],['label'=>'Scheduled']];
require_once __DIR__ . '/layout.php';

$uid  = $user['id'];
$page = max(1,(int)($_GET['page']??1));
$perPage=20;$offset=($page-1)*$perPage;

$scheduled = DB::query("SELECT c.* FROM campaigns c WHERE c.user_id=? AND c.status='scheduled' ORDER BY c.scheduled_at ASC LIMIT $perPage OFFSET $offset",[$uid]);
$total     = DB::queryOne("SELECT COUNT(*) as c FROM campaigns WHERE user_id=? AND status='scheduled'",[$uid])['c']??0;
$totalPages= ceil($total/$perPage);
?>
<div class="page-header">
  <div><h1>Scheduled SMS</h1><div class="subtitle">Campaigns queued for future delivery</div></div>
  <a href="/reseller/send-sms.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Schedule New</a>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fa-solid fa-calendar-check" style="color:var(--primary)"></i> Scheduled Campaigns <span class="badge badge-warning"><?=$total?></span></h3>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead><tr><th>Campaign</th><th>Sender ID</th><th>Recipients</th><th>Scheduled For</th><th>Created</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (empty($scheduled)): ?>
          <tr><td colspan="6"><div class="empty-state">
            <div class="empty-icon">🗓️</div>
            <h3>No Scheduled Campaigns</h3>
            <p>All clear! You have no SMS messages waiting to be sent.</p>
            <a href="/reseller/send-sms.php" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Schedule One</a>
          </div></td></tr>
        <?php else: ?>
          <?php foreach ($scheduled as $c): ?>
            <tr>
              <td>
                <strong><?=htmlspecialchars($c['name'])?></strong>
                <div style="font-size:11px;color:var(--text-secondary);margin-top:2px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=htmlspecialchars($c['message'])?></div>
              </td>
              <td><code><?=htmlspecialchars($c['sender_id'])?></code></td>
              <td><?=number_format($c['recipients_count']??0)?></td>
              <td>
                <strong style="color:var(--warning)"><?=date('d M Y',strtotime($c['scheduled_at']))?></strong>
                <div style="font-size:11px;color:var(--text-secondary)"><?=date('H:i',strtotime($c['scheduled_at']))?></div>
              </td>
              <td style="font-size:12px"><?=date('d M Y',strtotime($c['created_at']))?></td>
              <td>
                <form method="POST" action="/reseller/actions/cancel-campaign.php" style="display:inline">
                  <input type="hidden" name="id" value="<?=$c['id']?>">
                  <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
                  <button class="btn btn-danger btn-sm" onclick="return confirm('Cancel this scheduled SMS?')">
                    <i class="fa-solid fa-xmark"></i> Cancel
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($totalPages>1): ?>
    <div class="card-footer"><div class="pagination">
      <?php for($p=1;$p<=$totalPages;$p++): ?><a href="?page=<?=$p?>" class="page-btn <?=$p===$page?'active':''?>"><?=$p?></a><?php endfor; ?>
    </div></div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
