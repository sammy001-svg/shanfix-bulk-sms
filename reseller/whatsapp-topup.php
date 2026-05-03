<?php
try {
$pageTitle = 'WhatsApp Top-up (Reseller)';
$breadcrumb = [['label'=>'WhatsApp'],['label'=>'Top-up']];
require_once __DIR__ . '/layout.php';

$uid = $user['id'];

// Handle Top-up Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['initiate_topup'])) {
    if (!csrf_verify()) {
        flash_set('danger', 'Invalid security token.');
    } else {
        $amount = (float)$_POST['amount'];
        $phone = sanitize($_POST['phone']);
        
        if ($amount < 10) {
            flash_set('danger', 'Minimum top-up amount is KES 10.00');
        } else {
            require_once __DIR__ . '/../includes/actions/purchases.php';
            
            $purchaseData = [
                'type' => 'whatsapp',
                'custom_units' => 0,
                'payment_method' => 'mpesa',
                'payment_ref' => $phone,
                'plan_id' => 0
            ];
            
            $res = Purchase::create($uid, $purchaseData);
            
            if ($res['success']) {
                if ($res['manual'] ?? false) {
                    flash_set('info', 'Top-up request sent to admin. Please complete payment and wait for approval.');
                } else {
                    flash_set('success', 'STK Push sent to ' . $phone . '. Please enter your M-Pesa PIN.');
                }
            } else {
                flash_set('danger', 'Top-up failed: ' . $res['error']);
            }
        }
    }
}

// Fetch recent top-ups
$topups = DB::query("SELECT * FROM purchases WHERE user_id = ? AND type = 'whatsapp' ORDER BY created_at DESC LIMIT 10", [$uid]) ?: [];
?>

<div class="page-header">
  <div><h1>Reseller Wallet</h1><div class="subtitle">Top up your reseller WhatsApp balance to cover client transmissions</div></div>
</div>

<div style="display:grid; grid-template-columns: 1fr 1fr; gap:30px">
    <!-- Top-up Form -->
    <div class="card">
        <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-wallet" style="color:var(--primary)"></i> Reseller Balance</h3></div>
        <form method="POST">
            <div class="card-body">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                
                <div class="mb-24">
                    <div style="background:var(--bg-muted); padding:20px; border-radius:15px; border:1px solid var(--border); text-align:center">
                        <div style="font-size:12px; color:var(--text-muted); margin-bottom:5px">Current Balance</div>
                        <div style="font-size:32px; font-weight:800; color:var(--primary)">KES <?= number_format($user['whatsapp_balance'], 2) ?></div>
                        <div style="font-size:11px; color:var(--text-muted); margin-top:5px">Platform Rate: KES <?= number_format($user['whatsapp_rate'], 2) ?> per msg</div>
                    </div>
                </div>

                <div class="form-group mb-20">
                    <label class="form-label">Top up Amount (KES)</label>
                    <div class="input-group">
                        <span class="input-group-text">KES</span>
                        <input type="number" name="amount" class="form-control" placeholder="e.g. 1000" required min="10">
                    </div>
                </div>

                <div class="form-group mb-20">
                    <label class="form-label">M-Pesa Number</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-mobile-screen"></i></span>
                        <input type="text" name="phone" class="form-control" placeholder="07..." required value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" name="initiate_topup" class="btn btn-primary btn-block">
                    <i class="fa-solid fa-paper-plane"></i> Pay Now
                </button>
            </div>
        </form>
    </div>

    <!-- History -->
    <div class="card">
        <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-history"></i> Top-up History</h3></div>
        <div class="card-body" style="padding:0">
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($topups)): ?>
                            <tr><td colspan="3" class="text-center" style="padding:40px; color:var(--text-muted)">No transactions yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($topups as $t): ?>
                            <tr>
                                <td><?= date('d M, H:i', strtotime($t['created_at'])) ?></td>
                                <td>KES <?= number_format($t['amount'], 2) ?></td>
                                <td><span class="badge badge-<?= $t['status'] === 'completed' ? 'success' : 'warning' ?>"><?= strtoupper($t['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>

<?php
} catch (Throwable $e) {
    echo "<div style='padding:20px; border:2px solid red; background:#fff1f1; color:red; font-family:monospace; margin:20px; border-radius:10px;'>";
    echo "<h3>⚠️ Reseller Top-up Error</h3>";
    echo "<b>Message:</b> " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "<b>File:</b> " . htmlspecialchars($e->getFile()) . "<br>";
    echo "<b>Line:</b> " . $e->getLine() . "<br>";
    echo "</div>";
}
?>
