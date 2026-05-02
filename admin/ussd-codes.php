<?php
$pageTitle = 'USSD Code Inventory';
$breadcrumb = [['label'=>'Admin'],['label'=>'USSD Codes']];
require_once __DIR__ . '/layout.php';

$ussdCodes = DB::query("
    SELECT uc.*, u.name as user_name, u.email as user_email, u.role as user_role 
    FROM ussd_codes uc 
    JOIN users u ON uc.user_id = u.id 
    WHERE uc.status = 'approved'
    ORDER BY uc.approved_at DESC
");
?>

<div class="page-header">
  <div><h1>USSD Codes</h1><div class="subtitle">Overview of all active and assigned USSD services</div></div>
  <button class="btn btn-primary" onclick="openModal('delegateModal')">
      <i class="fa-solid fa-plus-circle"></i> Delegate USSD Code
  </button>
</div>

<div class="card">
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
          <tr>
              <th>USSD Code</th>
              <th>Type</th>
              <th>Assigned To</th>
              <th>Purpose</th>
              <th>Approved On</th>
              <th style="text-align:right">Actions</th>
          </tr>
      </thead>
      <tbody>
        <?php if (empty($ussdCodes)): ?>
          <tr><td colspan="6" class="text-center text-muted" style="padding:30px">No active USSD codes found</td></tr>
        <?php else: ?>
          <?php foreach ($ussdCodes as $c): ?>
            <tr>
              <td>
                  <div style="display:flex; align-items:center; gap:10px">
                      <div style="width:32px; height:32px; background:var(--bg-muted); border-radius:6px; display:flex; align-items:center; justify-content:center; color:var(--primary)">
                          <i class="fa-solid fa-hashtag"></i>
                      </div>
                      <div style="font-weight:700; font-size:15px"><?= htmlspecialchars($c['requested_code']) ?></div>
                  </div>
              </td>
              <td><span class="badge badge-purple"><?= strtoupper($c['type']) ?></span></td>
              <td>
                  <div><?= htmlspecialchars($c['user_name']) ?></div>
                  <div style="font-size:11px; color:var(--text-secondary)"><?= ucfirst($c['user_role']) ?></div>
              </td>
              <td style="max-width:240px">
                  <div class="text-truncate" title="<?= htmlspecialchars($c['purpose']) ?>"><?= htmlspecialchars($c['purpose']) ?></div>
              </td>
              <td style="font-size:12px"><?= $c['approved_at'] ? date('d M Y, H:i', strtotime($c['approved_at'])) : '—' ?></td>
              <td style="text-align:right">
                <div class="btn-group" style="justify-content: flex-end">
                    <a href="/admin/ussd-analytics.php?id=<?= $c['id'] ?>" class="btn btn-outline btn-sm" title="Analytics"><i class="fa-solid fa-chart-line"></i></a>
                    <a href="/admin/ussd-sessions.php?code_id=<?= $c['id'] ?>" class="btn btn-outline btn-sm" title="Sessions"><i class="fa-solid fa-history"></i></a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Delegate Modal -->
<div class="modal-overlay" id="delegateModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fa-solid fa-user-plus" style="color:var(--primary)"></i> Delegate USSD Code</h3>
      <button class="modal-close" onclick="closeModal('delegateModal')">×</button>
    </div>
    <form method="POST" action="/admin/actions/delegate-ussd.php">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div class="modal-body">
        <div class="form-group mb-16">
          <label class="form-label">Assign to User <span class="required">*</span></label>
          <select name="user_id" class="form-control" required>
              <option value="">Select a user...</option>
              <?php 
              $users = DB::query("SELECT id, name, email, role FROM users WHERE role != 'admin' ORDER BY name ASC");
              foreach ($users as $u): ?>
                  <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= ucfirst($u['role']) ?> - <?= htmlspecialchars($u['email']) ?>)</option>
              <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group mb-16">
          <label class="form-label">USSD Type <span class="required">*</span></label>
          <select name="type" class="form-control" required>
              <option value="shared">Shared (Extension)</option>
              <option value="dedicated">Dedicated (Full Code)</option>
          </select>
        </div>

        <div class="form-group mb-16">
          <label class="form-label">USSD Code <span class="required">*</span></label>
          <input type="text" name="ussd_code" class="form-control" placeholder="e.g. *384*10#" required>
        </div>

        <div class="form-group">
          <label class="form-label">Purpose / Internal Note</label>
          <textarea name="purpose" class="form-control" placeholder="e.g. Delegated for internal project X" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('delegateModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Assign Code</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
