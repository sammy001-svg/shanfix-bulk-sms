<?php
$pageTitle = 'USSD Wallet';
$breadcrumb = [['label'=>'USSD'],['label'=>'Wallet']];
require_once __DIR__ . '/layout.php';

$uid = $user['id'];

// Fetch Transactions
$transactions = DB::query("
    SELECT * FROM ussd_transactions 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 50
", [$uid]);

// Fetch specific rates (These should ideally come from the parent reseller's settings)
$parentId = $user['parent_id'];
$ussdSessionRate = 1.28; // Standard rate
$ussdRequestRate = 0.20; // Default client rate

if ($parentId) {
    // Check if reseller has set custom USSD rates for this client or globally
    // For now, we'll use these defaults or mock logic.
    // In a real scenario, you'd fetch from a 'reseller_ussd_rates' table.
}
?>

<div class="page-header">
  <div><h1>USSD Wallet</h1><div class="subtitle">Manage your USSD funds and view transaction history</div></div>
</div>

<div style="display:grid; grid-template-columns: 1fr 2fr; gap:24px; margin-bottom:24px">
    <!-- Balance Card -->
    <div class="card" style="background:linear-gradient(135deg, var(--primary) 0%, #3b82f6 100%); color:#fff; border:none; position:relative; overflow:hidden">
        <div style="position:absolute; top:-20px; right:-20px; opacity:0.1; font-size:120px"><i class="fa-solid fa-wallet"></i></div>
        <div class="card-body" style="padding:32px; position:relative; z-index:1">
            <div style="font-size:12px; text-transform:uppercase; letter-spacing:0.1em; opacity:0.8">Available Balance</div>
            <div style="font-size:42px; font-weight:800; margin:10px 0">KES <?= number_format($user['ussd_balance'] ?? 0, 2) ?></div>
            <div style="display:flex; gap:10px; margin-top:20px">
                <button class="btn btn-light btn-sm" style="background:rgba(255,255,255,0.2); border:none; color:#fff" onclick="openTopUpModal()">
                    <i class="fa-solid fa-plus-circle"></i> Top Up Balance
                </button>
            </div>
        </div>
    </div>

    <!-- Stats Card -->
    <div class="card">
        <div class="card-body" style="padding:0; display:grid; grid-template-columns: 1fr 1fr; height:100%">
            <div style="padding:24px; border-right:1px solid var(--border)">
                <div class="text-muted" style="font-size:11px; font-weight:700; text-transform:uppercase">Session Rate</div>
                <div style="font-size:24px; font-weight:800; color:var(--text-primary)">KES <?= number_format($ussdSessionRate, 2) ?></div>
                <div class="text-muted" style="font-size:11px">Per unique user session</div>
            </div>
            <div style="padding:24px">
                <div class="text-muted" style="font-size:11px; font-weight:700; text-transform:uppercase">Forwarding Rate</div>
                <div style="font-size:24px; font-weight:800; color:var(--text-primary)">KES <?= number_format($ussdRequestRate, 2) ?></div>
                <div class="text-muted" style="font-size:11px">Per HTTP request forwarded</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center">
        <h3 class="card-title"><i class="fa-solid fa-receipt" style="color:var(--primary)"></i> Transaction History</h3>
        <div class="badge badge-outline">Last 50 Transactions</div>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Description</th>
                        <th>Type</th>
                        <th style="text-align:right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr><td colspan="5" class="text-center" style="padding:40px; color:var(--text-muted)">No transactions found in your wallet history.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($transactions as $t): ?>
                        <tr>
                            <td style="font-size:12px"><?= date('d M Y, H:i', strtotime($t['created_at'])) ?></td>
                            <td style="font-family:monospace; font-size:11px"><?= htmlspecialchars($t['reference'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($t['description']) ?></td>
                            <td>
                                <span class="badge badge-<?= $t['type'] === 'credit' ? 'success' : 'danger' ?>">
                                    <?= strtoupper($t['type']) ?>
                                </span>
                            </td>
                            <td style="text-align:right; font-weight:700; color: <?= $t['type'] === 'credit' ? 'var(--success)' : 'var(--danger)' ?>">
                                <?= $t['type'] === 'credit' ? '+' : '-' ?> KES <?= number_format($t['amount'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Top Up Modal -->
<div id="topupModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:transparent !important; backdrop-filter:none !important; z-index:2000; align-items:center; justify-content:center">
  <div class="card" style="width:100%; max-width:400px; margin:20px; border:none; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25)">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border-light)">
      <h3 class="card-title" style="margin:0"><i class="fa-solid fa-mobile-screen-button" style="color:var(--primary)"></i> M-Pesa Top Up</h3>
      <button type="button" class="btn btn-icon" onclick="closeTopUpModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form action="/client/actions/ussd-topup.php" method="POST" id="topupForm">
        <div class="card-body" style="padding:24px">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            
            <div class="form-group mb-16">
                <label class="form-label">Amount (KES)</label>
                <div style="position:relative">
                    <span style="position:absolute; left:12px; top:12px; color:var(--text-muted); font-weight:700">KES</span>
                    <input type="number" name="amount" class="form-control" style="padding-left:45px" placeholder="e.g. 1000" min="10" required>
                </div>
            </div>

            <div class="form-group mb-16">
                <label class="form-label">M-Pesa Phone Number</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="2547XXXXXXXX" required>
                <div class="form-hint">Format: 2547XXXXXXXX</div>
            </div>

            <div class="alert alert-info" style="font-size:11px; margin:0">
                <i class="fa-solid fa-info-circle"></i> You will receive an STK Push prompt on your phone to enter your M-Pesa PIN.
            </div>
        </div>
        <div class="card-footer" style="padding:16px 24px; display:flex; gap:10px">
            <button type="button" class="btn btn-muted flex-1" onclick="closeTopUpModal()">Cancel</button>
            <button type="submit" class="btn btn-primary flex-1">Send Prompt</button>
        </div>
    </form>
  </div>
</div>

<script>
function openTopUpModal() {
    document.getElementById('topupModal').style.display = 'flex';
}
function closeTopUpModal() {
    document.getElementById('topupModal').style.display = 'none';
}
</script>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
