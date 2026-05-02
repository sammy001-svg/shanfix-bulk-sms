<?php
$pageTitle = 'USSD Code Requests';
$breadcrumb = [['label'=>'Admin'],['label'=>'USSD Requests']];
require_once __DIR__ . '/layout.php';

$filter = sanitize($_GET['status'] ?? '');
$where  = $filter ? "WHERE uc.status = '$filter'" : '';
$ussdRequests = DB::query("
    SELECT uc.*, u.name as user_name, u.email as user_email, u.role as user_role 
    FROM ussd_codes uc 
    JOIN users u ON uc.user_id = u.id 
    $where 
    ORDER BY uc.created_at DESC
");
?>

<div class="page-header">
  <div><h1>USSD Requests</h1><div class="subtitle">Review and manage USSD service applications</div></div>
  <div class="tabs">
      <a href="?" class="tab-btn <?= !$filter?'active':'' ?>">All</a>
      <a href="?status=pending"  class="tab-btn <?= $filter==='pending'?'active':'' ?>">⏳ Pending</a>
      <a href="?status=approved" class="tab-btn <?= $filter==='approved'?'active':'' ?>">✅ Approved</a>
      <a href="?status=rejected" class="tab-btn <?= $filter==='rejected'?'active':'' ?>">❌ Rejected</a>
  </div>
</div>

<div class="card">
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
          <tr>
              <th>Requested Code</th>
              <th>Type</th>
              <th>User</th>
              <th>Purpose</th>
              <th>Status</th>
              <th>Date</th>
              <th style="text-align:right">Actions</th>
          </tr>
      </thead>
      <tbody>
        <?php if (empty($ussdRequests)): ?>
          <tr><td colspan="7" class="text-center text-muted" style="padding:30px">No USSD requests found</td></tr>
        <?php else: ?>
          <?php foreach ($ussdRequests as $r): ?>
            <?php $statusColor = ['approved'=>'success','rejected'=>'danger','pending'=>'warning'][$r['status']] ?? 'muted'; ?>
            <tr>
              <td>
                  <div style="font-weight:700; font-size:15px"><?= $r['requested_code'] ?: '<span class="text-muted"><i>Not Specified</i></span>' ?></div>
              </td>
              <td><span class="badge badge-purple"><?= strtoupper($r['type']) ?></span></td>
              <td>
                  <div><?= htmlspecialchars($r['user_name']) ?></div>
                  <div style="font-size:11px; color:var(--text-secondary)"><?= ucfirst($r['user_role']) ?> • <?= htmlspecialchars($r['user_email']) ?></div>
              </td>
              <td style="max-width:300px">
                  <div class="text-truncate" title="<?= htmlspecialchars($r['purpose']) ?>"><?= htmlspecialchars($r['purpose']) ?></div>
              </td>
              <td><span class="badge badge-<?= $statusColor ?>"><?= ucfirst($r['status']) ?></span></td>
              <td style="font-size:12px"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
              <td style="text-align:right">
                <div class="btn-group" style="justify-content: flex-end">
                  <?php if ($r['status'] === 'pending'): ?>
                    <button class="btn btn-primary btn-sm" onclick="openApprove(<?= $r['id'] ?>, '<?= addslashes($r['requested_code'] ?? '') ?>')" title="Approve">
                        <i class="fa-solid fa-check"></i> Approve
                    </button>
                    <button class="btn btn-warning btn-sm" onclick="openReject(<?= $r['id'] ?>)" title="Reject">
                        <i class="fa-solid fa-times"></i> Reject
                    </button>
                  <?php endif; ?>
                  <button class="btn btn-outline btn-sm" onclick="alert('Purpose: <?= addslashes($r['purpose']) ?>')" title="Details">
                      <i class="fa-solid fa-info-circle"></i>
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Approve Modal -->
<div class="modal-overlay" id="approveModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fa-solid fa-check-circle" style="color:var(--success)"></i> Approve USSD Request</h3>
      <button class="modal-close" onclick="closeModal('approveModal')">×</button>
    </div>
    <form method="POST" action="/admin/actions/approve-ussd.php">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="id" id="approveId">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Final USSD Code <span class="required">*</span></label>
          <input type="text" name="final_code" id="approveCode" class="form-control" placeholder="e.g. *384*10#" required>
          <div style="font-size:11px;color:var(--text-secondary);margin-top:4px">Confirm or modify the USSD code before final assignment.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('approveModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Approve & Assign</button>
      </div>
    </form>
  </div>
</div>

<!-- Reject Modal -->
<div class="modal-overlay" id="rejectModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fa-solid fa-times-circle" style="color:var(--danger)"></i> Reject USSD Request</h3>
      <button class="modal-close" onclick="closeModal('rejectModal')">×</button>
    </div>
    <form method="POST" action="/admin/actions/reject-ussd.php">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="id" id="rejectId">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Rejection Reason <span class="required">*</span></label>
          <textarea name="reason" class="form-control" placeholder="Explain why this request is being rejected..." required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')">Cancel</button>
        <button type="submit" class="btn btn-danger">Reject Request</button>
      </div>
    </form>
  </div>
</div>

<?php
$extraScript = <<<'JS'
<script>
function openApprove(id, code) {
    document.getElementById('approveId').value = id;
    document.getElementById('approveCode').value = code;
    openModal('approveModal');
}
function openReject(id) {
    document.getElementById('rejectId').value = id;
    openModal('rejectModal');
}
</script>
JS;
include __DIR__ . '/../includes/layout-footer.php';
?>
