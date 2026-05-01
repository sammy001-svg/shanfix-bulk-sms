<?php
require_once __DIR__ . '/../includes/db.php';
$settings = DB::query("SELECT * FROM system_settings");
foreach ($settings as $s) {
    echo "{$s['key']}: {$s['value']}\n";
}
