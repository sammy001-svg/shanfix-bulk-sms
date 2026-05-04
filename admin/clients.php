<?php
$pageTitle = 'Clients';
$breadcrumb = [['label'=>'Admin'],['label'=>'Clients']];
require_once __DIR__ . '/layout.php';

$search = sanitize($_GET['q'] ?? '');
$page   = max(1,(int)($_GET['page']??1));
$perPage= 20; $offset = ($page-1)*$perPage;

$where = "WHERE u.role='client'";
$params = [];
if ($search){ $where .= ' AND (u.name LIKE ? OR u.email LIKE ?)'; $params[]="%$search%"; $params[]="%$search%"; }

$total  = DB::queryOne("SELECT COUNT(*) as c FROM users u $where",$params)['c']??0;
$clients= DB::query("SELECT u.*, r.name as reseller_name FROM users u LEFT JOIN users r ON u.parent_id=r.id $where ORDER BY u.created_at DESC LIMIT $perPage OFFSET $offset",$params);
$totalPages = ceil($total/$perPage);
?>
<div class="page-header">
  <div><h1>Clients</h1><div class="subtitle">All client accounts on the platform</div></div>
  <button class="btn btn-primary" onclick="openModal('createUserModal')"><i class="fa-solid fa-user-plus"></i> Create Client</button>
</div>

<div class="card" style="margin-bottom:18px">
  <div class="card-body" style="padding:14px 18px">
    <form method="GET" style="display:flex;gap:12px;align-items:center">
      <div class="input-group" style="flex:1">
        <div class="input-group-text input-addon-left"><i class="fa-solid fa-magnifying-glass"></i></div>
        <input type="text" name="q" class="form-control with-left" placeholder="Search name or email..." value="<?=htmlspecialchars($search)?>">
      </div>
      <button class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
      <?php if ($search) echo '<a href="/admin/clients.php" class="btn btn-secondary">Clear</a>'; ?>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fa-solid fa-user" style="color:var(--primary)"></i> All Clients <span class="badge badge-muted"><?=$total?></span></h3>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Reseller</th><th>SMS Units</th><th>Rate</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (empty($clients)): ?>
          <tr><td colspan="10" class="text-center text-muted" style="padding:30px">No clients found</td></tr>
        <?php else: ?>
          <?php foreach ($clients as $u): ?>
            <?php $rc=['active'=>'success','suspended'=>'danger','pending'=>'warning'][$u['status']]??'muted'; ?>
            <tr>
              <td><strong><?=htmlspecialchars($u['name'])?></strong><?php if ($u['company']) echo '<div style="font-size:11px;color:var(--text-secondary)">'.htmlspecialchars($u['company']).'</div>';?></td>
              <td style="font-size:13px;color:var(--text-secondary)"><?=htmlspecialchars($u['email'])?></td>
              <td style="font-size:13px"><?=htmlspecialchars($u['phone']??'—')?></td>
              <td><span class="badge badge-success"><?=htmlspecialchars($u['reseller_name']??'Direct')?></span></td>
              <td><strong style="color:var(--primary)"><?=number_format($u['sms_units'],2)?></strong></td>
              <td><span class="badge badge-outline" style="font-weight:600">KES <?=number_format($u['custom_unit_price']??1.00, 2)?></span></td>
              <td><span class="badge badge-<?=$rc?>"><?=ucfirst($u['status'])?></span></td>
              <td style="font-size:12px"><?=date('d M Y',strtotime($u['created_at']))?></td>
              <td>
                <div class="btn-group">
                  <button class="btn btn-outline btn-sm btn-icon" title="Allocate Units" onclick="openAllocate(<?=$u['id']?>,'<?=htmlspecialchars($u['name'])?>',<?=$u['sms_units']?>)"><i class="fa-solid fa-coins"></i></button>
                  <a class="btn btn-secondary btn-sm btn-icon" title="Edit User" href="/admin/edit-user.php?id=<?=$u['id']?>"><i class="fa-solid fa-pen"></i></a>
                  <form method="POST" action="/admin/actions/toggle-status.php" style="display:inline">
                    <input type="hidden" name="id" value="<?=$u['id']?>">
                    <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
                    <button class="btn <?=$u['status']==='active'?'btn-danger':'btn-primary'?> btn-sm btn-icon" onclick="return confirm('Are you sure you want to change this user status?')" title="<?=$u['status']==='active'?'Suspend':'Activate'?>">
                      <i class="fa-solid <?=$u['status']==='active'?'fa-ban':'fa-check'?>"></i>
                    </button>
                  </form>
                  <form method="POST" action="/admin/actions/delete-user.php" style="display:inline" onsubmit="return confirm('WARNING: This will permanently delete this client and ALL their data (messages, contacts, etc). This action cannot be undone. Are you sure?')">
                    <input type="hidden" name="id" value="<?=$u['id']?>">
                    <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
                    <button type="submit" class="btn btn-outline btn-sm btn-icon" style="color:var(--danger); border-color:var(--danger)" title="Delete User">
                      <i class="fa-solid fa-trash-can"></i>
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
    <div class="card-footer"><div class="pagination">
      <?php for($p=1;$p<=$totalPages;$p++): ?><a href="?page=<?=$p?>&q=<?=urlencode($search)?>" class="page-btn <?=$p===$page?'active':''?>"><?=$p?></a><?php endfor; ?>
    </div></div>
  <?php endif; ?>
