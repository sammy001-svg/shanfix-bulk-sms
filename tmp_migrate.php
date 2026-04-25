<?php
require_once __DIR__ . '/includes/db.php';
try {
    echo "Updating users table...\n";
    DB::execute("ALTER TABLE users ADD COLUMN custom_unit_price DECIMAL(10,4) DEFAULT NULL AFTER sms_units");
    echo "Updating pricing_plans table...\n";
    DB::execute("ALTER TABLE pricing_plans ADD COLUMN owner_id INT UNSIGNED DEFAULT NULL AFTER id");
    echo "Adding index to owner_id...\n";
    DB::execute("ALTER TABLE pricing_plans ADD INDEX (owner_id)");
    echo "Success!\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "Columns already exist. Continuing...\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
