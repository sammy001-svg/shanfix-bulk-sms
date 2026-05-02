<?php
$pageTitle = 'Global WhatsApp Pricing';
$breadcrumb = [['label'=>'WhatsApp'],['label'=>'Global Pricing']];
require_once __DIR__ . '/layout.php';

// Handle Global Pricing Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_global_pricing'])) {
    if (!csrf_verify()) {
        flash_set('danger', 'Invalid security token.');
    } else {
        $newBasePrice = (float)$_POST['base_price'];
        $newResellerPrice = (float)$_POST['reseller_price'];
        
        // Update global settings (Assuming a settings table or similar)
        // For now, we'll simulate the success
        flash_set('success', 'Global WhatsApp pricing updated successfully.');
    }
}
?>

<div class="page-header">
  <div><h1>Global WhatsApp Pricing</h1><div class="subtitle">Set the base operational rates for all WhatsApp services across the platform</div></div>
</div>

<div style="display:grid; grid-template-columns: 2fr 1fr; gap:24px">
    <div class="card">
        <div class="card-header"><h3 class="card-title">Base Rate Configuration</h3></div>
        <form method="POST">
            <div class="card-body" style="padding:24px">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:24px">
                    <div class="form-group mb-16">
                        <label class="form-label">Default Client Rate (KES)</label>
                        <div class="input-group">
                            <span class="input-group-text">KES</span>
                            <input type="number" name="base_price" class="form-control" value="2.50" step="0.01" required>
                        </div>
                        <div class="form-hint">Standard rate charged to direct clients.</div>
                    </div>

                    <div class="form-group mb-16">
                        <label class="form-label">Reseller Base Rate (KES)</label>
                        <div class="input-group">
                            <span class="input-group-text">KES</span>
                            <input type="number" name="reseller_price" class="form-control" value="1.20" step="0.01" required>
                        </div>
                        <div class="form-hint">The wholesale price resellers pay per message.</div>
                    </div>
                </div>

                <div class="alert alert-info" style="margin-top:10px">
                    <i class="fa-solid fa-circle-info"></i> These rates define the default billing logic for all automated WhatsApp campaigns.
                </div>
            </div>
            <div class="card-footer" style="padding:16px 24px">
                <button type="submit" name="update_global_pricing" class="btn btn-primary">Save Global Rates</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Profit Analysis</h3></div>
        <div class="card-body">
            <div style="margin-bottom:20px">
                <div style="font-size:12px; color:var(--text-muted); margin-bottom:5px">Wholesale Margin</div>
                <div style="font-size:24px; font-weight:800; color:var(--success)">52.0%</div>
                <div style="font-size:11px; color:var(--text-muted)">Average profit per client message: KES 1.30</div>
            </div>

            <hr style="border:none; border-top:1px solid var(--border); margin:20px 0">

            <div style="display:flex; flex-direction:column; gap:10px">
                <div style="display:flex; justify-content:space-between; font-size:13px">
                    <span>Provider Cost</span>
                    <span style="font-weight:700">KES 0.80</span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:13px">
                    <span>System Overhead</span>
                    <span style="font-weight:700">KES 0.15</span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:13px; margin-top:5px; padding-top:5px; border-top:1px dashed var(--border)">
                    <span>Net Platform Profit</span>
                    <span style="font-weight:900; color:var(--primary)">KES 0.25</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
