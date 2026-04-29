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
    $to       = sanitize($_POST['recipient'] ?? $_POST['to'] ?? '');
    $message  = sanitize($_POST['message'] ?? '');
    $senderId = sanitize($_POST['sender_id'] ?? 'SHANFIX');

    if (!$to || !$message) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Recipient and message are required.'];
        redirect($_SERVER['HTTP_REFERER']);
    }

    $result = SMS::send($user['id'], $to, $message, $senderId);

    if ($result['success']) {
        $_SESSION['flash'] = [
            'type' => 'success', 
            'message' => 'Message sent successfully! Cost: ' . number_format($result['cost'], 2) . ' units.'
        ];
    } else {
        $_SESSION['flash'] = [
            'type' => 'danger', 
            'message' => 'Failed to send: ' . ($result['error'] ?? 'Unknown system error')
        ];
    }

    $redirectUrl = $_SERVER['HTTP_REFERER'] ?? '/';
    header("Location: $redirectUrl");
    exit;
}
