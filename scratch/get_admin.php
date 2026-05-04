<?php
require_once 'includes/db.php';
$admin = DB::queryOne("SELECT email FROM users WHERE role='admin' LIMIT 1");
echo $admin['email'] ?? 'Not found';
