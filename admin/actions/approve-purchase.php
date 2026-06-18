<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    flash_set('danger', 'Invalid request.');
    redirect('/admin/finances.php');
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    flash_set('danger', 'Purchase ID missing.');
    redirect('/admin/finances.php');
}

$purchase = DB::queryOne(
    "SELECT p.*, u.name as user_name FROM purchases p JOIN users u ON p.user_id = u.id WHERE p.id = ?",
    [$id]
);

if (!$purchase) {
    flash_set('danger', 'Purchase not found.');
    redirect(safe_referer('/admin/finances.php'));
}

if ($purchase['status'] !== 'pending') {
    flash_set('warning', 'Purchase #' . $id . ' is already ' . $purchase['status'] . '.');
    redirect(safe_referer('/admin/finances.php'));
}

// Reuse the shared Purchase::complete() logic which handles unit crediting and deduction
require_once __DIR__ . '/../../includes/actions/purchases.php';
$ok = Purchase::complete($id);

if ($ok) {
    flash_set('success', 'Purchase #' . $id . ' approved — ' . number_format($purchase['units']) . ' units credited to ' . htmlspecialchars($purchase['user_name']) . '.');
} else {
    flash_set('danger', 'Could not approve purchase #' . $id . '. Parent account may have insufficient units.');
}

redirect(safe_referer('/admin/finances.php'));
