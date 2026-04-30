<?php
$pageTitle = 'Users — Resellers & Clients';
$breadcrumb = [['label'=>'Admin'],['label'=>'Users']];
require_once __DIR__ . '/layout.php';

$role   = sanitize($_GET['role'] ?? '');
$search = sanitize($_GET['q'] ?? '');
$page   = max(1,(int)($_GET['page']??1));
$perPage= 20; $offset = ($page-1)*$perPage;

$where = "WHERE u.role != 'admin'";
$params = [];
if ($role) { $where .= ' AND u.role=?'; $params[] = $role; }
if ($search){ $where .= ' AND (u.name LIKE ? OR u.email LIKE ?)'; $params[]="%$search%"; $params[]="%$search%"; }

$total = DB::queryOne("SELECT COUNT(*) as c FROM users u $where",$params)['c']??0;
$users = DB::query("SELECT u.*, p.name as parent_name FROM users u LEFT JOIN users p ON u.parent_id=p.id $where ORDER BY u.created_at DESC LIMIT $perPage OFFSET $offset",$params);
$totalPages = ceil($total/$perPage);
?>

<div class="page-header">
  <div><h1>User Management</h1><div class="subtitle">Manage resellers and clients across the platform</div></div>
  <button class="btn btn-primary" onclick="openModal('createUserModal')"><i class="fa-solid fa-user-plus"></i> Create User</button>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom:18px">
  <div class="card-body" style="padding:14px 18px">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
      <div class="input-group" style="flex:1;min-width:200px">
        <div class="input-group-text input-addon-left"><i class="fa-solid fa-magnifying-glass"></i></div>
        <input type="text" name="q" class="form-control with-left" placeholder="Search name or email..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <select name="role" class="form-control" style="width:150px">
        <option value="">All Roles</option>
        <option value="reseller" <?=$role==='reseller'?'selected':''?>>Resellers</option>
        <option value="client"   <?=$role==='client'?'selected':''?>>Clients</option>
      </select>
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
      <?php if ($role||$search) echo '<a href="/admin/resellers.php" class="btn btn-secondary">Clear</a>'; ?>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fa-solid fa-users" style="color:var(--primary)"></i> Users <span class="badge badge-muted"><?=$total?></span></h3>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Parent</th><th>Units</th><th>Rate</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (empty($users)): ?>
          <tr><td colspan="9" class="text-center text-muted" style="padding:30px">No users found</td></tr>
        <?php else: ?>
          <?php foreach ($users as $u): ?>
            <?php $rc=['active'=>'success','suspended'=>'danger','pending'=>'warning'][$u['status']]??'muted'; ?>
            <tr>
              <td><strong><?= htmlspecialchars($u['name']) ?></strong><?php if ($u['company']) echo '<div style="font-size:11px;color:var(--text-secondary)">'.htmlspecialchars($u['company']).'</div>'; ?></td>
              <td style="color:var(--text-secondary);font-size:13px"><?= htmlspecialchars($u['email']) ?></td>
              <td><span class="badge <?=$u['role']==='reseller'?'badge-success':'badge-info'?>"><?=ucfirst($u['role'])?></span></td>
              <td style="font-size:12px"><?= htmlspecialchars($u['parent_name']??'—') ?></td>
              <td><strong style="color:var(--primary)"><?= number_format($u['sms_units'],2) ?></strong></td>
              <td><span class="badge badge-outline" style="font-weight:600">KES <?=number_format($u['custom_unit_price']??1.00, 2)?></span></td>
              <td><span class="badge badge-<?=$rc?>"><?=ucfirst($u['status'])?></span></td>
              <td style="font-size:12px"><?= date('d M Y',strtotime($u['created_at'])) ?></td>
              <td>
                <div class="btn-group">
                  <button class="btn btn-outline btn-sm btn-icon" title="Allocate Units" onclick="openAllocate(<?=$u['id']?>,'<?=htmlspecialchars($u['name'])?>',<?=$u['sms_units']?>)"><i class="fa-solid fa-coins"></i></button>
                  <a class="btn btn-secondary btn-sm btn-icon" title="Edit" href="/admin/edit-user.php?id=<?=$u['id']?>"><i class="fa-solid fa-pen"></i></a>
                  <form method="POST" action="/admin/actions/toggle-status.php" style="display:inline">
                    <input type="hidden" name="id" value="<?=$u['id']?>">
                    <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
                    <button type="submit" class="btn <?=$u['status']==='active'?'btn-danger':'btn-primary'?> btn-sm btn-icon" title="<?=$u['status']==='active'?'Suspend':'Activate'?>" onclick="return confirm('Are you sure?')">
                      <i class="fa-solid <?=$u['status']==='active'?'fa-ban':'fa-check'?>"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($totalPages>1): ?>
    <div class="card-footer">
      <div class="pagination">
        <?php for($p=1;$p<=$totalPages;$p++): ?><a href="?page=<?=$p?>&role=<?=$role?>&q=<?=urlencode($search)?>" class="page-btn <?=$p===$page?'active':''?>"><?=$p?></a><?php endfor; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- Allocate Units Modal -->
