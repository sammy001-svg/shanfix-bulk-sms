<?php
/**
 * Admin Action: Bulk Allocate Units - Shanfix Technology
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/units.php');
}

if (!csrf_verify()) {
    flash_set('danger', 'Invalid security token.');
    redirect('/admin/units.php');
}

$adminUser    = current_user();
$targetGroup  = sanitize($_POST['target_group'] ?? '');
$unitsPerUser = (float)($_POST['units'] ?? 0);
$note         = sanitize($_POST['note'] ?? 'Bulk allocation by Admin');

if ($unitsPerUser <= 0) {
    flash_set('danger', 'Invalid unit amount.');
    redirect('/admin/units.php');
}

$roleClause = match ($targetGroup) {
    'resellers' => "role = 'reseller'",
    'clients'   => "role = 'client'",
    'all'       => "role IN ('client', 'reseller')",
    default     => null,
};

if (!$roleClause) {
    flash_set('danger', 'Invalid target group.');
    redirect('/admin/units.php');
}

$targetUsers = DB::query("SELECT id FROM users WHERE $roleClause AND status = 'active'");
$userCount   = count($targetUsers);

if ($userCount === 0) {
    flash_set('warning', 'No active users found in the selected group.');
    redirect('/admin/units.php');
}

$totalNeeded = $userCount * $unitsPerUser;

// Quick pre-check for better UX (fast fail before opening a transaction)
$adminBalance = (float)DB::queryValue("SELECT sms_units FROM users WHERE id = ?", [$adminUser['id']]);
if ($adminBalance < $totalNeeded) {
    flash_set('danger', "Insufficient units. Need " . number_format($totalNeeded, 2) . ", have " . number_format($adminBalance, 2) . ".");
    redirect('/admin/units.php');
}

try {
    DB::beginTransaction();

    // Atomic deduction — eliminates the TOCTOU race between the pre-check and the update.
    // Returns 0 rows if the balance was consumed by a concurrent request.
    $deducted = DB::execute(
        "UPDATE users SET sms_units = sms_units - ? WHERE id = ? AND sms_units >= ?",
        [$totalNeeded, $adminUser['id'], $totalNeeded]
    );

    if (!$deducted) {
        DB::rollback();
        $actual = (float)DB::queryValue("SELECT sms_units FROM users WHERE id = ?", [$adminUser['id']]);
        flash_set('danger', "Insufficient units. Need " . number_format($totalNeeded, 2) . ", have " . number_format($actual, 2) . ".");
        redirect('/admin/units.php');
    }

    // Credit users and log in chunks of 500 to avoid oversized IN-clause queries
    foreach (array_chunk($targetUsers, 500) as $chunk) {
        $ids            = array_column($chunk, 'id');
        $inPlaceholders = implode(',', array_fill(0, count($ids), '?'));

        DB::execute(
            "UPDATE users SET sms_units = sms_units + ? WHERE id IN ($inPlaceholders)",
            array_merge([$unitsPerUser], $ids)
        );

        // Log to unit_allocations — same table used by single allocate for a consistent audit trail
        $logRows   = [];
        $logParams = [];
        foreach ($chunk as $u) {
            $logRows[]   = '(?, ?, ?, ?, NOW())';
            array_push($logParams, $adminUser['id'], $u['id'], $unitsPerUser, "Bulk credit: $note");
        }
        DB::execute(
            "INSERT INTO unit_allocations (from_user, to_user, units, note, created_at) VALUES " . implode(',', $logRows),
            $logParams
        );
    }

    DB::commit();

    // Single broadcast notification instead of N per-user inserts
    notify(null, 'Units Credited',
        number_format($unitsPerUser) . " SMS units have been added to your account (bulk allocation).",
        'success'
    );

    flash_set('success', "Allocated " . number_format($unitsPerUser) . " units to each of $userCount users (total: " . number_format($totalNeeded) . ").");

} catch (Exception $e) {
    DB::rollback();
    error_log("Bulk allocate failed: " . $e->getMessage());
    flash_set('danger', 'Allocation failed — no units were transferred.');
}

redirect('/admin/units.php');
