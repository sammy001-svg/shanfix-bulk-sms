<?php
require_once 'config.php';
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->exec("UPDATE system_settings SET `key` = 'payhero_channel_id' WHERE `key` = 'payhero_api_channel_id'");
    echo "SUCCESS: Corrected Payhero key";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage();
}
