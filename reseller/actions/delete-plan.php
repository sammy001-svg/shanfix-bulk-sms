<?php
/**
 * Reseller Action: Delete Plan - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('reseller');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $logFile = __DIR__ . '/../../tmp/delete_debug.log';
    if (!is_dir(__DIR__ . '/../../tmp')) @mkdir(__DIR__ . '/../../tmp', 0777, true);
    
    $input = json_encode($_POST);
    file_put_contents($logFile, "[".date('Y-m-d H:i:s')."] Delete Attempt: " . $input . PHP_EOL, FILE_APPEND);

    if (!csrf_verify()) {
        file_put_contents($logFile, "[".date('Y-m-d H:i:s')."] CSRF Failed. Session Token: " . ($_SESSION['csrf_token'] ?? 'NULL') . PHP_EOL, FILE_APPEND);
        flash_set('danger', 'Invalid security token. Please refresh the page and try again.');
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
            
            file_put_contents($logFile, "[".date('Y-m-d H:i:s')."] Query Run. ID: $id, Reseller: $reseller_id, Affected: $affected" . PHP_EOL, FILE_APPEND);

            if ($affected > 0) {
                flash_set('success', 'Pricing plan deleted successfully.');
            } else {
                // Check if plan exists at all
                $exists = DB::queryOne("SELECT * FROM pricing_plans WHERE id = ?", [$id]);
                if ($exists) {
                    $reason = "Plan exists but owner is " . ($exists['owner_id'] ?? 'NULL');
                } else {
                    $reason = "Plan ID $id not found in database.";
                }
                file_put_contents($logFile, "[".date('Y-m-d H:i:s')."] Delete Failed: $reason" . PHP_EOL, FILE_APPEND);
                flash_set('danger', 'Failed to delete plan. ' . $reason);
            }
        } catch (Exception $e) {
            file_put_contents($logFile, "[".date('Y-m-d H:i:s')."] Exception: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
            flash_set('danger', 'Error: ' . $e->getMessage());
        }
    }

    redirect('/reseller/pricing.php');
}
