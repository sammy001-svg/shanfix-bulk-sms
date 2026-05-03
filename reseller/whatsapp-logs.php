<?php
try {
$pageTitle = 'WhatsApp Message Logs';
$breadcrumb = [['label'=>'WhatsApp'],['label'=>'Logs']];
require_once __DIR__ . '/layout.php';

$uid = $user['id'];

// Get Stats
$stats = [
    'sent' => (int)DB::queryValue("SELECT COUNT(*) FROM whatsapp_messages WHERE user_id = ? AND status = 'sent'", [$uid]),
    'delivered' => (int)DB::queryValue("SELECT COUNT(*) FROM whatsapp_messages WHERE user_id = ? AND status = 'delivered'", [$uid]),
    'failed' => (int)DB::queryValue("SELECT COUNT(*) FROM whatsapp_messages WHERE user_id = ? AND status = 'failed'", [$uid]),
];

// Get Logs with Account Info
$messages = DB::query("
    SELECT m.*, a.account_name, a.phone_number as sender_number
    FROM whatsapp_messages m
    LEFT JOIN whatsapp_accounts a ON m.account_id = a.id
    WHERE m.user_id = ?
    ORDER BY m.created_at DESC 
    LIMIT 100
", [$uid]) ?: [];
?>

<div class="page-header">
  <div><h1>WhatsApp Logs</h1><div class="subtitle">Review campaign performance and delivery receipts</div></div>
  <div style="display:flex; gap:10px">
    <button class="btn btn-outline" onclick="window.location.reload()"><i class="fa-solid fa-sync"></i> Refresh Status</button>
  </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-24 mb-24">
    <div class="card p-24" style="display:flex; align-items:center; gap:20px; border-left:4px solid var(--primary)">
        <div style="width:48px; height:48px; background:rgba(0, 100, 255, 0.1); color:var(--primary); border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px">
            <i class="fa-solid fa-paper-plane"></i>
        </div>
        <div>
            <div class="text-muted" style="font-size:12px; font-weight:600; text-transform:uppercase">Total Sent</div>
            <div style="font-size:24px; font-weight:800"><?= number_format($stats['sent']) ?></div>
        </div>
    </div>
    <div class="card p-24" style="display:flex; align-items:center; gap:20px; border-left:4px solid var(--success)">
        <div style="width:48px; height:48px; background:rgba(0, 200, 150, 0.1); color:var(--success); border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px">
            <i class="fa-solid fa-check-double"></i>
        </div>
        <div>
            <div class="text-muted" style="font-size:12px; font-weight:600; text-transform:uppercase">Delivered</div>
            <div style="font-size:24px; font-weight:800"><?= number_format($stats['delivered']) ?></div>
        </div>
    </div>
    <div class="card p-24" style="display:flex; align-items:center; gap:20px; border-left:4px solid var(--danger)">
        <div style="width:48px; height:48px; background:rgba(255, 100, 100, 0.1); color:var(--danger); border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <div>
            <div class="text-muted" style="font-size:12px; font-weight:600; text-transform:uppercase">Failed</div>
            <div style="font-size:24px; font-weight:800"><?= number_format($stats['failed']) ?></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">Recent Transmissions</h3></div>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>From Account</th>
                    <th>Recipient</th>
                    <th>Message Snippet</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($messages)): ?>
                    <tr><td colspan="5" class="text-center" style="padding:40px">No messages logged yet.</td></tr>
                <?php else: foreach ($messages as $m): ?>
                    <tr>
                        <td style="font-size:12px; white-space:nowrap"><?= date('d M Y, H:i', strtotime($m['created_at'])) ?></td>
                        <td>
                            <div style="font-weight:600"><?= htmlspecialchars($m['account_name'] ?: 'Unknown') ?></div>
                            <div style="font-size:10px; color:var(--text-muted)"><?= htmlspecialchars($m['sender_number'] ?: '—') ?></div>
                        </td>
                        <td><strong><?= htmlspecialchars($m['recipient']) ?></strong></td>
                        <td title="<?= htmlspecialchars($m['message']) ?>">
                            <div style="max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:13px">
                                <?= $m['media_url'] ? '<i class="fa-solid fa-image" style="color:var(--primary)"></i> ' : '' ?>
                                <?= htmlspecialchars($m['message']) ?>
                            </div>
                        </td>
                        <td>
                            <?php
                            $statusClass = 'badge-muted';
                            if ($m['status'] === 'sent') $statusClass = 'badge-primary';
                            if ($m['status'] === 'delivered') $statusClass = 'badge-success';
                            if ($m['status'] === 'failed') $statusClass = 'badge-danger';
                            ?>
                            <span class="badge <?= $statusClass ?>"><?= strtoupper($m['status']) ?></span>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
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
