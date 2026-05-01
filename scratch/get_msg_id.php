<?php
require_once 'config.php';
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $stmt = $pdo->query("SELECT id FROM messages WHERE user_id = 3 LIMIT 1");
    $msg = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($msg);
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage();
}
