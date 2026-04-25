<?php
$pageTitle = 'My Pricing Plans';
$breadcrumb = [['label'=>'Reseller'],['label'=>'My Pricing']];
require_once __DIR__ . '/layout.php';

$reseller_id = $user['id'];
$plans = DB::query("SELECT * FROM pricing_plans WHERE owner_id = ? ORDER BY units ASC", [$reseller_id]);
?>

<div class="page-header">
  <div><h1>My Pricing Plans</h1><div class="subtitle">Set unique packages for your own clients</div></div>
  <button class="btn btn-primary" onclick="addPlan()"><i class="fa-solid fa-plus"></i> Add Plan</button>
</div>

<?php if (empty($plans)): ?>
  <div class="card" style="padding:40px;text-align:center">
    <div style="font-size:48px;color:var(--text-muted);margin-bottom:16px"><i class="fa-solid fa-tags"></i></div>
    <h3>No Custom Plans Created</h3>
    <p class="text-muted">Create your first pricing plan to allow your clients to buy units from you.</p>
    <button class="btn btn-primary" style="margin-top:16px" onclick="addPlan()">Create First Plan</button>
  </div>
<?php else: ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:18px;margin-bottom:28px">
    <?php foreach ($plans as $p): ?>
      <div class="card" <?= $p['is_popular'] ? 'style="border:1px solid var(--primary);position:relative"' : 'style="position:relative"' ?>>
        <div class="card-body" style="text-align:center;padding:24px 16px">
          <?php if (!$p['is_active']): ?><div style="text-align:right;margin-bottom:4px"><span class="badge badge-danger" style="font-size:10px">Inactive</span></div><?php endif; ?>
          <?php if ($p['is_popular']): ?><div style="position:absolute;top:0;right:0;background:var(--primary);color:#000;font-size:11px;font-weight:700;padding:4px 12px;border-bottom-left-radius:var(--radius-sm)">POPULAR</div><?php endif; ?>
          <div style="font-size:11px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.1em"><?=htmlspecialchars($p['name'])?></div>
          <div style="font-size:40px;font-weight:800;color:var(--primary);margin:8px 0 0"><?=number_format($p['units'])?></div>
          <div style="font-size:12px;color:var(--text-muted);margin-bottom:12px">SMS Units</div>
          <div style="font-size:22px;font-weight:700"><?=htmlspecialchars($p['currency'])?> <?=number_format($p['price'],2)?></div>
          <div class="font-muted" style="font-size:11px;margin:4px 0 16px">
            <?php if ($p['units'] > 0): ?>
              <?=htmlspecialchars($p['currency'])?> <?=number_format($p['price']/$p['units'], 4)?> / unit
            <?php else: ?>
              — / unit
            <?php endif; ?>
          </div>
          <div class="btn-group" style="justify-content:center">
            <button class="btn btn-outline btn-sm" onclick="editPlan(<?=htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8')?>)"><i class="fa-solid fa-pen"></i> Edit</button>
            <form method="POST" action="/reseller/actions/delete-plan.php" style="display:inline">
              <input type="hidden" name="id" value="<?=$p['id']?>">
              <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
              <button class="btn btn-danger btn-sm" onclick="return confirm('Delete this plan?')"><i class="fa-solid fa-trash"></i></button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Plan Modal -->
<div class="modal-overlay" id="planModal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title" id="planModalTitle"><i class="fa-solid fa-tags" style="color:var(--primary)"></i> Add Plan</h3>
      <button class="modal-close" onclick="closeModal('planModal')">×</button>
    </div>
    <form method="POST" action="/reseller/actions/save-plan.php">
      <input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <input type="hidden" name="id" id="planId">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group"><label class="form-label">Plan Name <span class="required">*</span></label><input type="text" name="name" id="planName" class="form-control" placeholder="e.g. Starter Pack" required></div>
          <div class="form-group"><label class="form-label">Total Units <span class="required">*</span></label><input type="number" name="units" id="planUnits" class="form-control" min="1" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Price <span class="required">*</span></label><input type="number" name="price" id="planPrice" class="form-control" min="0.01" step="0.01" required></div>
          <div class="form-group"><label class="form-label">Currency</label><select name="currency" id="planCurrency" class="form-control"><option value="KES">KES</option><option value="USD">USD</option><option value="UGX">UGX</option><option value="TZS">TZS</option></select></div>
        </div>
        <div class="form-group">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px">
            <input type="checkbox" name="is_popular" id="planPopular" value="1" style="width:16px;height:16px"> Mark as Popular
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('planModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Plan</button>
      </div>
    </form>
  </div>
</div>

<?php
$extraScript = <<<'JS'
<script>
function addPlan() {
  document.getElementById('planId').value = '';
  document.getElementById('planName').value = '';
  document.getElementById('planUnits').value = '';
  document.getElementById('planPrice').value = '';
  document.getElementById('planPopular').checked = false;
  document.getElementById('planCurrency').value = 'KES';
  document.getElementById('planModalTitle').innerHTML = '<i class="fa-solid fa-tags" style="color:var(--primary)"></i> Add Plan';
  openModal('planModal');
}

function editPlan(plan) {
  document.getElementById('planId').value = plan.id;
  document.getElementById('planName').value = plan.name;
  document.getElementById('planUnits').value = plan.units;
  document.getElementById('planPrice').value = plan.price;
  document.getElementById('planPopular').checked = plan.is_popular == 1;
  document.getElementById('planCurrency').value = plan.currency || 'KES';
  document.getElementById('planModalTitle').innerHTML = '<i class="fa-solid fa-tags" style="color:var(--primary)"></i> Edit Plan';
  openModal('planModal');
}
</script>
JS;
include __DIR__ . '/../includes/layout-footer.php';
?>
