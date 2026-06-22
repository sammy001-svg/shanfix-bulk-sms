<?php
$pageTitle = 'WhatsApp Service Overview';
$breadcrumb = [['label'=>'WhatsApp'],['label'=>'Management']];
require_once __DIR__ . '/layout.php';

// Global Stats — wrapped in try/catch so the page doesn't crash if a table
// hasn't been created yet on this installation.
try {
    $totalAccounts  = (int)DB::queryValue("SELECT COUNT(*) FROM whatsapp_accounts");
    $activeAccounts = (int)DB::queryValue("SELECT COUNT(*) FROM whatsapp_accounts WHERE status = 'active'");
} catch (Exception $e) { $totalAccounts = $activeAccounts = 0; }

try {
    $totalMessages = (int)DB::queryValue("SELECT COUNT(*) FROM whatsapp_messages");
} catch (Exception $e) { $totalMessages = 0; }

// Revenue: sum of completed WhatsApp purchases (not USSD transactions)
try {
    $totalRevenue = (float)DB::queryValue(
        "SELECT COALESCE(SUM(amount), 0) FROM purchases WHERE type = 'whatsapp' AND status = 'completed'"
    );
} catch (Exception $e) { $totalRevenue = 0; }

// Recent Global Activity
try {
    $recentAccounts = DB::query("
        SELECT a.*, u.email, u.name as user_name
        FROM whatsapp_accounts a
        JOIN users u ON a.user_id = u.id
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
} catch (Exception $e) { $recentAccounts = []; }
?>

<div class="page-header">
  <div><h1>WhatsApp Management</h1><div class="subtitle">Global oversight of instances, traffic, and service health</div></div>
</div>

<div class="stats-grid mb-24">
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(59, 130, 246, 0.1); color:var(--primary)"><i class="fa-brands fa-whatsapp"></i></div>
        <div class="stat-details">
            <div class="stat-label">Connected Instances</div>
            <div class="stat-value"><?= number_format($totalAccounts) ?></div>
            <div class="stat-hint" style="color:var(--success)"><?= number_format($activeAccounts) ?> Currently Active</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(16, 185, 129, 0.1); color:var(--success)"><i class="fa-solid fa-paper-plane"></i></div>
        <div class="stat-details">
            <div class="stat-label">Global Traffic</div>
            <div class="stat-value"><?= number_format($totalMessages) ?></div>
            <div class="stat-hint">Messages Processed</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(139, 92, 246, 0.1); color:var(--secondary)"><i class="fa-solid fa-vault"></i></div>
        <div class="stat-details">
            <div class="stat-label">Estimated Revenue</div>
            <div class="stat-value">KES <?= number_format($totalRevenue ?? 0, 2) ?></div>
            <div class="stat-hint">From WhatsApp Credits</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">Recent Instance Connections</h3></div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>User</th>
                        <th>Instance ID</th>
                        <th>Status</th>
                        <th style="text-align:right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentAccounts as $a): ?>
                        <tr>
                            <td style="font-size:12px"><?= date('d M Y, H:i', strtotime($a['created_at'])) ?></td>
                            <td>
                                <div style="font-weight:700"><?= htmlspecialchars($a['email']) ?></div>
                                <div style="font-size:10px; color:var(--text-muted)"><?= htmlspecialchars($a['user_name']) ?></div>
                            </td>
                            <td><code style="font-size:11px"><?= htmlspecialchars($a['instance_id']) ?></code></td>
                            <td>
                                <span class="badge badge-<?= $a['status'] === 'active' ? 'success' : ($a['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                    <?= strtoupper($a['status']) ?>
                                </span>
                            </td>
                            <td style="text-align:right">
                                <a href="/admin/user-detail.php?id=<?= $a['user_id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Manage
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
