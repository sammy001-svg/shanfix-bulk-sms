<?php
require 'includes/db.php';
$res = DB::query("DESCRIBE purchases");
foreach ($res as $row) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
