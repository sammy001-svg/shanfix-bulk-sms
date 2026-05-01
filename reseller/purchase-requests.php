<?php
/**
 * Reseller: Client Purchase Requests - Shanfix Technology
 * Allows resellers to approve or deny manual M-Pesa payments from their clients.
 */
$pageTitle = 'Client Purchase Requests';
$breadcrumb = [['label'=>'Reseller'],['label'=>'Billing'],['label'=>'Purchase Requests']];
require_once __DIR__ . '/layout.php';

$uid = $user['id'];

// Handle Approval/Denial
if (isset($_POST['action']) && isset($_POST['purchase_id'])) {
    if (!csrf_verify()) {
        flash_set('danger', 'Invalid security token.');
    } else {
        $pid = (int)$_POST['purchase_id'];
        $action = $_POST['action']; // 'approve' or 'deny'
        
        // Verify purchase belongs to one of this reseller's clients
        $purchase = DB::queryOne("
            SELECT p.*, u.name as client_name, u.parent_id 
            FROM purchases p 
            JOIN users u ON p.user_id = u.id 
            WHERE p.id = ? AND u.parent_id = ? AND p.status = 'pending'
        ", [$pid, $uid]);

        if ($purchase) {
            if ($action === 'approve') {
                // Check if reseller has enough units
                if ($user['sms_units'] < $purchase['units']) {
                    flash_set('danger', 'You have insufficient units to approve this request. Please top up your account.');
                } else {
                    require_once __DIR__ . '/../includes/actions/purchases.php';
                    if (Purchase::complete($pid)) {
                        flash_set('success', "Purchase approved! " . number_format($purchase['units']) . " units added to " . htmlspecialchars($purchase['client_name']) . ".");
                    } else {
                        flash_set('danger', 'An error occurred while processing the top-up.');
                    }
                }
            } else {
                // Deny
                DB::execute("UPDATE purchases SET status = 'failed' WHERE id = ?", [$pid]);
                flash_set('info', 'Purchase request has been denied.');
            }
        } else {
            flash_set('danger', 'Invalid purchase request.');
        }
    }
    redirect('/reseller/purchase-requests.php');
}

// Fetch pending requests from clients
$requests = DB::query("
    SELECT p.*, u.name as client_name, u.phone as client_phone
    FROM purchases p
    JOIN users u ON p.user_id = u.id
    WHERE u.parent_id = ? AND p.payment_method = 'manual_mpesa' AND p.status = 'pending'
    ORDER BY p.created_at DESC
", [$uid]);

// Fetch recent history
$history = DB::query("
    SELECT p.*, u.name as client_name
    FROM purchases p
    JOIN users u ON p.user_id = u.id
    WHERE u.parent_id = ? AND p.payment_method = 'manual_mpesa' AND p.status != 'pending'
    ORDER BY p.created_at DESC LIMIT 10
", [$uid]);
?>

<div class="page-header">
    <div><h1>Client Purchase Requests</h1><div class="subtitle">Review and approve manual M-Pesa payments from your clients</div></div>
</div>

<div class="card mb-24">
    <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-clock-rotate-left" style="color:var(--warning)"></i> Pending Requests</h3></div>
    <div class="table-wrapper">
        <table class="data-table">
            <thead><tr><th>Client</th><th>Units</th><th>Amount</th><th>M-Pesa Ref</th><th>Date</th><th style="text-align:right">Actions</th></tr></thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr><td colspan="6" class="text-center text-muted" style="padding:40px">No pending purchase requests found.</td></tr>
                <?php else: foreach ($requests as $r): ?>
                    <tr>
                        <td>
                            <div style="font-weight:600"><?= htmlspecialchars($r['client_name']) ?></div>
                            <div style="font-size:11px;color:var(--text-muted)"><?= htmlspecialchars($r['client_phone']) ?></div>
                        </td>
                        <td><strong><?= number_format($r['units']) ?></strong> units</td>
                        <td>KES <?= number_format($r['amount'], 2) ?></td>
                        <td><code style="background:var(--primary-light);padding:2px 6px;border-radius:4px"><?= htmlspecialchars($r['transaction_ref']) ?></code></td>
                        <td style="font-size:12px"><?= date('d M Y H:i', strtotime($r['created_at'])) ?></td>
                        <td style="text-align:right">
                            <form method="POST" style="display:inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="purchase_id" value="<?= $r['id'] ?>">
                                <button type="submit" name="action" value="approve" class="btn btn-sm btn-success" onclick="return confirm('Are you sure you want to approve this payment and top up the client?')">
                                    <i class="fa-solid fa-check"></i> Approve
                                </button>
                                <button type="submit" name="action" value="deny" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to deny this request?')">
                                    <i class="fa-solid fa-xmark"></i> Deny
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-history" style="color:var(--primary)"></i> Recent Approvals</h3></div>
    <div class="table-wrapper">
        <table class="data-table">
            <thead><tr><th>Client</th><th>Units</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
                <?php if (empty($history)): ?>
                    <tr><td colspan="5" class="text-center text-muted" style="padding:24px">No recent history.</td></tr>
                <?php else: foreach ($history as $h): $sc = $h['status'] === 'completed' ? 'success' : 'danger'; ?>
                    <tr>
                        <td><?= htmlspecialchars($h['client_name']) ?></td>
                        <td><?= number_format($h['units']) ?> units</td>
                        <td>KES <?= number_format($h['amount'], 2) ?></td>
                        <td><span class="badge badge-<?= $sc ?>"><?= ucfirst($h['status']) ?></span></td>
                        <td style="font-size:12px"><?= date('d M Y', strtotime($h['created_at'])) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
