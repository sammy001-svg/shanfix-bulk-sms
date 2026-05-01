<?php
require_once __DIR__ . '/../includes/db.php';
$res = DB::execute("UPDATE system_settings SET value = ? WHERE `key` = ?", ['/assets/images/shanfix-logo.png', 'site_logo']);
if ($res) {
    echo "Logo path updated successfully in database.\n";
} else {
    echo "Failed to update or already set.\n";
}
