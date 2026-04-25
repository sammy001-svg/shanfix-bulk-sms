<?php
/**
 * Admin Action: Delete Pricing Plan - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        DB::execute("DELETE FROM pricing_plans WHERE id = ?", [$id]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Pricing plan deleted.'];
    }
    redirect('/admin/pricing.php');
}
