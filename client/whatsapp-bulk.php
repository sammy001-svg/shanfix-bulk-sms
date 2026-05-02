<?php
$pageTitle = 'WhatsApp Bulk Messaging';
$breadcrumb = [['label'=>'WhatsApp'],['label'=>'Bulk Messaging']];
require_once __DIR__ . '/layout.php';

$uid = $user['id'];
$account = DB::queryOne("SELECT * FROM whatsapp_accounts WHERE user_id = ? AND status = 'active'", [$uid]);

// Handle Sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_whatsapp'])) {
    if (!csrf_verify()) {
        flash_set('danger', 'Invalid security token.');
    } elseif (!$account) {
        flash_set('danger', 'Please connect and activate your WhatsApp account first.');
    } else {
        $recipientsStr = $_POST['recipients'];
        $message = sanitize($_POST['message']);
        $mediaUrl = sanitize($_POST['media_url'] ?? '');
        
        $recipients = explode("\n", str_replace("\r", "", $recipientsStr));
        $count = 0;

        require_once __DIR__ . '/../includes/gateways/whatsapp.php';
        $gateway = new WhatsApp_Gateway($account['instance_id'], $account['token']);

        foreach ($recipients as $number) {
            $number = trim($number);
            if (empty($number)) continue;

            // Log Message (Status 'queued')
            $msgId = DB::insert("
                INSERT INTO whatsapp_messages (user_id, account_id, recipient, message, media_url, status)
                VALUES (?, ?, ?, ?, ?, 'queued')
            ", [$uid, $account['id'], $number, $message, $mediaUrl]);

            if ($msgId) {
                // Send via Gateway
                $res = $gateway->sendMessage($number, $message, $mediaUrl);
                if ($res['success']) {
                    DB::execute("UPDATE whatsapp_messages SET status = 'sent', external_id = ? WHERE id = ?", [$res['message_id'], $msgId]);
                    $count++;
                } else {
                    DB::execute("UPDATE whatsapp_messages SET status = 'failed' WHERE id = ?", [$msgId]);
                }
            }
        }

        if ($count > 0) {
            flash_set('success', "Campaign initiated! Successfully sent $count messages.");
        } else {
            flash_set('danger', 'Failed to send messages. Please check your account status.');
        }
    }
}
?>

<div class="page-header">
  <div><h1>Bulk WhatsApp</h1><div class="subtitle">Broadcast high-impact messages and media to your customers</div></div>
</div>

<?php if (!$account): ?>
<div class="alert alert-warning mb-24">
    <div style="display:flex; gap:15px; align-items:center">
        <i class="fa-solid fa-triangle-exclamation" style="font-size:24px"></i>
        <div>
            <strong>Action Required:</strong> Your WhatsApp account is not active. 
            <a href="whatsapp-connect.php" style="color:inherit; text-decoration:underline">Connect your account</a> to enable bulk messaging.
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <form method="POST">
        <div class="card-body" style="padding:24px">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:24px">
                <div>
                    <div class="form-group mb-16">
                        <label class="form-label">Recipients (One per line)</label>
                        <textarea name="recipients" class="form-control" rows="10" placeholder="254712345678&#10;254787654321" required></textarea>
                        <div class="form-hint">Numbers should be in international format without the '+' sign.</div>
                    </div>
                </div>
                
                <div>
                    <div class="form-group mb-16">
                        <label class="form-label">Message Content</label>
                        <textarea name="message" class="form-control" rows="6" placeholder="Type your message here..." required></textarea>
                        <div class="form-hint">Support for emojis and personalization variables.</div>
                    </div>

                    <div class="form-group mb-16">
                        <label class="form-label">Media URL (Optional)</label>
                        <input type="url" name="media_url" class="form-control" placeholder="https://example.com/image.jpg">
                        <div class="form-hint">Direct link to an image, PDF, or video to attach to your message.</div>
                    </div>

                    <div style="background:var(--bg-muted); padding:15px; border-radius:12px; border:1px dashed var(--border)">
                        <div style="display:flex; justify-content:space-between; margin-bottom:5px">
                            <span class="text-muted" style="font-size:12px">Estimated Cost:</span>
                            <span style="font-weight:700">KES <?= number_format($count ?? 0 * 2.50, 2) ?></span>
                        </div>
                        <div style="display:flex; justify-content:space-between">
                            <span class="text-muted" style="font-size:12px">Account Balance:</span>
                            <span style="font-weight:700">KES <?= number_format($user['whatsapp_balance'] ?? 0, 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer" style="padding:16px 24px; display:flex; justify-content:flex-end">
            <button type="submit" name="send_whatsapp" class="btn btn-primary btn-lg" <?= !$account ? 'disabled' : '' ?>>
                <i class="fa-solid fa-paper-plane"></i> Launch Campaign
            </button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
