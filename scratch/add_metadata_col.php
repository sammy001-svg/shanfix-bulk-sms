<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=bulk_sms_system', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("ALTER TABLE contacts ADD COLUMN metadata JSON NULL AFTER email");
    echo "Column 'metadata' added successfully to 'contacts' table.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
