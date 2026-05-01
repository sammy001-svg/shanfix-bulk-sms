<?php
require_once 'config.php';
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->exec("ALTER TABLE reseller_settings ADD COLUMN sidebar_color VARCHAR(10) DEFAULT '#0e1726' AFTER primary_color");
    echo "SUCCESS: Added sidebar_color column";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage();
}
