<?php
require_once 'includes/db.php';
$create = DB::queryOne("SHOW CREATE TABLE notifications")['Create Table'];
echo $create;
