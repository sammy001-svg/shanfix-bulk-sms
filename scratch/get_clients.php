<?php
require_once 'includes/db.php';
$url = DB::queryOne("SELECT value FROM system_settings WHERE `key` = 'site_url'");
print_r($url);
