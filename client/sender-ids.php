<?php
// Client Sender IDs page
$pageTitle = 'Sender IDs';
$breadcrumb = [['label'=>'Client'],['label'=>'Sender IDs']];
require_once __DIR__ . '/layout.php';

$uid      = $user['id'];
$senderIds= DB::query("SELECT * FROM sender_ids WHERE user_id=? ORDER BY created_at DESC",[$uid]);
?>
<div class="page-header">
  <div><h1>Sender IDs</h1><div class="subtitle">Your approved sender identifiers</div></div>
  <button class="btn btn-primary" onclick="openModal('requestModal')"><i class="fa-solid fa-plus"></i> Request Sender ID</button>
</div>

<div class="card">
  <div class="table-wrapper">
    <table class="data-table">
      <thead><tr><th>Sender ID</th><th>Status</th><th>Requested</th><th>Approved</th><th>Note</th></tr></thead>
      <tbody>
        <?php if (empty($senderIds)): ?>
          <tr><td colspan="5"><div class="empty-state">
            <div class="empty-icon">🪪</div><h3>No Sender IDs</h3>
            <p>Request a Sender ID to personalise your messages.</p>
            <button class="btn btn-primary" onclick="openModal('requestModal')"><i class="fa-solid fa-plus"></i> Request Now</button>
          </div></td></tr>
        <?php else: foreach ($senderIds as $s):
          $sc=['approved'=>'success','pending'=>'warning','rejected'=>'danger'][$s['status']]??'muted'; ?>
          <tr>
            <td><code style="font-size:14px;font-weight:700"><?=htmlspecialchars($s['sender_id'])?></code></td>
            <td><span class="badge badge-<?=$sc?>"><?=ucfirst($s['status'])?></span></td>
            <td style="font-size:12px"><?=date('d M Y',strtotime($s['created_at']))?></td>
            <td style="font-size:12px"><?=$s['approved_at']?date('d M Y',strtotime($s['approved_at'])):'—'?></td>
            <td style="font-size:12px;color:var(--text-secondary)"><?=htmlspecialchars($s['rejection_reason']??'—')?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="requestModal">
  <div class="modal"><div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-id-badge" style="color:var(--primary)"></i> Request Sender ID</h3><button class="modal-close" onclick="closeModal('requestModal')">×</button></div>
    <form method="POST" action="/client/actions/request-sender-id.php"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Sender ID <span class="required">*</span></label><input type="text" name="sender_id" class="form-control" maxlength="11" placeholder="e.g. MyBrand" required><div class="form-hint">Max 11 characters, letters and numbers only. No spaces.</div></div>
        <div class="form-group"><label class="form-label">Purpose <span class="required">*</span></label><textarea name="purpose" class="form-control" rows="3" placeholder="Briefly describe what this sender ID will be used for..." required></textarea></div>
        <div class="alert alert-info"><i class="fa-solid fa-hourglass-half"></i> Sender IDs are reviewed within 24–48 hours.</div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('requestModal')">Cancel</button><button type="submit" class="btn btn-primary">Submit Request</button></div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
