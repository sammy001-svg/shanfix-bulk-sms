<?php
/**
 * Reseller Action: Edit Client - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('reseller');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid security token.'];
        redirect('/reseller/clients.php');
    }

    $id = (int)($_POST['id'] ?? 0);
    $customPrice = (float)($_POST['custom_unit_price'] ?? 1.00);
    $resellerId = $_SESSION['user_id'];

    // Verify the client belongs to this reseller
    $client = DB::queryOne("SELECT id FROM users WHERE id = ? AND parent_id = ? AND role = 'client'", [$id, $resellerId]);
    
    if (!$client) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Client not found or access denied.'];
        redirect('/reseller/clients.php');
    }

    $success = DB::execute("UPDATE users SET custom_unit_price = ? WHERE id = ?", [$customPrice, $id]);

    if ($success !== false) {
        $_SESSION['flash'] = ['type' => 'success', 'message' => "Client rate updated successfully!"];
    } else {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to update client.'];
    }

    redirect('/reseller/clients.php');
}
