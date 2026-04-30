<?php
require_once 'includes/db.php';
$onfon = DB::query("SELECT * FROM system_settings WHERE `key` LIKE 'onfon_%'");
print_r($onfon);
