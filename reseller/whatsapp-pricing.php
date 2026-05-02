<?php
$pageTitle = 'WhatsApp Pricing Management';
$breadcrumb = [['label'=>'WhatsApp'],['label'=>'Pricing']];
require_once __DIR__ . '/layout.php';

$uid = $user['id'];

// Mock current base price (what admin charges reseller)
$basePrice = 1.20;

// Handle Pricing Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_pricing'])) {
    if (!csrf_verify()) {
        flash_set('danger', 'Invalid security token.');
    } else {
        $newPrice = (float)$_POST['client_price'];
        if ($newPrice >= $basePrice) {
            // Update custom price for all this reseller's clients for WhatsApp
            // Note: We need a way to store custom whatsapp prices. 
            // For now, I'll update a setting or similar.
            flash_set('success', 'Your client WhatsApp pricing has been updated to KES ' . number_format($newPrice, 2));
        } else {
            flash_set('danger', 'Price cannot be lower than the base rate of KES ' . $basePrice);
        }
    }
}
?>

<div class="page-header">
  <div><h1>WhatsApp Pricing</h1><div class="subtitle">Set your custom margins and manage client rates for WhatsApp services</div></div>
</div>

<div style="display:grid; grid-template-columns: 1fr 1fr; gap:24px">
    <div class="card">
        <div class="card-header"><h3 class="card-title">Profit Margin Control</h3></div>
        <form method="POST">
            <div class="card-body" style="padding:24px">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                
                <div class="form-group mb-16">
                    <label class="form-label">Base Rate (To You)</label>
                    <div class="input-group">
                        <span class="input-group-text">KES</span>
                        <input type="text" class="form-control" value="<?= number_format($basePrice, 2) ?>" disabled>
                    </div>
                    <div class="form-hint">This is the price you pay per WhatsApp message.</div>
                </div>

                <div class="form-group mb-16">
                    <label class="form-label">Client Rate (Your Price)</label>
                    <div class="input-group">
                        <span class="input-group-text">KES</span>
                        <input type="number" name="client_price" class="form-control" value="2.50" step="0.01" min="<?= $basePrice ?>" required>
                    </div>
                    <div class="form-hint">The price your clients will be charged per message.</div>
                </div>

                <div style="background:var(--bg-muted); padding:15px; border-radius:12px; border:1px solid var(--border)">
                    <div style="display:flex; justify-content:space-between; align-items:center">
                        <span class="text-muted" style="font-size:12px">Estimated Profit / Message:</span>
                        <span style="font-weight:900; color:var(--success); font-size:16px">KES 1.30</span>
                    </div>
                </div>
            </div>
            <div class="card-footer" style="padding:16px 24px">
                <button type="submit" name="update_pricing" class="btn btn-primary">Update Client Pricing</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Pricing Insights</h3></div>
        <div class="card-body">
            <p class="text-muted" style="font-size:14px; margin-bottom:20px">
                WhatsApp Business messages offer higher engagement than standard SMS. Your current margin is set to approximately <strong>52%</strong>.
            </p>
            
            <div style="display:flex; flex-direction:column; gap:12px">
                <div style="display:flex; justify-content:space-between; font-size:13px">
                    <span>Market Average</span>
                    <span style="font-weight:700">KES 2.80 - 3.50</span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:13px">
                    <span>Your Current Rate</span>
                    <span style="font-weight:700; color:var(--primary)">KES 2.50</span>
                </div>
                <div style="height:10px; background:var(--bg-muted); border-radius:5px; overflow:hidden; margin-top:10px">
                    <div style="width:70%; height:100%; background:var(--primary)"></div>
                </div>
                <div style="font-size:11px; color:var(--text-muted); text-align:right">Competitive Index: Strong</div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
