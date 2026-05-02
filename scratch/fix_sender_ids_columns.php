<?php
require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = DB::getInstance();
    $pdo->exec("ALTER TABLE sender_ids ADD COLUMN IF NOT EXISTS application_letter varchar(255) DEFAULT NULL, ADD COLUMN IF NOT EXISTS registration_cert varchar(255) DEFAULT NULL");
    echo "SUCCESS: Added application_letter and registration_cert to sender_ids table\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
