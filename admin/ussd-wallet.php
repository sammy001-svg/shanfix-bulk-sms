<?php
$pageTitle = 'USSD Wallet Management';
$breadcrumb = [['label'=>'USSD'],['label'=>'Wallet']];
require_once __DIR__ . '/layout.php';

// Handle Manual Adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_adjust'])) {
    if (!csrf_verify()) {
        flash_set('danger', 'Invalid security token.');
    } else {
        $targetUserId = (int)$_POST['user_id'];
        $amount = (float)$_POST['amount'];
        $type = $_POST['type']; // credit or debit
        $description = sanitize($_POST['description']);

        if ($targetUserId > 0 && $amount > 0) {
            require_once __DIR__ . '/../includes/actions/ussd-wallet.php';
            $wallet = new USSD_Wallet();
            $success = $wallet->manualAdjustment($targetUserId, $amount, $type, $description);
            
            if ($success) {
                flash_set('success', 'Wallet adjusted successfully.');
            } else {
                flash_set('danger', 'Failed to adjust wallet. Check user existence and balance.');
            }
        }
    }
}

// Global Stats
$totalBalance = DB::queryValue("SELECT SUM(ussd_balance) FROM users");
$totalTransactions = DB::queryValue("SELECT COUNT(*) FROM ussd_transactions");
$pendingTransactions = DB::queryValue("SELECT COUNT(*) FROM ussd_transactions WHERE status = 'pending'");

// Fetch Transactions with user info
$transactions = DB::query("
    SELECT t.*, u.email, u.name as user_name 
    FROM ussd_transactions t
    JOIN users u ON t.user_id = u.id
    ORDER BY t.created_at DESC 
    LIMIT 100
");

// Fetch users for the dropdown
$usersList = DB::query("SELECT id, email, name FROM users WHERE role IN ('reseller', 'client') ORDER BY email ASC");
?>

<div class="page-header">
  <div><h1>USSD Wallet Management</h1><div class="subtitle">Monitor global USSD funds and manage user balances</div></div>
  <button class="btn btn-primary" onclick="openAdjustModal()"><i class="fa-solid fa-plus-minus"></i> Manual Adjustment</button>
</div>

<div class="stats-grid mb-24">
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(16, 185, 129, 0.1); color:var(--success)"><i class="fa-solid fa-vault"></i></div>
        <div class="stat-details">
            <div class="stat-label">Global User Balance</div>
            <div class="stat-value">KES <?= number_format($totalBalance ?? 0, 2) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(59, 130, 246, 0.1); color:var(--primary)"><i class="fa-solid fa-receipt"></i></div>
        <div class="stat-details">
            <div class="stat-label">Total Transactions</div>
            <div class="stat-value"><?= number_format($totalTransactions) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(245, 158, 11, 0.1); color:var(--warning)"><i class="fa-solid fa-clock"></i></div>
        <div class="stat-details">
            <div class="stat-label">Pending Requests</div>
            <div class="stat-value"><?= number_format($pendingTransactions) ?></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center">
        <h3 class="card-title">Recent USSD Transactions</h3>
        <div class="badge badge-outline">Global History (Last 100)</div>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>User</th>
                        <th>Description</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th style="text-align:right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $t): ?>
                        <tr>
                            <td style="font-size:12px"><?= date('d M Y, H:i', strtotime($t['created_at'])) ?></td>
                            <td>
                                <div style="font-weight:700"><?= htmlspecialchars($t['email']) ?></div>
                                <div style="font-size:11px; color:var(--text-muted)"><?= htmlspecialchars($t['user_name']) ?></div>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($t['description']) ?></div>
                                <div style="font-family:monospace; font-size:10px; color:var(--text-muted)"><?= htmlspecialchars($t['reference'] ?? 'No Ref') ?></div>
                            </td>
                            <td>
                                <span class="badge badge-<?= $t['type'] === 'credit' ? 'success' : 'danger' ?>">
                                    <?= strtoupper($t['type']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?= $t['status'] === 'completed' ? 'success' : ($t['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                    <?= strtoupper($t['status']) ?>
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

<!-- Adjustment Modal -->
<div id="adjustModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:2000; align-items:center; justify-content:center; backdrop-filter:blur(4px)">
  <div class="card" style="width:100%; max-width:500px; margin:20px; border:none; box-shadow: var(--shadow-lg)">
    <div class="card-header">
      <h3 class="card-title">Manual Balance Adjustment</h3>
      <button type="button" class="btn btn-icon" onclick="closeAdjustModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST">
        <div class="card-body" style="padding:24px">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            
            <div class="form-group mb-16">
                <label class="form-label">Select User</label>
                <select name="user_id" class="form-control" required>
                    <option value="">-- Choose User --</option>
                    <?php foreach ($usersList as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['email']) ?> (<?= htmlspecialchars($u['name']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px" class="mb-16">
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-control" required>
                        <option value="credit">Credit (+)</option>
                        <option value="debit">Debit (-)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Amount (KES)</label>
                    <input type="number" name="amount" class="form-control" placeholder="0.00" step="0.01" min="0.01" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Description / Reason</label>
                <textarea name="description" class="form-control" rows="2" placeholder="e.g. Manual Top Up via Cash" required></textarea>
            </div>
        </div>
        <div class="card-footer" style="padding:16px 24px; display:flex; gap:10px">
            <button type="button" class="btn btn-muted flex-1" onclick="closeAdjustModal()">Cancel</button>
            <button type="submit" name="manual_adjust" class="btn btn-primary flex-1">Apply Adjustment</button>
        </div>
    </form>
  </div>
</div>

<script>
function openAdjustModal() {
    document.getElementById('adjustModal').style.display = 'flex';
}
function closeAdjustModal() {
    document.getElementById('adjustModal').style.display = 'none';
}
</script>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
