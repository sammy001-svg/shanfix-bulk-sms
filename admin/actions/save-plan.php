<?php
/**
 * Admin Action: Manage Pricing Plan - Shanfix Technology
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

$id       = (int)($_POST['id'] ?? 0);
$name     = sanitize($_POST['name'] ?? '');
$units    = (int)($_POST['units'] ?? 0);
$price    = (float)($_POST['price'] ?? 0);
$currency = sanitize($_POST['currency'] ?? 'KES');
$popular  = isset($_POST['is_popular']) ? 1 : 0;

if ($name && $units > 0 && $price >= 0) {
    try {
        if ($id) {
            DB::execute(
                "UPDATE pricing_plans SET name = ?, units = ?, price = ?, currency = ?, is_popular = ? WHERE id = ?",
                [$name, $units, $price, $currency, $popular, $id]
            );
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Pricing plan updated successfully!'];
        } else {
            DB::execute(
                "INSERT INTO pricing_plans (name, units, price, currency, is_active, is_popular, created_at) VALUES (?, ?, ?, ?, 1, ?, NOW())",
                [$name, $units, $price, $currency, $popular]
            );
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'New pricing plan created successfully!'];
        }
    } catch (Exception $e) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Database error: ' . $e->getMessage()];
    }
} else {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'All required fields must be filled correctly (Units must be > 0, Price must be non-negative).'];
}

redirect('/admin/pricing.php');
