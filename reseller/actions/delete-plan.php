<?php
/**
 * Reseller Action: Delete Plan - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('reseller');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reseller_id = $_SESSION['user_id'];
    $id = (int)($_POST['id'] ?? 0);

    if ($id) {
        // Ensure the reseller owns this plan
        $sql = "DELETE FROM pricing_plans WHERE id = ? AND owner_id = ?";
        $success = DB::execute($sql, [$id, $reseller_id]);
        if ($success) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Pricing plan deleted successfully.'];
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to delete plan. You may not have permission.'];
        }
    }

    redirect('/reseller/pricing.php');
}
