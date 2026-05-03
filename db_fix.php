<?php
/**
 * DB Fix Script for Shanfix Bulk SMS
 * Run this by visiting: http://localhost/db_fix.php
 */
require_once __DIR__ . '/includes/db.php';

echo "<h2>Shanfix Database Migration Tool</h2>";

$migrations = [
    "ALTER TABLE `ussd_requests` ADD `user_id` INT(11) NOT NULL AFTER `id`" => "Adding user_id to ussd_requests",
    "ALTER TABLE `ussd_requests` ADD `code_id` INT(11) NOT NULL AFTER `user_id`" => "Adding code_id to ussd_requests",
    "ALTER TABLE `ussd_sessions` ADD `user_id` INT(11) NOT NULL AFTER `id`" => "Adding user_id to ussd_sessions",
    "ALTER TABLE `ussd_sessions` ADD `code_id` INT(11) NOT NULL AFTER `user_id`" => "Adding code_id to ussd_sessions",
    "ALTER TABLE `users` ADD `ussd_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00" => "Adding ussd_balance to users",
    "ALTER TABLE `users` ADD `whatsapp_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00" => "Adding whatsapp_balance to users",
    "ALTER TABLE `users` ADD `api_client_id` VARCHAR(50) DEFAULT NULL" => "Adding api_client_id to users",
    "ALTER TABLE `users` ADD `api_key` VARCHAR(100) DEFAULT NULL" => "Adding api_key to users"
];

foreach ($migrations as $sql => $desc) {
    try {
        DB::execute($sql);
        echo "<p style='color:green'>[SUCCESS] $desc</p>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "<p style='color:blue'>[ALREADY EXISTS] $desc</p>";
        } else {
            echo "<p style='color:red'>[ERROR] $desc: " . $e->getMessage() . "</p>";
        }
    }
}

echo "<h3>Migration complete! You can now delete this file and refresh your USSD Analytics page.</h3>";
