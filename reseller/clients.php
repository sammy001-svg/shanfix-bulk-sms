<?php
$pageTitle = 'My Clients';
$breadcrumb = [['label'=>'Reseller'],['label'=>'My Clients']];
require_once __DIR__ . '/layout.php';

$uid    = $user['id'];
$search = sanitize($_GET['q']??'');
$page   = max(1,(int)($_GET['page']??1));
$perPage= 20; $offset=($page-1)*$perPage;

$where  = "WHERE u.role='client' AND u.parent_id=?";
$params = [$uid];
if ($search){ $where.=' AND (u.name LIKE ? OR u.email LIKE ?)'; $params[]="%$search%"; $params[]="%$search%"; }

$total   = DB::queryOne("SELECT COUNT(*) as c FROM users u $where",$params)['c']??0;
$clients = DB::query("SELECT u.* FROM users u $where ORDER BY u.created_at DESC LIMIT $perPage OFFSET $offset",$params);
$totalPages = ceil($total/$perPage);
?>
<div class="page-header">
  <div><h1>My Clients</h1><div class="subtitle">Clients under your reseller account</div></div>
  <button class="btn btn-primary" onclick="openModal('createClientModal')"><i class="fa-solid fa-user-plus"></i> Add Client</button>
</div>

<div class="card" style="margin-bottom:18px">
  <div class="card-body" style="padding:14px 18px">
    <form method="GET" style="display:flex;gap:12px;align-items:center">
      <div class="input-group" style="flex:1">
        <div class="input-group-text input-addon-left"><i class="fa-solid fa-magnifying-glass"></i></div>
        <input type="text" name="q" class="form-control with-left" placeholder="Search name or email..." value="<?=htmlspecialchars($search)?>">
      </div>
      <button class="btn btn-primary"><i class="fa-solid fa-filter"></i> Search</button>
      <?php if ($search) echo '<a href="/reseller/clients.php" class="btn btn-secondary">Clear</a>'; ?>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title"><i class="fa-solid fa-users" style="color:var(--primary)"></i> Clients <span class="badge badge-muted"><?=$total?></span></h3>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>SMS Units</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (empty($clients)): ?>
          <tr><td colspan="7"><div class="empty-state">
            <div class="empty-icon">👤</div>
            <h3>No Clients Yet</h3>
            <p>Add your first client to get started.</p>
            <button class="btn btn-primary" onclick="openModal('createClientModal')"><i class="fa-solid fa-user-plus"></i> Add Client</button>
          </div></td></tr>
        <?php else: ?>
          <?php foreach ($clients as $u): ?>
            <?php $rc=['active'=>'success','suspended'=>'danger','pending'=>'warning'][$u['status']]??'muted'; ?>
            <tr>
              <td><strong><?=htmlspecialchars($u['name'])?></strong></td>
              <td style="font-size:13px;color:var(--text-secondary)"><?=htmlspecialchars($u['email'])?></td>
              <td style="font-size:13px"><?=htmlspecialchars($u['phone']??'—')?></td>
              <td><strong style="color:var(--primary)"><?=number_format($u['sms_units'],2)?></strong></td>
              <td><span class="badge badge-<?=$rc?>"><?=ucfirst($u['status'])?></span></td>
              <td style="font-size:12px"><?=date('d M Y',strtotime($u['created_at']))?></td>
              <td>
                <button class="btn btn-outline btn-sm" onclick="openAllocate(<?=$u['id']?>,'<?=htmlspecialchars($u['name'])?>',<?=$u['sms_units']?>)"><i class="fa-solid fa-coins"></i> Units</button>
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

<!-- Allocate Modal -->
<div class="modal-overlay" id="allocateModal">
  <div class="modal">
    <div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-coins" style="color:var(--primary)"></i> Transfer Units</h3><button class="modal-close" onclick="closeModal('allocateModal')">×</button></div>
    <form method="POST" action="/reseller/actions/allocate-units.php">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <input type="hidden" name="to_user" id="allocUserId">
      <div class="modal-body">
        <div style="background:var(--bg-muted);padding:12px;border-radius:var(--radius-md);margin-bottom:16px;font-size:13px">
          Client: <strong id="allocName">—</strong> · Balance: <strong id="allocUnits" style="color:var(--primary)">—</strong><br>
          Your balance: <strong style="color:var(--primary)"><?=number_format($user['sms_units'],2)?></strong> units
        </div>
        <div class="form-group"><label class="form-label">Units to Transfer <span class="required">*</span></label><input type="number" name="units" class="form-control" min="1" max="<?=floor($user['sms_units'])?>" required></div>
        <div class="form-group"><label class="form-label">Note</label><input type="text" name="note" class="form-control" placeholder="Optional note..."></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('allocateModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-coins"></i> Transfer</button>
      </div>
    </form>
  </div>
</div>

<!-- Create Client Modal -->
<div class="modal-overlay" id="createClientModal">
  <div class="modal" style="max-width:500px">
    <div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-user-plus" style="color:var(--primary)"></i> Add Client</h3><button class="modal-close" onclick="closeModal('createClientModal')">×</button></div>
    <form method="POST" action="/reseller/actions/create-client.php">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group"><label class="form-label">Full Name <span class="required">*</span></label><input type="text" name="name" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" placeholder="+254..."></div>
        </div>
        <div class="form-group"><label class="form-label">Email <span class="required">*</span></label><input type="email" name="email" class="form-control" required></div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Password <span class="required">*</span></label><input type="password" name="password" class="form-control" required minlength="8"></div>
          <div class="form-group"><label class="form-label">Initial Units</label><input type="number" name="sms_units" class="form-control" value="0" min="0" max="<?=floor($user['sms_units'])?>"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('createClientModal')">Cancel</button>
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
  document.getElementById('allocName').textContent = name;
  document.getElementById('allocUnits').textContent = parseFloat(units).toLocaleString();
  openModal('allocateModal');
}
</script>
JS;
include __DIR__ . '/../includes/layout-footer.php';
?>
