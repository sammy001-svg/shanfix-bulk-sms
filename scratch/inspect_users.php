<?php
require_once 'includes/db.php';
$create = DB::queryOne("SHOW CREATE TABLE users")['Create Table'];
echo $create;
