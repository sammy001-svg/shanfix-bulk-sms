<?php
// Client purchases/buy units page
$pageTitle = 'Buy Units';
$breadcrumb = [['label'=>'Client'],['label'=>'Buy Units']];
require_once __DIR__ . '/layout.php';

$uid          = $user['id'];
$parentId     = $user['parent_id'];
$customRate   = ($user['custom_unit_price'] > 0) ? (float)$user['custom_unit_price'] : null;

// Fetch plans and reseller settings if white-labeled
$resellerSettings = null;
if (Branding::isWhiteLabeled() && $parentId) {
    $resellerSettings = DB::queryOne("SELECT unit_price, payment_instructions FROM reseller_settings WHERE reseller_id = ?", [$parentId]);
    $plans = DB::query("SELECT * FROM pricing_plans WHERE is_active=1 AND owner_id = ? ORDER BY units ASC", [$parentId]);
} else {
    // Direct client (Admin plans)
    $plans = DB::query("SELECT * FROM pricing_plans WHERE is_active=1 AND owner_id IS NULL ORDER BY units ASC");
}

$history = DB::query("SELECT * FROM purchases WHERE user_id=? ORDER BY created_at DESC LIMIT 10",[$uid]);

// Set unit rate
if ($customRate) {
    $unitRate = $customRate;
} elseif ($resellerSettings && isset($resellerSettings['unit_price']) && (float)$resellerSettings['unit_price'] > 0) {
    $unitRate = (float)$resellerSettings['unit_price'];
} else {
    $unitRate = 1.00; // Default platform rate
}
?>
<div class="page-header">
  <div>
    <h1>Buy SMS Units</h1>
    <div class="subtitle">
      <?php if ($customRate): ?>
        Your account has a fixed rate of <strong>KES <?= number_format($customRate, 2) ?></strong> per unit.
      <?php else: ?>
        Choose a package or enter custom units
      <?php endif; ?>
    </div>
  </div>
  <div style="background:var(--primary-light);border:1px solid var(--primary);padding:8px 18px;border-radius:var(--radius-md)">
    <span style="font-size:12px;color:var(--primary);font-weight:600">Balance:</span>
    <strong style="font-size:16px;color:var(--primary);margin-left:6px" class="current-balance-value" data-balance-type="sms"><?=number_format($user['sms_units'],2)?></strong> units
  </div>
</div>

