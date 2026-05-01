<?php
require_once __DIR__ . '/includes/db.php';
header('Content-Type: text/plain');

try {
    $dbName = DB::queryOne("SELECT DATABASE()")['DATABASE()'];
    echo "Current Database: $dbName\n";
    
    $latest = DB::query("SELECT id, user_id, status FROM purchases ORDER BY id DESC LIMIT 5");
    echo "Latest Purchases:\n";
    foreach ($latest as $l) {
        echo "#{$l['id']} - User {$l['user_id']} - {$l['status']}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
