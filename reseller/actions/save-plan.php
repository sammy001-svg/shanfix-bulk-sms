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
                // WHERE owner_id = ? enforces ownership — cannot edit another reseller's plan
                $affected = DB::execute(
                    "UPDATE pricing_plans SET name = ?, units = ?, price = ?, currency = ?, is_popular = ? WHERE id = ? AND owner_id = ?",
                    [$name, $units, $price, $currency, $popular, $id, $reseller_id]
                );
                flash_set(
                    $affected > 0 ? 'success' : 'danger',
                    $affected > 0 ? 'Pricing plan updated successfully!' : 'Failed to update plan. You may not have permission.'
                );
            } else {
                DB::execute(
                    "INSERT INTO pricing_plans (name, units, price, currency, is_active, is_popular, owner_id, created_at) VALUES (?, ?, ?, ?, 1, ?, ?, NOW())",
                    [$name, $units, $price, $currency, $popular, $reseller_id]
                );
                flash_set('success', 'New pricing plan created successfully!');
            }
        } catch (Exception $e) {
            flash_set('danger', 'Database error: ' . $e->getMessage());
        }
    } else {
        flash_set('danger', $price <= 0 ? 'Price must be greater than zero.' : 'Please fill in all required fields.');
    }

    redirect('/reseller/pricing.php');
}
