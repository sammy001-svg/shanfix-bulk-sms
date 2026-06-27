<?php
/**
 * Reseller Action: Delete Plan - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('reseller');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('danger', 'Invalid security token. Please refresh the page and try again.');
        redirect('/reseller/pricing.php');
    }

    $user        = current_user();
    $resellerId  = $user['id'];
    $id          = (int)($_POST['id'] ?? 0);

    if ($id) {
        try {
            $affected = DB::execute(
                "DELETE FROM pricing_plans WHERE id = ? AND owner_id = ?",
                [$id, $resellerId]
            );

            if ($affected > 0) {
                flash_set('success', 'Pricing plan deleted successfully.');
            } else {
                flash_set('danger', 'Plan not found or you do not have permission to delete it.');
            }
        } catch (Exception $e) {
            error_log("delete-plan failed for id=$id reseller=$resellerId: " . $e->getMessage());
            flash_set('danger', 'An error occurred while deleting the plan. Please try again.');
        }
    }

    redirect('/reseller/pricing.php');
}
