<?php
// Client purchases/buy units page
$pageTitle = 'Buy Units';
$breadcrumb = [['label'=>'Client'],['label'=>'Buy Units']];
require_once __DIR__ . '/layout.php';

$uid          = $user['id'];
$parentId     = $user['parent_id'];
$customRate   = ($user['custom_unit_price'] > 0) ? (float)$user['custom_unit_price'] : null;

// Fetch plans based on hierarchy
if ($parentId) {
    // Client belongs to a reseller
    $plans = DB::query("SELECT * FROM pricing_plans WHERE is_active=1 AND owner_id = ? ORDER BY units ASC", [$parentId]);
} else {
    // Direct client (Admin plans)
    $plans = DB::query("SELECT * FROM pricing_plans WHERE is_active=1 AND owner_id IS NULL ORDER BY units ASC");
}

$history = DB::query("SELECT * FROM purchases WHERE user_id=? ORDER BY created_at DESC LIMIT 10",[$uid]);
?>
<div class="page-header">
  <div>
    <h1>Buy SMS Units</h1>
    <div class="subtitle">
      <?php if ($customRate): ?>
        Your account has a fixed rate of <strong>KES <?= number_format($customRate, 2) ?></strong> per unit.
      <?php else: ?>
        Choose a package that fits your needs
      <?php endif; ?>
    </div>
  </div>
  <div style="background:var(--primary-light);border:1px solid var(--primary);padding:8px 18px;border-radius:var(--radius-md)">
    <span style="font-size:12px;color:var(--primary);font-weight:600">Balance:</span>
    <strong style="font-size:16px;color:var(--primary);margin-left:6px"><?=number_format($user['sms_units'],2)?></strong> units
  </div>
</div>

<!-- Plans -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-bottom:28px">
  <?php foreach ($plans as $p): ?>
    <div class="card" style="text-align:center;position:relative;<?=$p['is_popular']??false?'border-color:var(--primary);':''?>">
      <?php if ($p['is_popular']??false): ?><div style="position:absolute;top:-10px;left:50%;transform:translateX(-50%);background:var(--primary);color:#111;font-size:10px;font-weight:700;padding:2px 10px;border-radius:10px">POPULAR</div><?php endif; ?>
      <div class="card-body" style="padding:24px 16px">
        <h3 style="font-size:16px;font-weight:700;margin-bottom:4px"><?=htmlspecialchars($p['name'])?></h3>
        <div style="font-size:30px;font-weight:800;color:var(--primary);margin:12px 0"><?=number_format($p['units'])?><span style="font-size:14px;font-weight:500;color:var(--text-secondary)"> SMS</span></div>
        <div style="font-size:22px;font-weight:700;margin-bottom:16px"><?=$p['currency']?> <?=number_format($p['price'],2)?></div>
        <button class="btn btn-primary btn-full" onclick="openCheckout(<?=$p['id']?>,'<?=htmlspecialchars($p['name'])?>',<?=$p['units']?>,<?=$p['price']?>,'<?=$p['currency']?>')">
          <i class="fa-solid fa-cart-shopping"></i> Buy Now
        </button>
      </div>
    </div>
  <?php endforeach; ?>

  <!-- Custom -->
  <div class="card" style="text-align:center">
    <div class="card-body" style="padding:24px 16px">
      <h3 style="font-size:16px;font-weight:700;margin-bottom:4px">Custom</h3>
      <div style="font-size:24px;font-weight:700;color:var(--primary);margin:12px 0"><i class="fa-solid fa-sliders"></i></div>
      <p class="text-muted" style="font-size:12px;margin-bottom:16px">Specify any amount</p>
      <button class="btn btn-outline btn-full" onclick="openModal('customModal')"><i class="fa-solid fa-pen"></i> Custom Amount</button>
    </div>
  </div>
</div>

