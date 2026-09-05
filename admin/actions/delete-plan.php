<?php
/**
 * Admin Action: Delete Pricing Plan - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/pricing.php');
}

if (!csrf_verify()) {
    flash_set('danger', 'Invalid security token.');
    redirect('/admin/pricing.php');
}

$id = (int)($_POST['id'] ?? 0);
if ($id) {
    DB::execute("DELETE FROM pricing_plans WHERE id = ?", [$id]);
    flash_set('success', 'Pricing plan deleted.');
}
redirect('/admin/pricing.php');
