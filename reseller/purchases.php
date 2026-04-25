<?php
$pageTitle = 'Buy SMS Units';
$breadcrumb = [['label'=>'Reseller'],['label'=>'Purchases']];
require_once __DIR__ . '/layout.php';

$uid        = $user['id'];
$customRate = ($user['custom_unit_price'] > 0) ? (float)$user['custom_unit_price'] : null;
$history    = DB::query("SELECT p.*,pp.name as plan_name FROM purchases p LEFT JOIN pricing_plans pp ON p.plan_id=pp.id WHERE p.user_id=? ORDER BY p.created_at DESC LIMIT 20", [$uid]);
?>

<div class="page-header">
  <div><h1>Buy SMS Units</h1><div class="subtitle">Top up your account with SMS credits</div></div>
  <div style="background:var(--primary-light);border:1px solid var(--primary);padding:8px 18px;border-radius:var(--radius-md)">
    <span style="font-size:12px;color:var(--primary);font-weight:600">Balance:</span>
    <strong style="font-size:16px;color:var(--primary);margin-left:6px"><?=number_format($user['sms_units'],2)?></strong> units
  </div>
</div>

<div class="card" style="max-width:800px;margin-bottom:28px">
  <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-coins" style="color:var(--primary)"></i> Purchase SMS Units</h3></div>
  <div class="card-body" style="padding:32px">
    <?php if ($customRate): ?>
      <div style="text-align:center;margin-bottom:24px">
        <div style="font-size:14px;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.1em">My Special Rate</div>
        <div style="font-size:48px;font-weight:800;color:var(--primary);line-height:1">KES <?= number_format($customRate, 2) ?></div>
        <div style="font-size:14px;color:var(--text-muted);margin-top:4px">Per SMS Unit</div>
      </div>

      <form method="POST" action="/reseller/actions/purchase.php" style="max-width:400px;margin:0 auto">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="form-group">
          <label class="form-label">How many units do you need?</label>
          <input type="number" name="custom_units" id="buyUnits" class="form-control" placeholder="e.g. 5000" min="100" required oninput="calcTotal(this.value, <?= $customRate ?>)">
          <div id="totalCostDisplay" style="margin-top:12px;font-weight:700;font-size:18px;text-align:center;color:var(--text-primary)">Total Cost: KES 0.00</div>
        </div>
        <input type="hidden" name="payment_method" value="mpesa">
        <div class="form-group">
          <label class="form-label">M-Pesa Phone Number <span class="required">*</span></label>
          <input type="text" name="payment_ref" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="e.g. 254712345678" required>
          <div class="form-hint">Enter the number that will receive the payment prompt.</div>
        </div>
        <div class="alert alert-info" style="font-size:12px"><i class="fa-solid fa-mobile-screen-button"></i> An M-Pesa STK Push prompt will be sent to your phone.</div>
        <button type="submit" class="btn btn-primary btn-full" style="height:48px;font-size:16px"><i class="fa-solid fa-paper-plane"></i> Initiate Payment</button>
      </form>
    <?php else: ?>
      <div style="text-align:center;padding:20px">
        <div style="font-size:48px;color:var(--warning);margin-bottom:16px"><i class="fa-solid fa-exclamation-triangle"></i></div>
        <h3>No Dedicated Rate Set</h3>
        <p class="text-muted">You do not have a dedicated unit rate assigned to your account. Please contact the administrator to set your wholesale price before you can purchase units.</p>
        <a href="mailto:admin@shanfix.com" class="btn btn-outline" style="margin-top:12px">Contact Admin</a>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-receipt" style="color:var(--primary)"></i> Purchase History</h3></div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead><tr><th>ID</th><th>Units</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
        <?php if (empty($history)): ?>
          <tr><td colspan="6" class="text-center text-muted" style="padding:24px">No purchases yet</td></tr>
        <?php else: foreach ($history as $h): $hc=['completed'=>'success','pending'=>'warning','failed'=>'danger'][$h['status']]??'muted'; ?>
          <tr>
            <td>#<?= $h['id'] ?></td>
            <td><strong><?= number_format($h['units']) ?></strong></td>
            <td>KES <?= number_format($h['amount'],2) ?></td>
            <td><?= ucfirst($h['payment_method']) ?></td>
            <td><span class="badge badge-<?=$hc?>"><?=ucfirst($h['status'])?></span></td>
            <td style="font-size:12px"><?=date('d M Y',strtotime($h['created_at']))?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
$extraScript = <<<'JS'
<script>
function calcTotal(units, rate) {
  const total = parseFloat(units || 0) * rate;
  document.getElementById('totalCostDisplay').textContent = 'Total Cost: KES ' + total.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
}
</script>
JS;
include __DIR__ . '/../includes/layout-footer.php';
?>
