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
        $nums = explode(',', $rawNumbers);
        foreach ($nums as $n) {
            $n = trim($n);
            if ($n) $recipients[] = $n;
        }
    }
    // (CSV handling would be added here or in a separate step)

    $count = count(array_unique($recipients));
    if ($count === 0) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'No recipients found.'];
        redirect($_SERVER['HTTP_REFERER']);
    }

    $id = (int)($_POST['id'] ?? 0);
    $status = $scheduledAt ? 'scheduled' : 'running';

    if ($id) {
        // Update existing draft
        DB::execute("UPDATE campaigns SET sender_id = ?, name = ?, message = ?, recipients_count = ?, status = ?, scheduled_at = ? WHERE id = ? AND user_id = ?", 
                    [$senderId, $name, $message, $count, $status, $scheduledAt ?: null, $id, $user['id']]);
    } else {
        // Create new
        $id = DB::insert("INSERT INTO campaigns (user_id, sender_id, name, message, recipients_count, status, scheduled_at, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())", 
                         [$user['id'], $senderId, $name, $message, $count, $status, $scheduledAt ?: null]);
    }

    if ($id) {
        if ($status === 'running') {
            // Trigger actual sending logic (background or loop)
            foreach ($recipients as $to) {
                SMS::send($user['id'], $to, $message, $senderId);
            }
            DB::execute("UPDATE campaigns SET status = 'completed' WHERE id = ?", [$id]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Campaign launched! $count messages processed."];
        } else {
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Campaign " . ($id ? "updated and " : "") . "scheduled for $scheduledAt."];
        }
    }

    redirect('/client/campaigns.php');
}
