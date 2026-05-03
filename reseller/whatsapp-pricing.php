<?php
try {
$pageTitle = 'WhatsApp Pricing Management';
$breadcrumb = [['label'=>'WhatsApp'],['label'=>'Pricing']];
require_once __DIR__ . '/layout.php';

$uid = $user['id'];

// Get Reseller's own rate (what they pay the admin)
$resellerRate = (float)($user['whatsapp_rate'] ?? 1.20);

// Get the current rate they charge their clients
// We'll take the average or just the rate of the first client found
$clientRateRow = DB::queryOne("SELECT whatsapp_rate FROM users WHERE parent_id = ? LIMIT 1", [$uid]);
$currentClientRate = $clientRateRow ? (float)$clientRateRow['whatsapp_rate'] : ($resellerRate + 0.50);

// Handle Pricing Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_pricing'])) {
    if (!csrf_verify()) {
        flash_set('danger', 'Invalid security token.');
    } else {
        $newPrice = (float)$_POST['client_price'];
        if ($newPrice >= $resellerRate) {
            // Update all clients of this reseller
            DB::execute("UPDATE users SET whatsapp_rate = ? WHERE parent_id = ?", [$newPrice, $uid]);
            flash_set('success', 'Your client WhatsApp pricing has been updated to KES ' . number_format($newPrice, 2));
            $currentClientRate = $newPrice;
        } else {
            flash_set('danger', 'Your selling price cannot be lower than your buying rate of KES ' . number_format($resellerRate, 2));
        }
    }
}

$profit = $currentClientRate - $resellerRate;
$margin = $currentClientRate > 0 ? ($profit / $currentClientRate) * 100 : 0;
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
                
                <div class="form-group mb-20">
                    <label class="form-label">Your Buying Rate (To Admin)</label>
                    <div class="input-group">
                        <span class="input-group-text">KES</span>
                        <input type="text" class="form-control" value="<?= number_format($resellerRate, 2) ?>" disabled>
                    </div>
                    <div class="form-hint">This is the fixed price you pay per WhatsApp message.</div>
                </div>

                <div class="form-group mb-20">
                    <label class="form-label">Client Selling Rate</label>
                    <div class="input-group">
                        <span class="input-group-text">KES</span>
                        <input type="number" name="client_price" class="form-control" value="<?= number_format($currentClientRate, 2) ?>" step="0.01" min="<?= $resellerRate ?>" required>
                    </div>
                    <div class="form-hint">The price your clients will be charged. All your clients will be updated.</div>
                </div>

                <div style="background:var(--bg-muted); padding:20px; border-radius:12px; border:1px solid var(--border); display:flex; flex-direction:column; gap:10px">
                    <div style="display:flex; justify-content:space-between; align-items:center">
                        <span class="text-muted" style="font-size:12px">Profit Per Message:</span>
                        <span style="font-weight:900; color:var(--success); font-size:18px">KES <?= number_format($profit, 2) ?></span>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center">
                        <span class="text-muted" style="font-size:12px">Gross Margin:</span>
                        <span style="font-weight:700; color:var(--primary)"><?= number_format($margin, 1) ?>%</span>
                    </div>
                </div>
            </div>
            <div class="card-footer" style="padding:16px 24px">
                <button type="submit" name="update_pricing" class="btn btn-primary btn-full">
                    <i class="fa-solid fa-save"></i> Save & Apply to All Clients
                </button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Market Analysis</h3></div>
        <div class="card-body" style="padding:24px">
            <p class="text-muted" style="font-size:14px; margin-bottom:20px">
                Your current selling price of <strong>KES <?= number_format($currentClientRate, 2) ?></strong> puts you in a 
                <span class="badge badge-success"><?= $currentClientRate < 2.5 ? 'Highly Competitive' : 'Premium' ?></span> position.
            </p>
            
            <div style="display:flex; flex-direction:column; gap:15px">
                <div style="display:flex; justify-content:space-between; font-size:13px">
                    <span>Admin Buying Rate</span>
                    <span style="font-weight:700">KES <?= number_format($resellerRate, 2) ?></span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:13px">
                    <span>Market Average</span>
                    <span style="font-weight:700">KES 2.80 - 3.50</span>
                </div>
                
                <div style="margin-top:20px">
                    <div style="display:flex; justify-content:space-between; margin-bottom:8px">
                        <span style="font-size:12px; font-weight:600">Competitive Index</span>
                        <span style="font-size:12px; color:var(--primary)"><?= number_format(100 - ($margin/2), 0) ?>%</span>
                    </div>
                    <div style="height:8px; background:var(--bg-muted); border-radius:4px; overflow:hidden">
                        <div style="width:<?= 100 - ($margin/2) ?>%; height:100%; background:var(--primary); transition: width 0.5s ease"></div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-20" style="font-size:12px; padding:12px">
                    <i class="fa-solid fa-lightbulb"></i> 
                    <strong>Pro Tip:</strong> Higher volumes allow for lower margins. Consider offering discounts to high-volume clients manually.
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
<?php
} catch (Throwable $e) {
    echo "<div style='padding:20px; border:2px solid red; background:#fff1f1; color:red; font-family:monospace; margin:20px; border-radius:10px;'>";
    echo "<h3>⚠️ PHP Execution Error</h3>" . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>
