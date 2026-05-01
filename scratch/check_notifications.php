<?php
require_once 'includes/db.php';
$cols = DB::query("DESCRIBE notifications");
foreach($cols as $c) {
    echo $c['Field'] . " (" . $c['Type'] . ")\n";
}
