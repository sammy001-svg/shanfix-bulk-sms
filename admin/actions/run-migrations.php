<?php
/**
 * Admin Action: apply outstanding additive database updates.
 *
 * Only the changes listed in Schema::required() are ever run — ADD COLUMN,
 * ADD INDEX and INSERT IGNORE of settings keys. Nothing drops, renames or
 * rewrites data, each change is re-checked before it runs, and the whole thing
 * is safe to repeat.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers/schema.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    flash_set('danger', 'Invalid request.');
    redirect('/admin/database-updates.php');
}

$res = Schema::apply();

// The sidebar badge is cached in the session for 5 minutes; expire it so the
// count reflects what just happened instead of lagging behind.
unset($_SESSION['_admin_badge_ts']);

$applied = count($res['applied']);
$failed  = count($res['failed']);

if ($failed > 0) {
    $first = array_key_first($res['failed']);
    flash_set('danger', sprintf(
        '%d update(s) applied, %d failed. First failure — %s: %s',
        $applied, $failed, $first, $res['failed'][$first]
    ));
} elseif ($applied > 0) {
    flash_set('success', sprintf(
        '%d database update(s) applied successfully: %s',
        $applied, implode(', ', $res['applied'])
    ));
} else {
    flash_set('info', 'Everything is already up to date — no changes were needed.');
}

redirect('/admin/database-updates.php');