<!-- Packages Section -->
<?php if (!empty($plans)): ?>
  <div style="margin-bottom: 32px;">
    <h3 style="margin-bottom: 20px; font-weight: 700;"><i class="fa-solid fa-layer-group" style="color:var(--primary)"></i> Select a Package</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px;">
      <?php foreach ($plans as $p): ?>
        <div class="card package-card" onclick="selectPlan(<?= htmlspecialchars(json_encode($p)) ?>)" style="cursor: pointer; transition: all 0.2s ease; border: 1px solid var(--border); position: relative;">
          <div class="card-body" style="text-align: center; padding: 24px 15px;">
            <?php if ($p['is_popular']): ?>
              <div style="position: absolute; top: -1px; right: -1px; background: var(--primary); color: #000; font-size: 10px; font-weight: 800; padding: 4px 10px; border-top-right-radius: var(--radius-md); border-bottom-left-radius: var(--radius-sm);">BEST VALUE</div>
            <?php endif; ?>
            <div style="font-size: 11px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;"><?= htmlspecialchars($p['name']) ?></div>
            <div style="font-size: 32px; font-weight: 800; color: var(--primary); margin-bottom: 2px;"><?= number_format($p['units']) ?></div>
            <div style="font-size: 11px; color: var(--text-muted); margin-bottom: 15px;">SMS Units</div>
            <div style="font-size: 20px; font-weight: 700; margin-bottom: 15px;"><?= htmlspecialchars($p['currency'] ?? 'KES') ?> <?= number_format($p['price'], 2) ?></div>
            <button class="btn btn-outline btn-sm btn-full" style="pointer-events: none;">Select Package</button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  
  <style>
    .package-card:hover { border-color: var(--primary) !important; transform: translateY(-3px); box-shadow: var(--shadow-md); }
    .package-card:hover button { background: var(--primary); color: #000; border-color: var(--primary); }
  </style>
<?php endif; ?>

<!-- Dynamic Buy Section -->
<div class="card" style="max-width: 600px; margin: 0 auto 28px;">
  <div class="card-body" style="padding: 30px 20px; text-align: center;">
    <div style="font-size: 48px; color: var(--primary); margin-bottom: 10px;"><i class="fa-solid fa-coins"></i></div>
    <h2 style="margin-bottom: 8px;">Buy Custom Units</h2>
    <p class="text-muted" style="margin-bottom: 24px;">Enter the exact number of units you need</p>

    <div style="background: var(--bg-muted); padding: 20px; border-radius: var(--radius-lg); margin-bottom: 24px;">
      <div class="form-group" style="margin-bottom: 15px;">
        <label class="form-label" style="font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em;">Number of Units</label>
        <input type="number" id="calcUnits" class="form-control" style="text-align: center; font-size: 24px; font-weight: 700; height: 60px;" placeholder="e.g. 1000" min="50" oninput="calculateTotal(this.value)">
      </div>
      
      <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 15px; border-top: 1px solid var(--border);">
        <div style="text-align: left;">
          <div style="font-size: 11px; color: var(--text-secondary); text-transform: uppercase;">Your Rate</div>
          <div style="font-size: 16px; font-weight: 600;">KES <?= number_format($unitRate, 2) ?> <span style="font-size: 12px; font-weight: 400; color: var(--text-muted);">/ unit</span></div>
        </div>
        <div style="text-align: right;">
          <div style="font-size: 11px; color: var(--text-secondary); text-transform: uppercase;">Total Cost</div>
          <div style="font-size: 24px; font-weight: 800; color: var(--primary);" id="totalCostDisplay">KES 0.00</div>
        </div>
      </div>
    </div>

    <button class="btn btn-primary btn-lg btn-full" style="height: 55px; font-size: 16px; font-weight: 700;" onclick="startCheckout()">
      <i class="fa-solid fa-cart-shopping"></i> Proceed to Checkout
    </button>
  </div>
</div>

<input type="hidden" id="userRate" value="<?= $unitRate ?>">

<!-- Purchase History -->
<div class="card" style="margin-bottom: 28px;">
  <div class="card-header">
    <h3 class="card-title"><i class="fa-solid fa-receipt" style="color:var(--primary)"></i> Recent Purchases</h3>
    <a href="/client/purchase-history.php" class="btn btn-outline btn-sm">Full History →</a>
  </div>
  <div class="table-wrapper">
    <table class="data-table">
      <thead><tr><th>Reference</th><th>Units</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr></thead>
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
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title"><i class="fa-solid fa-cart-shopping" style="color:var(--primary)"></i> Confirm Payment</h3>
      <button class="modal-close" onclick="closeModal('checkoutModal')">×</button>
    </div>
    <form method="POST" action="/client/actions/create-purchase.php">
      <?= csrf_field() ?>
      <input type="hidden" name="plan_id" id="chkPlanId">
      <input type="hidden" name="custom_units" id="chkUnits">
      <div class="modal-body">
        <div id="chkSummary" style="background:var(--bg-muted);padding:16px;border-radius:var(--radius-md);text-align:center;margin-bottom:18px">
            <div style="font-size:13px;color:var(--text-secondary)">Purchase Summary</div>
            <div style="font-size:28px;font-weight:800;color:var(--primary);margin:5px 0" id="summaryUnits">0 SMS</div>
            <div style="font-size:18px;font-weight:700" id="summaryTotal">KES 0.00</div>
        </div>
        
        <?php if ($parentId): ?>
            <div class="alert alert-warning" style="margin-bottom:15px">
                <div style="font-weight:700;margin-bottom:5px"><i class="fa-solid fa-circle-info"></i> Reseller Payment:</div>
                <div style="font-size:14px">
                  <?= ($resellerSettings && !empty($resellerSettings['payment_instructions'])) 
                      ? nl2br(htmlspecialchars($resellerSettings['payment_instructions'])) 
                      : "Please contact your reseller to make payment and receive your units. Once paid, enter the transaction code below." ?>
                </div>
            </div>
            <div class="form-group">
              <label class="form-label">Transaction Reference Code <span class="required">*</span></label>
              <input type="text" name="payment_ref" class="form-control" placeholder="e.g. M-Pesa Code" required>
              <div class="form-hint">Enter the reference code after making payment. Your reseller will verify and approve.</div>
            </div>
        <?php else: ?>
            <div class="form-group">
              <label class="form-label">M-Pesa Phone Number <span class="required">*</span></label>
              <input type="text" name="payment_ref" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="e.g. 254712345678" required>
              <div class="form-hint">Enter the number to receive the STK prompt.</div>
            </div>
            
            <div class="alert alert-info" style="font-size:12px;margin-bottom:0">
                <i class="fa-solid fa-mobile-screen-button"></i> An M-Pesa STK push will be sent to your phone.
            </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('checkoutModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> <?= ($parentId) ? 'Submit for Approval' : 'Pay Now' ?></button>
      </div>
    </form>
  </div>
</div>

<?php
$extraScript = <<<'JS'
<script>
function selectPlan(plan) {
    document.getElementById('chkPlanId').value = plan.id;
    document.getElementById('chkUnits').value = plan.units;
    document.getElementById('summaryUnits').textContent = parseInt(plan.units).toLocaleString() + ' SMS (' + plan.name + ')';
    document.getElementById('summaryTotal').textContent = (plan.currency || 'KES') + ' ' + parseFloat(plan.price).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    openModal('checkoutModal');
}

function calculateTotal(units) {
    if (document.getElementById('chkPlanId')) document.getElementById('chkPlanId').value = ''; 
    const rate = window.ShanfixConfig.smsRate || 1.00;
    const total = units * rate;
    document.getElementById('totalCostDisplay').textContent = 'KES ' + total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function startCheckout() {
    const units = document.getElementById('calcUnits').value;
    if (!units || units < 50) {
        alert('Please enter at least 50 units.');
        return;
    }
    
    if (document.getElementById('chkPlanId')) document.getElementById('chkPlanId').value = ''; 
    const rate = window.ShanfixConfig.smsRate || 1.00;
    const total = units * rate;
    
    document.getElementById('chkUnits').value = units;
    document.getElementById('summaryUnits').textContent = parseInt(units).toLocaleString() + ' SMS';
    document.getElementById('summaryTotal').textContent = 'KES ' + total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    openModal('checkoutModal');
}

// AJAX Form Handler
document.querySelector('#checkoutModal form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = this;
    const btn = form.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;

    const formData = new FormData(form);
    formData.append('ajax', '1');

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.success) {
            closeModal('checkoutModal');
            if (data.manual) {
                Swal.fire('Submitted', 'Payment reference submitted! Your reseller will verify and approve your units shortly.', 'success');
            } else {
                // Trigger global STK handler
                window.ShanfixSTK.checkStatus(data.id, 'sms');
            }
        } else {
            Swal.fire('Transaction Failed', data.error, 'error');
        }
    } catch (error) {
        console.error('Purchase error:', error);
        Swal.fire('Error', 'An unexpected error occurred. Please try again.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
});
</script>
JS;
?>
<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
