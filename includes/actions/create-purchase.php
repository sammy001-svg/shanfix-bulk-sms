<?php
/**
 * Action: Create Purchase Request - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/actions/purchases.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid security token. Please try again.'];
        redirect($_SERVER['HTTP_REFERER']);
    }

    $user = current_user();
    if (!$user) {
        redirect('/login.php');
    }
    
    $result = Purchase::create($user['id'], $_POST);

    if ($result['success']) {
        if (!empty($result['manual'])) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Payment reference submitted! Your reseller will verify and approve your units shortly.'];
        } else {
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'STK Push initiated! Please enter your M-Pesa PIN on your phone. Units will be added automatically once confirmed.'];
        }
    } else {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Transaction failed: ' . $result['error']];
    }

    redirect($_SERVER['HTTP_REFERER'] ?? '/client/purchases.php');
}
