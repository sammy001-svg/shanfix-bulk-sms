<?php
require_once 'includes/db.php';
$resellers = DB::query("SELECT name, email, role, status FROM users WHERE role = 'reseller'");
echo json_encode($resellers, JSON_PRETTY_PRINT);
