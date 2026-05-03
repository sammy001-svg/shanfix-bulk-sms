<?php
$pageTitle = 'WhatsApp Message Logs';
$breadcrumb = [['label'=>'WhatsApp'],['label'=>'Logs']];
require_once __DIR__ . '/layout.php';

$uid = $user['id'];

$stats = [
    'sent' => DB::queryValue("SELECT COUNT(*) FROM whatsapp_messages WHERE user_id = ? AND status = 'sent'", [$uid]) ?: 0,
    'delivered' => DB::queryValue("SELECT COUNT(*) FROM whatsapp_messages WHERE user_id = ? AND status = 'delivered'", [$uid]) ?: 0,
    'failed' => DB::queryValue("SELECT COUNT(*) FROM whatsapp_messages WHERE user_id = ? AND status = 'failed'", [$uid]) ?: 0,
];

$messages = DB::query("SELECT * FROM whatsapp_messages WHERE user_id = ? ORDER BY created_at DESC LIMIT 100", [$uid]);
?>

<div class="page-header">
  <div><h1>Message History</h1><div class="subtitle">Track delivery receipts and interactions</div></div>
</div>

<div class="stats-grid mb-24">
    <div class="stat-card">
        <div class="stat-details"><div class="stat-label">Sent</div><div class="stat-value"><?= number_format($stats['sent']) ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-details"><div class="stat-label">Delivered</div><div class="stat-value"><?= number_format($stats['delivered']) ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-details"><div class="stat-label">Failed</div><div class="stat-value"><?= number_format($stats['failed']) ?></div></div>
    </div>
</div>

<div class="card">
    <div class="card-header">Recent Activity</div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Timestamp</th><th>Recipient</th><th>Message</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($messages as $m): ?>
                    <tr>
                        <td><?= date('d M, H:i', strtotime($m['created_at'])) ?></td>
                        <td><strong><?= htmlspecialchars($m['recipient']) ?></strong></td>
                        <td><?= htmlspecialchars($m['message']) ?></td>
                        <td><span class="badge"><?= strtoupper($m['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
