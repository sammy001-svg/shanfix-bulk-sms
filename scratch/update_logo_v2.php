<?php
require_once __DIR__ . '/../includes/db.php';
$res = DB::execute("UPDATE system_settings SET value = ? WHERE `key` = ?", ['/assets/images/logo.png', 'site_logo']);
if ($res) {
    echo "Logo path updated to /assets/images/logo.png successfully.\n";
} else {
    echo "Failed to update or already set.\n";
}
