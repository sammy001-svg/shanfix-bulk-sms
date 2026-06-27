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

    $id          = (int)($_POST['id'] ?? 0);
    $customPrice = (float)($_POST['custom_unit_price'] ?? 1.00);
    $resellerId  = current_user()['id'];

    // Verify the client belongs to this reseller
    $client = DB::queryOne("SELECT id FROM users WHERE id = ? AND parent_id = ? AND role = 'client'", [$id, $resellerId]);

    if (!$client) {
        flash_set('danger', 'Client not found or access denied.');
        redirect('/reseller/clients.php');
    }

    DB::execute("UPDATE users SET custom_unit_price = ? WHERE id = ?", [$customPrice, $id]);
    flash_set('success', 'Client rate updated successfully!');
    redirect('/reseller/clients.php');
}
