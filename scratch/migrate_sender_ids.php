<?php
require_once 'config.php';
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->exec("ALTER TABLE sender_ids ADD COLUMN application_letter varchar(255) DEFAULT NULL, ADD COLUMN registration_cert varchar(255) DEFAULT NULL");
    echo "SUCCESS: Added columns to sender_ids table";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage();
}
