<?php
require_once 'includes/db.php';
$tables = DB::query('SHOW TABLES');
foreach($tables as $t) {
    $tableName = array_values($t)[0];
    echo "\nTable: $tableName\n";
    $create = DB::queryOne("SHOW CREATE TABLE `$tableName`")['Create Table'];
    echo $create . "\n";
}
