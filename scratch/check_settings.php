<?php
require_once 'includes/db.php';
$settings = DB::query("SELECT * FROM system_settings WHERE `key` LIKE 'kk_%'");
echo json_encode($settings, JSON_PRETTY_PRINT);
