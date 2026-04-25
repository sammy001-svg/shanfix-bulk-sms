<?php
$pageTitle = 'Units Management';
$breadcrumb = [['label'=>'Admin'],['label'=>'Units']];
require_once __DIR__ . '/layout.php';

$page   = max(1,(int)($_GET['page']??1));
$perPage= 20;$offset=($page-1)*$perPage;
$search = sanitize($_GET['q']??'');
$where  = 'WHERE 1=1';$params=[];
if ($search){$where.=' AND (u.name LIKE ? OR u.email LIKE ?)';$params[]="%$search%";$params[]="%$search%";}

$totalUsers = DB::queryOne("SELECT SUM(sms_units) as total_units FROM users",[]);

// ── Monthly Stats ──────────────────────────────────────────────
$monthlyData = DB::queryOne("
    SELECT 
        COALESCE(SUM(units), 0) as total_units,
        COALESCE(SUM(amount), 0) as total_revenue
    FROM purchases 
    WHERE status='completed' 
    AND MONTH(created_at) = MONTH(CURRENT_DATE())
    AND YEAR(created_at) = YEAR(CURRENT_DATE())
");

// ── Onfon Live Balance ─────────────────────────────────────────
require_once __DIR__ . '/../includes/gateways/onfon.php';
$adminUser = DB::queryOne("SELECT id, sms_units FROM users WHERE role = 'admin' LIMIT 1");
$localUnits = $adminUser['sms_units'] ?? 0;

$users = DB::query("SELECT u.* FROM users u $where AND u.role!='admin' ORDER BY u.sms_units DESC LIMIT $perPage OFFSET $offset",$params);
$total = DB::queryOne("SELECT COUNT(*) as c FROM users u $where AND u.role!='admin'",$params)['c']??0;
$totalPages = ceil($total/$perPage);
?>
<div class="page-header">
  <div><h1>Units Management</h1><div class="subtitle">Allocate and track SMS units across all users</div></div>
  <button class="btn btn-primary" onclick="openModal('bulkAllocModal')"><i class="fa-solid fa-coins"></i> Bulk Allocate</button>
</div>

<!-- Stats Grid -->
<div class="stats-grid" style="margin-bottom:24px">
  <!-- Onfon Live Balance (Total Units in System) -->
  <div class="stat-card" style="border: 1px solid var(--primary-light); background: rgba(0, 200, 150, 0.03);">
    <div class="stat-icon green" style="position:relative; z-index:2;"><i class="fa-solid fa-cloud-arrow-down"></i></div>
    <div class="stat-info" style="position:relative; z-index:2;">
      <div class="stat-label">Total Units in System (Onfon)</div>
      <div class="stat-value" id="providerBalance"><?= number_format($localUnits, 2) ?></div>
      <div class="stat-trend">
        <form method="POST" action="/admin/actions/sync-balance.php" id="syncForm" style="display:inline">
            <?= csrf_field() ?>
            <button type="button" class="btn btn-link btn-sm" style="padding:0; font-size:11px; text-decoration:none; color:var(--primary); cursor:pointer;" onclick="this.innerHTML='<i class=\'fa-solid fa-spinner fa-spin\'></i> Syncing...'; this.style.opacity='0.6'; this.form.submit();">
                <i class="fa-solid fa-rotate"></i> Sync now
            </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Monthly Purchases -->
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fa-solid fa-shopping-cart"></i></div>
    <div class="stat-info">
      <div class="stat-label">Units Purchased (Month)</div>
      <div class="stat-value"><?= number_format($monthlyData['total_units']) ?></div>
      <div class="stat-trend up"><i class="fa-solid fa-calendar-days"></i> Current month usage</div>
    </div>
  </div>

  <!-- Monthly Revenue -->
  <div class="stat-card">
    <div class="stat-icon orange"><i class="fa-solid fa-money-bill-wave"></i></div>
    <div class="stat-info">
      <div class="stat-label">Total Revenue (Month)</div>
      <div class="stat-value">KES <?= number_format($monthlyData['total_revenue'], 2) ?></div>
      <div class="stat-trend up"><i class="fa-solid fa-arrow-trend-up"></i> Generated this month</div>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">
  <!-- User Balances -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="fa-solid fa-wallet" style="color:var(--primary)"></i> User Balances</h3>
      <form method="GET" style="display:flex;gap:8px">
        <input type="text" name="q" class="form-control" placeholder="Search..." value="<?=htmlspecialchars($search)?>" style="width:160px">
        <button class="btn btn-primary btn-sm"><i class="fa-solid fa-search"></i></button>
      </form>
    </div>
    <div class="table-wrapper" style="max-height:380px;overflow-y:auto">
      <table class="data-table">
        <thead><tr><th>User</th><th>Role</th><th>Balance</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td><strong><?=htmlspecialchars($u['name'])?></strong><div style="font-size:11px;color:var(--text-secondary)"><?=htmlspecialchars($u['email'])?></div></td>
              <td><span class="badge <?=$u['role']==='reseller'?'badge-success':'badge-info'?>"><?=ucfirst($u['role'])?></span></td>
              <td><strong style="color:var(--primary)"><?=number_format($u['sms_units'],2)?></strong></td>
              <td><button class="btn btn-outline btn-sm" onclick="openAllocate(<?=$u['id']?>,'<?=htmlspecialchars($u['name'])?>',<?=$u['sms_units']?>)"><i class="fa-solid fa-plus"></i></button></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Recent Purchases -->
  <div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-receipt" style="color:var(--primary)"></i> Recent Purchases</h3>
    </div>
    <div class="table-wrapper" style="max-height:380px;overflow-y:auto">
      <?php
      $recentPurchases = DB::query("SELECT p.*,u.name,u.email FROM purchases p JOIN users u ON p.user_id=u.id ORDER BY p.created_at DESC LIMIT 30");
      ?>
      <table class="data-table">
        <thead><tr><th>User</th><th>Units</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach ($recentPurchases as $p): ?>
            <?php $pc=['completed'=>'success','pending'=>'warning','failed'=>'danger'][$p['status']]??'muted'; ?>
            <tr>
              <td><?=htmlspecialchars($p['name'])?></td>
              <td><?=number_format($p['units'])?></td>
              <td>KES <?=number_format($p['amount'],2)?></td>
              <td><span class="badge badge-<?=$pc?>"><?=ucfirst($p['status'])?></span></td>
              <td style="font-size:11px"><?=date('d M Y',strtotime($p['created_at']))?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Allocate Modal -->
<div class="modal-overlay" id="allocateModal">
  <div class="modal">
    <div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-coins" style="color:var(--primary)"></i> Allocate Units</h3><button class="modal-close" onclick="closeModal('allocateModal')">×</button></div>
    <form method="POST" action="/admin/actions/allocate-units.php">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <input type="hidden" name="to_user" id="allocUserId">
      <div class="modal-body">
        <div style="background:var(--bg-muted);padding:12px;border-radius:var(--radius-md);margin-bottom:16px;font-size:13px">
          User: <strong id="allocUserName">—</strong> · Balance: <strong id="allocUserUnits" style="color:var(--primary)">—</strong>
        </div>
        <div class="form-group"><label class="form-label">Units to Add <span class="required">*</span></label><input type="number" name="units" class="form-control" min="1" required></div>
        <div class="form-group"><label class="form-label">Method</label><select name="method" class="form-control"><option value="admin_credit">Admin Credit (Free)</option><option value="mpesa">M-Pesa</option><option value="bank">Bank Transfer</option></select></div>
        <div class="form-group"><label class="form-label">Note</label><input type="text" name="note" class="form-control" placeholder="e.g. Manual top-up"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('allocateModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Allocate</button>
      </div>
    </form>
  </div>
</div>

<!-- Bulk Allocate Modal -->
<div class="modal-overlay" id="bulkAllocModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fa-solid fa-coins" style="color:var(--primary)"></i> Bulk Allocate Units</h3>
      <button class="modal-close" onclick="closeModal('bulkAllocModal')">×</button>
    </div>
    <form method="POST" action="/admin/actions/bulk-allocate.php">
      <?= csrf_field() ?>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Target Group <span class="required">*</span></label>
          <select name="target_group" class="form-control" required>
            <option value="all">All Users (Clients & Resellers)</option>
            <option value="resellers">All Resellers</option>
            <option value="clients">All Clients</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Units to Add (Each) <span class="required">*</span></label>
          <input type="number" name="units" class="form-control" min="1" required placeholder="e.g. 100">
          <div style="font-size:11px;color:var(--text-secondary);margin-top:4px">This amount will be added to every user in the selected group.</div>
        </div>
        <div class="form-group">
          <label class="form-label">Note</label>
          <input type="text" name="note" class="form-control" placeholder="e.g. Monthly Bonus">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('bulkAllocModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Bulk Allocate</button>
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
