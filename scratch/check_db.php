<?php
$config = include 'config.php';
// config.php uses defines, not return array
require_once 'config.php';

try {
    $dsn = "mysql:host=" . DB_HOST;
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '" . DB_NAME . "'");
    $exists = $stmt->fetch();
    if ($exists) {
        echo "DATABASE_EXISTS";
    } else {
        echo "DATABASE_MISSING";
    }
} catch (PDOException $e) {
    echo "CONNECTION_ERROR: " . $e->getMessage();
}
