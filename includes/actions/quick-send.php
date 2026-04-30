<?php
/**
 * Action: Quick Send SMS - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/actions/sms.php';

$user = current_user();
if (!$user) {
    header('Location: /login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senderId    = sanitize($_POST['sender_id'] ?? 'SHANFIX');
    $message     = sanitize($_POST['message'] ?? '');
    $scheduledAt = $_POST['scheduled_at'] ?? null;
    $groupId     = (int)($_POST['group_id'] ?? 0);
    $rawNumbers  = sanitize($_POST['numbers'] ?? $_POST['recipient'] ?? '');

    if (!$message) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Message content is required.'];
        redirect($_SERVER['HTTP_REFERER']);
    }

    // 1. Identify all recipients
    $recipients = [];
    if ($groupId) {
        $contacts = DB::query("SELECT phone FROM contacts WHERE group_id = ? AND user_id = ?", [$groupId, $user['id']]);
        foreach ($contacts as $c) $recipients[] = $c['phone'];
    }
    if ($rawNumbers) {
        $nums = preg_split('/[\n,;]+/', $rawNumbers);
        foreach ($nums as $n) {
            $n = trim($n);
            if ($n) $recipients[] = $n;
        }
    }

    $recipients = array_unique($recipients);
    $count = count($recipients);

    if ($count === 0) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'No valid recipients found.'];
        redirect($_SERVER['HTTP_REFERER']);
    }

    // 2. Decide: Single direct send OR Campaign
    if ($count === 1 && !$scheduledAt) {
        // Single immediate send
        $result = SMS::send($user['id'], $recipients[0], $message, $senderId);
        if ($result['success']) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Message sent successfully!"];
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => "Failed to send: " . $result['error']];
        }
    } else {
        // Create a campaign for tracking/scheduling
        $status = $scheduledAt ? 'scheduled' : 'queued';
        $campaignId = DB::insert("INSERT INTO campaigns (user_id, sender_id, name, message, group_id, recipients, total_count, status, scheduled_at, created_at) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())", 
                                 [$user['id'], $senderId, "Quick Broadcast " . date('Y-m-d H:i'), $message, $groupId ?: null, implode(',', $recipients), $count, $status, $scheduledAt ?: null]);

        if ($campaignId) {
            if (!$scheduledAt) {
                // Trigger immediate processing for non-scheduled campaign
                SMS::processCampaign($campaignId);
                $_SESSION['flash'] = ['type' => 'success', 'message' => "Broadcast started! $count messages are being processed."];
            } else {
                $_SESSION['flash'] = ['type' => 'success', 'message' => "Broadcast scheduled for " . date('d M Y H:i', strtotime($scheduledAt))];
            }
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => "Failed to initiate broadcast."];
        }
    }

    redirect($_SERVER['HTTP_REFERER'] ?? '/');
}
