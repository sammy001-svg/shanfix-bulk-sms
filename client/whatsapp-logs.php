<?php
$pageTitle = 'WhatsApp Message Logs';
$breadcrumb = [['label'=>'WhatsApp'],['label'=>'Logs']];
require_once __DIR__ . '/layout.php';

$uid = $user['id'];

// Stats for logs
$stats = [
    'sent' => DB::queryValue("SELECT COUNT(*) FROM whatsapp_messages WHERE user_id = ? AND status = 'sent'", [$uid]),
    'delivered' => DB::queryValue("SELECT COUNT(*) FROM whatsapp_messages WHERE user_id = ? AND status = 'delivered'", [$uid]),
    'read' => DB::queryValue("SELECT COUNT(*) FROM whatsapp_messages WHERE user_id = ? AND status = 'read'", [$uid]),
    'failed' => DB::queryValue("SELECT COUNT(*) FROM whatsapp_messages WHERE user_id = ? AND status = 'failed'", [$uid]),
];

// Fetch Messages
$messages = DB::query("SELECT * FROM whatsapp_messages WHERE user_id = ? ORDER BY created_at DESC LIMIT 100", [$uid]);
?>

<div class="page-header">
  <div><h1>Message History</h1><div class="subtitle">Track delivery receipts and customer interactions in real-time</div></div>
</div>

<div class="stats-grid mb-24">
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(59, 130, 246, 0.1); color:var(--primary)"><i class="fa-solid fa-paper-plane"></i></div>
        <div class="stat-details">
            <div class="stat-label">Total Sent</div>
            <div class="stat-value"><?= number_format($stats['sent']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(16, 185, 129, 0.1); color:var(--success)"><i class="fa-solid fa-check-double"></i></div>
        <div class="stat-details">
            <div class="stat-label">Delivered</div>
            <div class="stat-value"><?= number_format($stats['delivered']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(139, 92, 246, 0.1); color:var(--secondary)"><i class="fa-solid fa-eye"></i></div>
        <div class="stat-details">
            <div class="stat-label">Read Receipts</div>
            <div class="stat-value"><?= number_format($stats['read']) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:rgba(239, 68, 68, 0.1); color:var(--danger)"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="stat-details">
            <div class="stat-label">Failed</div>
            <div class="stat-value"><?= number_format($stats['failed']) ?></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center">
        <h3 class="card-title">Recent Activity</h3>
        <div class="badge badge-outline">Last 100 Messages</div>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Recipient</th>
                        <th>Message Preview</th>
                        <th>Status</th>
                        <th style="text-align:right">Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($messages)): ?>
                        <tr><td colspan="5" class="text-center" style="padding:40px; color:var(--text-muted)">No messages recorded yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($messages as $m): ?>
                        <tr>
                            <td style="font-size:12px"><?= date('d M Y, H:i', strtotime($m['created_at'])) ?></td>
                            <td style="font-weight:700"><?= htmlspecialchars($m['recipient']) ?></td>
                            <td style="max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap">
                                <?= htmlspecialchars($m['message']) ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= 
                                    $m['status'] === 'read' ? 'secondary' : 
                                    ($m['status'] === 'delivered' ? 'success' : 
                                    ($m['status'] === 'failed' ? 'danger' : 'info')) ?>">
                                    <?= strtoupper($m['status']) ?>
                                </span>
                            </td>
                            <td style="text-align:right">
                                <?php if ($m['status'] === 'read'): ?>
                                    <i class="fa-solid fa-check-double" style="color:var(--secondary)"></i>
                                <?php elseif ($m['status'] === 'delivered'): ?>
                                    <i class="fa-solid fa-check-double" style="color:var(--success)"></i>
                                <?php elseif ($m['status'] === 'sent'): ?>
                                    <i class="fa-solid fa-check" style="color:var(--text-muted)"></i>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
