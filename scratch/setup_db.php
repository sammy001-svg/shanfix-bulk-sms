<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1', 'root', '');
    echo "Connected successfully to MySQL server\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS bulk_sms_system");
    echo "Database bulk_sms_system created or already exists\n";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
