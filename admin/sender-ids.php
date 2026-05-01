<?php
$pageTitle = 'Sender ID Requests';
$breadcrumb = [['label'=>'Admin'],['label'=>'Sender IDs']];
require_once __DIR__ . '/layout.php';

$filter = sanitize($_GET['status'] ?? '');
$where  = $filter ? "WHERE s.status = '$filter'" : '';
$senderIds = DB::query("SELECT s.*, u.name, u.email, u.role FROM sender_ids s JOIN users u ON s.user_id=u.id $where ORDER BY s.created_at DESC");
?>

<div class="page-header">
  <div><h1>Sender ID Requests</h1><div class="subtitle">Approve or reject sender ID applications</div></div>
  <div style="display:flex;gap:12px;align-items:center;">
    <button class="btn btn-primary" onclick="openModal('addSenderModal')"><i class="fa-solid fa-plus-circle"></i> Add Approved Sender ID</button>
    <div class="tabs">
        <a href="?" class="tab-btn <?= !$filter?'active':'' ?>">All</a>
        <a href="?status=pending"  class="tab-btn <?= $filter==='pending'?'active':'' ?>">⏳ Pending</a>
        <a href="?status=approved" class="tab-btn <?= $filter==='approved'?'active':'' ?>">✅ Approved</a>
        <a href="?status=rejected" class="tab-btn <?= $filter==='rejected'?'active':'' ?>">❌ Rejected</a>
    </div>
  </div>
</div>

<div class="card">
  <div class="table-wrapper">
    <table class="data-table">
      <thead><tr><th>Sender ID</th><th>Requested By</th><th>Role</th><th>Purpose</th><th>Documents</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (empty($senderIds)): ?>
          <tr><td colspan="8" class="text-center text-muted" style="padding:30px">No records found</td></tr>
        <?php else: ?>
          <?php foreach ($senderIds as $s): ?>
            <?php $sc=['approved'=>'success','rejected'=>'danger','pending'=>'warning'][$s['status']]??'muted'; ?>
            <tr>
              <td><strong style="font-size:16px;letter-spacing:0.05em"><?=htmlspecialchars($s['sender_id'])?></strong></td>
              <td><?=htmlspecialchars($s['name'])?><div style="font-size:11px;color:var(--text-secondary)"><?=htmlspecialchars($s['email'])?></div></td>
              <td><span class="badge <?=$s['role']==='reseller'?'badge-success':'badge-info'?>"><?=ucfirst($s['role'])?></span></td>
              <td style="max-width:240px;font-size:13px;color:var(--text-secondary)"><?=htmlspecialchars($s['purpose']??'—')?></td>
              <td>
                <?php if ($s['application_letter']): ?>
                  <div style="display:flex; flex-direction:column; gap:4px">
                    <a href="/<?= $s['application_letter'] ?>" target="_blank" class="btn btn-outline btn-xs" style="font-size:10px; padding:2px 6px">
                      <i class="fa-solid fa-file-pdf"></i> App Letter
                    </a>
                    <a href="/<?= $s['registration_cert'] ?>" target="_blank" class="btn btn-outline btn-xs" style="font-size:10px; padding:2px 6px">
                      <i class="fa-solid fa-certificate"></i> Reg Cert
                    </a>
                  </div>
                <?php else: ?>
                  <span class="text-muted" style="font-size:11px">—</span>
                <?php endif; ?>
              </td>
              <td><span class="badge badge-<?=$sc?>"><?=ucfirst($s['status'])?></span></td>
              <td style="font-size:12px"><?=date('d M Y',strtotime($s['created_at']))?></td>
              <td>
                <div class="btn-group">
                  <?php if ($s['status']==='pending'): ?>
                    <form method="POST" action="/admin/actions/approve-sender-id.php" style="display:inline">
                      <input type="hidden" name="id" value="<?=$s['id']?>">
                      <input type="hidden" name="action" value="approve">
                      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
                      <button class="btn btn-primary btn-sm" title="Approve"><i class="fa-solid fa-check"></i></button>
                    </form>
                    <button class="btn btn-warning btn-sm" onclick="openReject(<?=$s['id']?>)" title="Reject"><i class="fa-solid fa-times"></i></button>
                  <?php endif; ?>
                  <form method="POST" action="/admin/actions/delete-sender-id.php" style="display:inline">
                    <input type="hidden" name="id" value="<?=$s['id']?>">
                    <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
                    <button class="btn btn-danger btn-sm btn-icon" title="Delete Permanent" onclick="return confirm('Delete this Sender ID permanently?')"><i class="fa-solid fa-trash"></i></button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Approved Sender ID Modal -->
<div class="modal-overlay" id="addSenderModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fa-solid fa-plus-circle" style="color:var(--primary)"></i> Add Approved Sender ID</h3>
      <button class="modal-close" onclick="closeModal('addSenderModal')">×</button>
    </div>
    <form method="POST" action="/admin/actions/create-sender-id.php">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Target User <span class="required">*</span></label>
          <select name="user_id" class="form-control" required style="background:var(--bg-muted)">
            <option value="">Select User...</option>
            <?php 
                $usersList = DB::query("SELECT id, name, email, role FROM users WHERE role != 'admin' ORDER BY name ASC");
                foreach($usersList as $u):
            ?>
                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= ucfirst($u['role']) ?> - <?= htmlspecialchars($u['email']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Sender ID <span class="required">*</span></label>
          <input type="text" name="sender_id" class="form-control" placeholder="e.g. SHANFIX" required maxlength="11">
          <div style="font-size:11px;color:var(--text-secondary);margin-top:4px">Must match exactly what is approved on Onfon Media Dashboard.</div>
        </div>
        <div class="form-group">
          <label class="form-label">Purpose / Description</label>
          <input type="text" name="purpose" class="form-control" placeholder="e.g. Assigned from Onfon Pool">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('addSenderModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Assign & Approve</button>
      </div>
    </form>
  </div>
</div>
<div class="modal-overlay" id="rejectModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fa-solid fa-times-circle" style="color:var(--danger)"></i> Reject Sender ID</h3>
      <button class="modal-close" onclick="closeModal('rejectModal')">×</button>
    </div>
    <form method="POST" action="/admin/actions/approve-sender-id.php">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <input type="hidden" name="action" value="reject">
      <input type="hidden" name="id" id="rejectId">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Rejection Reason <span class="required">*</span></label>
          <textarea name="reason" class="form-control" placeholder="Explain why this sender ID is being rejected..." required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')">Cancel</button>
        <button type="submit" class="btn btn-danger"><i class="fa-solid fa-times"></i> Reject</button>
      </div>
    </form>
  </div>
</div>

<?php
$extraScript = <<<'JS'
<script>
function openReject(id) { document.getElementById('rejectId').value = id; openModal('rejectModal'); }
</script>
JS;
include __DIR__ . '/../includes/layout-footer.php';
?>
