<?php
require_once 'config.php';
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->exec("ALTER TABLE reseller_settings 
                ADD COLUMN unit_price DECIMAL(10,4) DEFAULT 0.0000 AFTER sidebar_color,
                ADD COLUMN payment_instructions TEXT AFTER unit_price");
    echo "SUCCESS: Added unit_price and payment_instructions columns";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage();
}
