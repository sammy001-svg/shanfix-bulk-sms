<?php
/**
 * Action: Create & Launch Campaign - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/actions/sms.php';

$user = current_user();
if (!$user) {
    header('Location: /login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = sanitize($_POST['name'] ?? '');
    $senderId    = sanitize($_POST['sender_id'] ?? 'SHANFIX');
    $message     = sanitize($_POST['message'] ?? '');
    $scheduledAt = $_POST['scheduled_at'] ?? null;
    $groupId     = (int)($_POST['group_id'] ?? 0);
    $rawNumbers  = sanitize($_POST['numbers'] ?? '');
    
    // 1. Identify recipients
    $recipients = [];
    if ($groupId) {
        $contacts = DB::query("SELECT phone FROM contacts WHERE group_id = ? AND user_id = ?", [$groupId, $user['id']]);
        foreach ($contacts as $c) $recipients[] = $c['phone'];
    }
    if ($rawNumbers) {
        $nums = preg_split('/[\n,;]+/', $rawNumbers);
        foreach ($nums as $n) {
            $n = preg_replace('/[^0-9]/', '', $n); // Remove non-numeric
            if (!$n) continue;

            // Smart Kenyan Normalization
            if (strlen($n) === 9 && ($n[0] === '7' || $n[0] === '1')) { // 711222333 -> 254711222333
                $n = '254' . $n;
            } elseif (strlen($n) === 10 && $n[0] === '0') { // 0711222333 -> 254711222333
                $n = '254' . substr($n, 1);
            }
            
            // Ensure 254 prefix and + sign
            if (strpos($n, '254') === 0) {
                $n = '+' . $n;
            } elseif (strpos($n, '+') !== 0) {
                $n = '+' . $n;
            }
            
            $recipients[] = $n;
        }
    }
    // (CSV handling would be added here or in a separate step)

    $count = count(array_unique($recipients));
    if ($count === 0) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'No recipients found.'];
        redirect($_SERVER['HTTP_REFERER']);
    }

    $id = (int)($_POST['id'] ?? 0);
    $status = $scheduledAt ? 'scheduled' : 'queued';

    if ($id) {
        // Update existing draft
        DB::execute("UPDATE campaigns SET sender_id = ?, name = ?, message = ?, group_id = ?, recipients = ?, total_count = ?, status = ?, scheduled_at = ? WHERE id = ? AND user_id = ?", 
                    [$senderId, $name, $message, $groupId ?: null, implode(',', $recipients), $count, $status, $scheduledAt ?: null, $id, $user['id']]);
    } else {
        // Create new
        $id = DB::insert("INSERT INTO campaigns (user_id, sender_id, name, message, group_id, recipients, total_count, status, scheduled_at, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())", 
                         [$user['id'], $senderId, $name, $message, $groupId ?: null, implode(',', $recipients), $count, $status, $scheduledAt ?: null]);
    }

    if ($id) {
        if ($status === 'queued') {
            // Process immediately
            SMS::processCampaign($id);
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Campaign launched! $count messages are being processed."];
        } else {
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Campaign scheduled for " . date('d M Y H:i', strtotime($scheduledAt))];
        }
    }

    redirect($_SESSION['user']['role'] === 'reseller' ? '/reseller/campaigns.php' : '/client/campaigns.php');
}
