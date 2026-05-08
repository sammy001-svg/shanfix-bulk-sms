<?php
$pageTitle = 'Scheduled SMS';
$breadcrumb = [['label'=>'Client'],['label'=>'Scheduled']];
require_once __DIR__ . '/layout.php';
$uid=$user['id'];
$scheduled=DB::query("SELECT * FROM campaigns WHERE user_id=? AND status='scheduled' ORDER BY scheduled_at ASC",[$uid]);
?>
<div class="page-header">
  <div><h1>Scheduled</h1><div class="subtitle">SMS campaigns queued for future delivery</div></div>
  <a href="/client/send-sms.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Schedule New</a>
</div>
<div class="card">
  <div class="table-wrapper">
    <table class="data-table">
      <thead><tr><th>Campaign</th><th>Sender ID</th><th>Recipients</th><th>Scheduled For</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (empty($scheduled)): ?>
          <tr><td colspan="5"><div class="empty-state"><div class="empty-icon">🗓️</div><h3>No Scheduled Messages</h3><p>Schedule a message to send it later.</p><a href="/client/send-sms.php" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Schedule One</a></div></td></tr>
        <?php else: foreach ($scheduled as $c): ?>
          <tr>
            <td><strong><?=htmlspecialchars($c['name'])?></strong><div style="font-size:11px;color:var(--text-secondary)"><?=htmlspecialchars(mb_strimwidth($c['message'],0,60,'...'))?></div></td>
            <td><code><?=htmlspecialchars($c['sender_id'])?></code></td>
            <td><?=number_format($c['total_count']??0)?></td>
            <td><strong style="color:var(--warning)"><?=date('d M Y H:i',strtotime($c['scheduled_at']))?></strong></td>
            <td>
              <form method="POST" action="/client/actions/cancel-campaign.php" style="display:inline">
                <input type="hidden" name="id" value="<?=$c['id']?>"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
                <button class="btn btn-danger btn-sm" onclick="return confirm('Cancel this scheduled SMS?')"><i class="fa-solid fa-xmark"></i> Cancel</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
