<?php
require_once 'config.php';
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $stmt = $pdo->query("SELECT * FROM system_settings WHERE `key` LIKE 'payhero_%'");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage();
}
