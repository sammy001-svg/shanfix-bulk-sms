<?php
$pageTitle = 'WhatsApp Bulk Messaging';
$breadcrumb = [['label'=>'WhatsApp'],['label'=>'Bulk Messaging']];
require_once __DIR__ . '/layout.php';

$uid = $user['id'];
$account = DB::queryOne("SELECT * FROM whatsapp_accounts WHERE user_id = ? AND status = 'active'", [$uid]);
$groups = DB::query("SELECT g.*, COUNT(c.id) as cnt FROM whatsapp_contact_groups g LEFT JOIN whatsapp_contacts c ON c.group_id = g.id WHERE g.user_id = ? GROUP BY g.id ORDER BY g.name", [$uid]);

// Handle Sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_whatsapp'])) {
    if (!csrf_verify()) {
        flash_set('danger', 'Invalid security token.');
    } elseif (!$account) {
        flash_set('danger', 'Please connect and activate your WhatsApp account first.');
    } else {
        $msgTemplate = $_POST['message'] ?? '';
        $mediaUrl = sanitize($_POST['media_url'] ?? '');
        $csvData = $_POST['csv_data'] ?? '';
        $recipientsStr = $_POST['recipients'] ?? '';

        if (!empty($_FILES['media_file']['name'])) {
            $targetDir = __DIR__ . '/../uploads/whatsapp/';
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            $fileName = time() . '_' . preg_replace("/[^a-zA-Z0-9\._-]/", "_", basename($_FILES['media_file']['name']));
            if (move_uploaded_file($_FILES['media_file']['tmp_name'], $targetDir . $fileName)) {
                $mediaUrl = rtrim(SITE_URL, '/') . '/uploads/whatsapp/' . $fileName;
            }
        }
        
        $contacts = [];
        if (!empty($csvData)) {
            $handle = fopen('php://temp', 'r+');
            fwrite($handle, $csvData);
            rewind($handle);
            $headers = array_map('strtolower', fgetcsv($handle));
            $phoneIdx = array_search('phone', $headers);
            if ($phoneIdx !== false) {
                while (($row = fgetcsv($handle)) !== false) {
                    $phone = trim($row[$phoneIdx] ?? '');
                    if ($phone) {
                        $data = [];
                        foreach ($headers as $idx => $h) $data[$h] = trim($row[$idx] ?? '');
                        $contacts[] = ['phone' => $phone, 'data' => $data];
                    }
                }
            }
            fclose($handle);
        } elseif (!empty($_POST['group_id'])) {
            $groupId = (int)$_POST['group_id'];
            $groupRows = DB::query("SELECT phone, name, email FROM whatsapp_contacts WHERE group_id = ? AND user_id = ?", [$groupId, $uid]);
            foreach ($groupRows as $gr) $contacts[] = ['phone' => $gr['phone'], 'data' => ['name' => $gr['name'], 'email' => $gr['email']]];
        } else {
            foreach (explode("\n", str_replace("\r", "", $recipientsStr)) as $num) {
                if ($num = trim($num)) $contacts[] = ['phone' => $num, 'data' => []];
            }
        }

        if (empty($contacts)) {
            flash_set('danger', 'No valid recipients found.');
        } else {
            require_once __DIR__ . '/../includes/gateways/whatsapp.php';
            $gateway = new WhatsApp_Gateway($account['instance_id'], $account['token']);
            $count = 0;
            foreach ($contacts as $contact) {
                $number = $contact['phone'];
                $personalizedMsg = $msgTemplate;
                foreach ($contact['data'] as $key => $val) $personalizedMsg = str_replace('##' . ucfirst($key) . '##', $val, $personalizedMsg);
                
                $msgId = DB::insert("INSERT INTO whatsapp_messages (user_id, account_id, recipient, message, media_url, status) VALUES (?, ?, ?, ?, ?, 'queued')", [$uid, $account['id'], $number, $personalizedMsg, $mediaUrl]);
                if ($msgId) {
                    $res = $gateway->sendMessage($number, $personalizedMsg, $mediaUrl);
                    if ($res['success']) {
                        DB::execute("UPDATE whatsapp_messages SET status = 'sent', external_id = ? WHERE id = ?", [$res['message_id'], $msgId]);
                        $count++;
                    } else {
                        DB::execute("UPDATE whatsapp_messages SET status = 'failed' WHERE id = ?", [$msgId]);
                    }
                }
            }
            if ($count > 0) { flash_set('success', "Campaign launched! $count messages sent."); redirect('whatsapp-logs.php'); }
            else { flash_set('danger', 'Failed to send messages.'); }
        }
    }
}
?>

<div class="page-header">
  <div><h1>Bulk WhatsApp</h1><div class="subtitle">Broadcast high-impact personalized messages</div></div>
</div>

<form method="POST" id="whatsappBulkForm" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="csv_data" id="csvDataInput">
    <input type="hidden" name="send_whatsapp" value="1">

    <div style="display:grid; grid-template-columns: 1fr 380px; gap:24px;">
        <div class="card">
            <div class="card-header">Compose Campaign</div>
            <div class="card-body">
                <textarea name="recipients" id="recipientsArea" class="form-control mb-16" rows="6" placeholder="Numbers (254...)"></textarea>
                <textarea name="message" id="whatsappMsg" class="form-control mb-16" rows="6" placeholder="Your message..." required></textarea>
                <input type="url" name="media_url" class="form-control" placeholder="Media URL (Optional)">
            </div>
        </div>
        <div class="card">
            <div class="card-header">Status</div>
            <div class="card-body">
                <button type="submit" class="btn btn-primary btn-full" <?= !$account ? 'disabled' : '' ?>>Launch Campaign</button>
            </div>
        </div>
    </div>
</form>

<?php include __DIR__ . '/../includes/layout-footer.php'; ?>