<!-- Purchase History -->
<div class="card">
  <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-receipt" style="color:var(--primary)"></i> Purchase History</h3></div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead><tr><th>Package</th><th>Units</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
        <?php if (empty($history)): ?>
          <tr><td colspan="6" class="text-center text-muted" style="padding:24px">No purchases yet</td></tr>
        <?php else: foreach ($history as $p): $sc=['completed'=>'success','pending'=>'warning','failed'=>'danger'][$p['status']]??'muted'; ?>
          <tr>
            <td><strong><?=htmlspecialchars($p['transaction_ref']??'#'.$p['id'])?></strong></td>
            <td><?=number_format($p['units'])?></td>
            <td><?=$p['currency']??'KES'?> <?=number_format($p['amount'],2)?></td>
            <td><?=ucfirst(str_replace('_',' ',$p['payment_method']??'—'))?></td>
            <td><span class="badge badge-<?=$sc?>"><?=ucfirst($p['status'])?></span></td>
            <td style="font-size:12px"><?=date('d M Y',strtotime($p['created_at']))?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Checkout Modal -->
<div class="modal-overlay" id="checkoutModal">
  <div class="modal"><div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-cart-shopping" style="color:var(--primary)"></i> Confirm Purchase</h3><button class="modal-close" onclick="closeModal('checkoutModal')">×</button></div>
    <form method="POST" action="/client/actions/create-purchase.php"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><input type="hidden" name="plan_id" id="chkPlanId">
      <div class="modal-body">
        <div id="chkSummary" style="background:var(--bg-muted);padding:16px;border-radius:var(--radius-md);text-align:center;margin-bottom:18px"></div>
        <input type="hidden" name="payment_method" value="mpesa">
        <div class="form-group">
          <label class="form-label">M-Pesa Phone Number <span class="required">*</span></label>
          <input type="text" name="payment_ref" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="e.g. 254712345678" required>
          <div class="form-hint">Ensure this phone is with you to receive the STK Push prompt.</div>
        </div>
        <div class="alert alert-info" style="font-size:12px"><i class="fa-solid fa-mobile-screen-button"></i> You will receive a popup on your phone to enter your M-Pesa PIN.</div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('checkoutModal')">Cancel</button><button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Send STK Push</button></div>
    </form>
  </div>
</div>
<div class="modal-overlay" id="customModal">
  <div class="modal"><div class="modal-header"><h3 class="modal-title"><i class="fa-solid fa-sliders" style="color:var(--primary)"></i> Custom Amount</h3><button class="modal-close" onclick="closeModal('customModal')">×</button></div>
    <form method="POST" action="/client/actions/create-purchase.php"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>">
      <input type="hidden" name="payment_method" value="mpesa">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Units Needed</label><input type="number" name="custom_units" class="form-control" min="50" placeholder="e.g. 2500" required></div>
        <div class="form-group">
          <label class="form-label">M-Pesa Phone Number <span class="required">*</span></label>
          <input type="text" name="payment_ref" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="254712345678" required>
        </div>
        <div class="alert alert-info" style="font-size:12px"><i class="fa-solid fa-info-circle"></i> An M-Pesa payment prompt will be sent to this number.</div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('customModal')">Cancel</button><button type="submit" class="btn btn-primary">Proceed to Pay</button></div>
    </form>
  </div>
</div>

<?php
$extraScript = <<<'JS'
<script>
function openCheckout(planId, name, units, price, currency){
  document.getElementById('chkPlanId').value = planId;
  document.getElementById('chkSummary').innerHTML =
    `<strong style="font-size:18px">${name}</strong><br>
     <span style="font-size:28px;color:var(--primary);font-weight:800">${units.toLocaleString()}</span> SMS units<br>
     <span style="font-size:20px;font-weight:700">${currency} ${parseFloat(price).toLocaleString(undefined,{minimumFractionDigits:2})}</span>`;
  openModal('checkoutModal');
}
</script>
JS;
include __DIR__ . '/../includes/layout-footer.php';
?>
