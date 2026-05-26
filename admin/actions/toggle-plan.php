<?php
/**
 * Admin Action: Toggle Plan Visibility - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/pricing.php');
}

if (!csrf_verify()) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid security token.'];
    redirect('/admin/pricing.php');
}

$id = (int)($_POST['id'] ?? 0);

if ($id) {
    DB::execute("UPDATE pricing_plans SET is_active = 1 - is_active WHERE id = ?", [$id]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Plan status toggled successfully.'];
}
redirect(safe_referer('/admin/pricing.php'));
