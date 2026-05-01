<?php
require_once 'config.php';
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $stmt = $pdo->query("SELECT id, name, api_client_id, api_key FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($users, JSON_PRETTY_PRINT);
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage();
}