<div class="modal-overlay" id="allocateModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fa-solid fa-coins" style="color:var(--primary)"></i> Allocate Units</h3>
      <button class="modal-close" onclick="closeModal('allocateModal')">×</button>
    </div>
    <form method="POST" action="/admin/actions/allocate-units.php">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <input type="hidden" name="to_user" id="allocUserId">
      <div class="modal-body">
        <div id="allocUserInfo" style="background:var(--bg-muted);padding:12px;border-radius:var(--radius-md);margin-bottom:16px;font-size:13px">
          Allocating to: <strong id="allocUserName">—</strong> · Current balance: <strong id="allocUserUnits">—</strong>
        </div>
        <div class="form-group">
          <label class="form-label">Units to Allocate <span class="required">*</span></label>
          <input type="number" name="units" class="form-control" placeholder="e.g. 500" min="1" required>
        </div>
        <div class="form-group">
          <label class="form-label">Note (optional)</label>
          <input type="text" name="note" class="form-control" placeholder="Reason for allocation">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('allocateModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Allocate</button>
      </div>
    </form>
  </div>
</div>

<!-- Create User Modal -->
<div class="modal-overlay" id="createUserModal">
  <div class="modal" style="max-width:540px">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fa-solid fa-user-plus" style="color:var(--primary)"></i> Create User</h3>
      <button class="modal-close" onclick="closeModal('createUserModal')">×</button>
    </div>
    <form method="POST" action="/admin/actions/create-user.php">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group"><label class="form-label">Full Name <span class="required">*</span></label><input type="text" name="name" class="form-control" placeholder="Jane Doe" required></div>
          <div class="form-group"><label class="form-label">Role <span class="required">*</span></label><select name="role" class="form-control" required><option value="reseller">Reseller</option><option value="client">Client</option></select></div>
        </div>
        <div class="form-group"><label class="form-label">Email <span class="required">*</span></label><input type="email" name="email" class="form-control" required></div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" placeholder="+254..."></div>
          <div class="form-group"><label class="form-label">Company</label><input type="text" name="company" class="form-control"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Password <span class="required">*</span></label><input type="password" name="password" class="form-control" required minlength="8"></div>
          <div class="form-group"><label class="form-label">Initial Units</label><input type="number" name="sms_units" class="form-control" placeholder="0" min="0" value="0"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('createUserModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create User</button>
      </div>
    </form>
  </div>
</div>

<?php
$extraScript = <<<'JS'
<script>
function openAllocate(id, name, units) {
  document.getElementById('allocUserId').value = id;
  document.getElementById('allocUserName').textContent = name;
  document.getElementById('allocUserUnits').textContent = parseFloat(units).toLocaleString();
  openModal('allocateModal');
}
</script>
JS;
include __DIR__ . '/../includes/layout-footer.php';
?>
