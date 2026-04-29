<?php
require_once __DIR__ . '/includes/db.php';
$res = DB::query("SELECT * FROM system_settings WHERE `key` LIKE 'kk_%'");
foreach($res as $r) {
    echo $r['key'] . " (Len: " . strlen($r['value']) . "): " . $r['value'] . "\n";
}
