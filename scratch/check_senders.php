<?php
require 'includes/db.php';
$res = DB::query('SELECT sender_id FROM sender_ids');
foreach($res as $r) {
    echo $r['sender_id'] . ' [HEX: ' . bin2hex($r['sender_id']) . "]\n";
}
