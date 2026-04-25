<?php
$pageTitle = 'Sender IDs';
$breadcrumb = [['label'=>'Reseller'],['label'=>'Sender IDs']];
require_once __DIR__ . '/layout.php';

$uid      = $user['id'];
$senderIds = DB::query("SELECT * FROM sender_ids WHERE user_id = ? ORDER BY created_at DESC", [$uid]);
?>

<div class="page-header">
  <div>
    <h1>Sender IDs</h1>
    <div class="subtitle">Request and manage your SMS sender identifiers</div>
  </div>
  <button class="btn btn-primary" onclick="openModal('senderModal')">
    <i class="fa-solid fa-plus"></i> Request Sender ID
  </button>
</div>

<div class="alert alert-info" style="margin-bottom:20px">
  <i class="fa-solid fa-circle-info"></i>
  Sender IDs must be approved by an administrator before use. Approvals typically take 1–3 business days.
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fa-solid fa-id-badge" style="color:var(--primary)"></i> My Sender IDs</h3>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr><th>Sender ID</th><th>Purpose</th><th>Status</th><th>Requested</th><th>Approved/Rejected</th></tr>
      </thead>
      <tbody>
        <?php if (empty($senderIds)): ?>
          <tr><td colspan="5">
            <div class="empty-state">
              <div class="empty-icon">🪪</div>
              <h3>No Sender IDs yet</h3>
              <p>Request a sender ID to brand your SMS messages.</p>
              <button class="btn btn-primary" onclick="openModal('senderModal')">Request Sender ID</button>
            </div>
          </td></tr>
        <?php else: ?>
          <?php foreach ($senderIds as $s): ?>
            <?php $sc = ['approved'=>'success','rejected'=>'danger','pending'=>'warning'][$s['status']] ?? 'muted'; ?>
            <tr>
              <td><strong style="font-size:15px;letter-spacing:0.02em"><?= htmlspecialchars($s['sender_id']) ?></strong></td>
              <td style="max-width:250px;color:var(--text-secondary)"><?= htmlspecialchars($s['purpose'] ?? '—') ?></td>
              <td>
                <span class="badge badge-<?= $sc ?>">
                  <?= $s['status'] === 'pending' ? '⏳ Pending Review' : ($s['status'] === 'approved' ? '✅ Approved' : '❌ Rejected') ?>
                </span>
                <?php if ($s['status'] === 'rejected' && $s['reject_reason']): ?>
                  <div style="font-size:11px;color:var(--danger);margin-top:4px"><?= htmlspecialchars($s['reject_reason']) ?></div>
                <?php endif; ?>
              </td>
              <td style="font-size:12px"><?= date('d M Y', strtotime($s['created_at'])) ?></td>
              <td style="font-size:12px"><?= $s['approved_at'] ? date('d M Y', strtotime($s['approved_at'])) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Request Modal -->
<div class="modal-overlay" id="senderModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fa-solid fa-id-badge" style="color:var(--primary)"></i> Request Sender ID</h3>
      <button class="modal-close" onclick="closeModal('senderModal')">×</button>
    </div>
    <form method="POST" action="/reseller/actions/request-sender-id.php">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Sender ID <span class="required">*</span></label>
          <input type="text" name="sender_id" class="form-control" placeholder="e.g. BRANDCO" maxlength="11" required style="text-transform:uppercase;font-weight:700;letter-spacing:0.05em">
          <div class="form-hint">Max 11 characters. Alphanumeric only. This is how recipients will see your name.</div>
        </div>
        <div class="form-group">
          <label class="form-label">Purpose / Description <span class="required">*</span></label>
          <textarea name="purpose" class="form-control" placeholder="Briefly describe the purpose of this sender ID (e.g. marketing campaigns for retail clients)..." required rows="4"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('senderModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Submit Request</button>
      </div>
    </form>
  </div>
</div>

<?php
$extraScript = isset($_GET['new']) ? "<script>openModal('senderModal')</script>" : '';
include __DIR__ . '/../includes/layout-footer.php';
?>