</div>

<!-- Allocate Units Modal -->
<div class="modal-overlay" id="allocateModal">
  <div class="modal">
    <div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-coins" style="color:var(--primary)"></i> Allocate Units</h3><button class="modal-close" onclick="closeModal('allocateModal')">×</button></div>
    <form method="POST" action="/admin/actions/allocate-units.php">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <input type="hidden" name="to_user" id="allocUserId">
      <div class="modal-body">
        <div id="allocUserInfo" style="background:var(--bg-muted);padding:12px;border-radius:var(--radius-md);margin-bottom:16px;font-size:13px">
          Client: <strong id="allocUserName">—</strong> · Balance: <strong id="allocUserUnits">—</strong>
        </div>
        <div class="form-group"><label class="form-label">Units <span class="required">*</span></label><input type="number" name="units" class="form-control" min="1" required></div>
        <div class="form-group"><label class="form-label">Note</label><input type="text" name="note" class="form-control" placeholder="Reason..."></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('allocateModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Allocate</button>
      </div>
    </form>
  </div>
</div>

<!-- Create Client Modal -->
<div class="modal-overlay" id="createUserModal">
  <div class="modal" style="max-width:540px">
    <div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-user-plus" style="color:var(--primary)"></i> Create Client</h3><button class="modal-close" onclick="closeModal('createUserModal')">×</button></div>
    <form method="POST" action="/admin/actions/create-user.php">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <input type="hidden" name="role" value="client">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group"><label class="form-label">Full Name <span class="required">*</span></label><input type="text" name="name" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" placeholder="+254..."></div>
        </div>
        <div class="form-group"><label class="form-label">Email <span class="required">*</span></label><input type="email" name="email" class="form-control" required></div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Password <span class="required">*</span></label><input type="password" name="password" class="form-control" required minlength="8"></div>
          <div class="form-group"><label class="form-label">Initial Units</label><input type="number" name="sms_units" class="form-control" value="0" min="0"></div>
        </div>
        <div class="form-group">
          <label class="form-label">Assign to Reseller</label>
          <select name="parent_id" class="form-control">
            <option value="">Direct (no reseller)</option>
            <?php foreach (DB::query("SELECT id,name FROM users WHERE role='reseller' AND status='active' ORDER BY name") as $r): ?>
              <option value="<?=$r['id']?>"><?=htmlspecialchars($r['name'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('createUserModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Client</button>
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
