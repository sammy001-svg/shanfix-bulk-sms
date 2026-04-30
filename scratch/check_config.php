<?php
require 'includes/db.php';
$res = DB::query('SELECT * FROM system_settings');
foreach($res as $r) {
    echo $r['key'] . '=' . $r['value'] . "\n";
}
