<?php
/**
 * Reseller Action: Save Plan - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('reseller');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('danger', 'Security token mismatch. Please try again.');
        redirect('/reseller/pricing.php');
    }

    $reseller_id = current_user()['id'];
    $id       = (int)($_POST['id'] ?? 0);
    $name     = sanitize($_POST['name'] ?? '');
    $units    = (int)($_POST['units'] ?? 0);
    $price    = (float)($_POST['price'] ?? 0);
    $currency = sanitize($_POST['currency'] ?? 'KES');
    $popular  = isset($_POST['is_popular']) ? 1 : 0;

    if ($name && $units > 0 && $price > 0) {
        try {
            if ($id) {
                // Ensure the reseller owns this plan
                $sql = "UPDATE pricing_plans SET name = ?, units = ?, price = ?, currency = ?, is_popular = ? WHERE id = ? AND owner_id = ?";
                $success = DB::execute($sql, [$name, $units, $price, $currency, $popular, $id, $reseller_id]);
                if ($success) {
                    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Pricing plan updated successfully!'];
                } else {
                    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to update plan. You may not have permission.'];
                }
            } else {
                $sql = "INSERT INTO pricing_plans (name, units, price, currency, is_active, is_popular, owner_id, created_at) VALUES (?, ?, ?, ?, 1, ?, ?, NOW())";
                DB::execute($sql, [$name, $units, $price, $currency, $popular, $reseller_id]);
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'New pricing plan created successfully!'];
            }
        } catch (Exception $e) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Database error: ' . $e->getMessage()];
        }
    } else {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => $price <= 0 ? 'Price must be greater than zero.' : 'Please fill in all required fields.'];
    }

    redirect('/reseller/pricing.php');
}
