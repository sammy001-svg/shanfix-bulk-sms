<?php
require_once __DIR__ . '/includes/db.php';
$db = DB::getInstance();
$stmt = $db->query("SELECT email, role FROM users WHERE role = 'reseller'");
$resellers = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($resellers);
