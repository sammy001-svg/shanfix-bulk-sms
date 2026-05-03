<?php
require 'includes/db.php';
$res = DB::query("DESCRIBE pricing_plans");
foreach ($res as $row) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
