<?php
/**
 * Reseller Action: Delete Plan - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('reseller');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('danger', 'Invalid security token.');
        redirect('/reseller/pricing.php');
    }

    $user = current_user();
    $reseller_id = $user['id'];
    $id = (int)($_POST['id'] ?? 0);

    if ($id) {
        try {
            // Ensure the reseller owns this plan
            $sql = "DELETE FROM pricing_plans WHERE id = ? AND owner_id = ?";
            $affected = DB::execute($sql, [$id, $reseller_id]);
            
            if ($affected > 0) {
                flash_set('success', 'Pricing plan deleted successfully.');
            } else {
                flash_set('danger', 'Failed to delete plan. It may not exist or you do not have permission.');
            }
        } catch (Exception $e) {
            flash_set('danger', 'Error: ' . $e->getMessage());
        }
    }

    redirect('/reseller/pricing.php');
}
