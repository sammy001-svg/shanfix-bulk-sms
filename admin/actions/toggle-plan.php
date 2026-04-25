<?php
/**
 * Admin Action: Toggle Plan Visibility - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $active = (int)($_POST['is_active'] ?? 0);

    if ($id) {
        DB::execute("UPDATE pricing_plans SET is_active = 1 - is_active WHERE id = ?", [$id]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Plan status toggled successfully.'];
    }
    redirect($_SERVER['HTTP_REFERER']);
}
