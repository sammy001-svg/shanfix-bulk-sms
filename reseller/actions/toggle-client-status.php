<?php
require_once __DIR__ . '/../../includes/init.php';
require_role('reseller');
csrf_verify();

$uid = $user['id'];
$id  = (int)($_POST['id'] ?? 0);

$client = DB::queryOne(
    "SELECT id, status, name FROM users WHERE id = ? AND parent_id = ? AND role = 'client'",
    [$id, $uid]
);
if (!$client) {
    flash_set('danger', 'Client not found.');
    redirect('/reseller/clients.php');
}

$newStatus = $client['status'] === 'active' ? 'suspended' : 'active';
DB::execute("UPDATE users SET status = ? WHERE id = ?", [$newStatus, $id]);

$label = $newStatus === 'active' ? 'activated' : 'suspended';
flash_set('success', "Client " . htmlspecialchars($client['name']) . " has been $label.");

$ref = safe_referer('/reseller/clients.php');
redirect($ref);
