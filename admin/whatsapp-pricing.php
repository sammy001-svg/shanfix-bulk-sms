<?php
$pageTitle = 'Global WhatsApp Pricing';
$breadcrumb = [['label'=>'WhatsApp'],['label'=>'Global Pricing']];
require_once __DIR__ . '/layout.php';

// Save rates to system_settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_global_pricing'])) {
    if (!csrf_verify()) {
        flash_set('danger', 'Invalid security token.');
    } else {
        $newBasePrice     = max(0.01, (float)$_POST['base_price']);
        $newResellerPrice = max(0.01, (float)$_POST['reseller_price']);

        if ($newResellerPrice >= $newBasePrice) {
            flash_set('danger', 'Reseller rate must be lower than the client rate.');
        } else {
            DB::execute(
                "INSERT INTO system_settings (`key`, `value`) VALUES ('whatsapp_base_price', ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
                [$newBasePrice]
            );
            DB::execute(
                "INSERT INTO system_settings (`key`, `value`) VALUES ('whatsapp_reseller_price', ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
                [$newResellerPrice]
            );
            // Bust the get_setting() static cache so the new values are visible immediately
            // (the cache lives only for the current request, so a redirect reloads clean)
            flash_set('success', 'Global WhatsApp pricing updated successfully.');
            redirect('/admin/whatsapp-pricing.php');
        }
    }
}

// Load current rates from DB (with sensible defaults)
$basePrice     = (float)(get_setting('whatsapp_base_price',     2.50));
$resellerPrice = (float)(get_setting('whatsapp_reseller_price', 1.20));

// Dynamic profit analysis
$clientMargin   = $basePrice > 0 ? round(($basePrice - $resellerPrice) / $basePrice * 100, 1) : 0;
$profitPerMsg   = round($basePrice - $resellerPrice, 2);
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
                <input type="hidden" name="update_global_pricing" value="1">

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:24px">
                    <div class="form-group mb-16">
                        <label class="form-label">Default Client Rate (KES)</label>
                        <div class="input-group">
                            <span class="input-group-text">KES</span>
                            <input type="number" name="base_price" class="form-control"
                                   value="<?= number_format($basePrice, 2, '.', '') ?>"
                                   step="0.01" min="0.01" required>
                        </div>
                        <div class="form-hint">Standard rate charged to direct clients per message.</div>
                    </div>

                    <div class="form-group mb-16">
                        <label class="form-label">Reseller Base Rate (KES)</label>
                        <div class="input-group">
                            <span class="input-group-text">KES</span>
                            <input type="number" name="reseller_price" class="form-control"
                                   value="<?= number_format($resellerPrice, 2, '.', '') ?>"
                                   step="0.01" min="0.01" required>
                        </div>
                        <div class="form-hint">Wholesale price resellers pay per message.</div>
                    </div>
                </div>

                <div class="alert alert-info" style="margin-top:10px">
                    <i class="fa-solid fa-circle-info"></i>
                    These rates set the platform-wide defaults. Individual users can be given custom rates on the <a href="/admin/edit-user.php" style="color:inherit;text-decoration:underline">Edit User</a> page.
                </div>
            </div>
            <div class="card-footer" style="padding:16px 24px">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Global Rates</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Profit Analysis</h3></div>
        <div class="card-body">
            <div style="margin-bottom:20px">
                <div style="font-size:12px; color:var(--text-muted); margin-bottom:5px">Client Margin</div>
                <div style="font-size:24px; font-weight:800; color:var(--success)"><?= $clientMargin ?>%</div>
                <div style="font-size:11px; color:var(--text-muted)">
                    Profit per client message: <strong>KES <?= number_format($profitPerMsg, 2) ?></strong>
                </div>
            </div>

            <hr style="border:none; border-top:1px solid var(--border); margin:20px 0">

            <div style="display:flex; flex-direction:column; gap:10px">
                <div style="display:flex; justify-content:space-between; font-size:13px">
                    <span>Client Rate</span>
                    <span style="font-weight:700">KES <?= number_format($basePrice, 2) ?></span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:13px">
                    <span>Reseller Rate</span>
                    <span style="font-weight:700">KES <?= number_format($resellerPrice, 2) ?></span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:13px; margin-top:5px; padding-top:5px; border-top:1px dashed var(--border)">
                    <span>Spread per Message</span>
                    <span style="font-weight:900; color:var(--primary)">KES <?= number_format($profitPerMsg, 2) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
