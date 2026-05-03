<?php
/**
 * ROBUST DB Fix & Migration Tool for Shanfix Bulk SMS
 * This script will:
 * 1. Ensure all required tables exist
 * 2. Ensure all missing columns are added
 * 3. Force error reporting to help diagnose blank pages
 */

// Force error reporting to catch issues during migration
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/db.php';

echo "<div style='font-family:sans-serif; max-width:800px; margin:20px auto; padding:20px; border:1px solid #ddd; border-radius:10px; background:#fff;'>";
echo "<h2>🛠️ Shanfix Database Robust Fix Tool</h2>";
echo "<p>Running diagnostics and applying fixes...</p>";

$db = DB::getInstance();

// 1. Create Tables if missing
$tables = [
    "ussd_codes" => "CREATE TABLE IF NOT EXISTS `ussd_codes` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `requested_code` VARCHAR(50) NOT NULL,
        `type` ENUM('dedicated', 'shared') DEFAULT 'dedicated',
        `purpose` TEXT,
        `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        `reject_reason` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB",
    
    "ussd_sessions" => "CREATE TABLE IF NOT EXISTS `ussd_sessions` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `code_id` INT(11) NOT NULL,
        `session_id` VARCHAR(100) NOT NULL,
        `phone` VARCHAR(20) NOT NULL,
        `status` ENUM('active', 'completed', 'timed_out') DEFAULT 'active',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_session_id` (`session_id`)
    ) ENGINE=InnoDB",
    
    "ussd_requests" => "CREATE TABLE IF NOT EXISTS `ussd_requests` (
        `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `code_id` INT(11) NOT NULL,
        `session_id` VARCHAR(100) NULL,
        `phone` VARCHAR(20) NULL,
        `input` TEXT NULL,
        `response` TEXT NULL,
        `http_status` SMALLINT(6) DEFAULT 200,
        `duration_ms` INT(11) DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB",

    "whatsapp_accounts" => "CREATE TABLE IF NOT EXISTS `whatsapp_accounts` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `instance_id` VARCHAR(100) NOT NULL,
        `token` VARCHAR(255) NOT NULL,
        `status` ENUM('pending','active','expired','suspended') NOT NULL DEFAULT 'pending',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB",

    "whatsapp_chatbots" => "CREATE TABLE IF NOT EXISTS `whatsapp_chatbots` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `parent_id` INT(11) DEFAULT NULL,
        `keyword` VARCHAR(100) NOT NULL,
        `match_type` ENUM('exact','contains','starts_with') NOT NULL DEFAULT 'exact',
        `response` TEXT NOT NULL,
        `media_url` VARCHAR(255) DEFAULT NULL,
        `is_menu` TINYINT(1) NOT NULL DEFAULT 0,
        `is_dynamic` TINYINT(1) NOT NULL DEFAULT 0,
        `data_source_table` VARCHAR(100) DEFAULT NULL,
        `trigger_count` INT(11) NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB",

    "whatsapp_custom_data" => "CREATE TABLE IF NOT EXISTS `whatsapp_custom_data` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `table_name` VARCHAR(100) NOT NULL,
        `data_key` VARCHAR(100) NOT NULL,
        `data_value` LONGTEXT NOT NULL, 
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB"
];

foreach ($tables as $name => $sql) {
    try {
        $db->exec($sql);
        echo "<p style='color:green'>✅ Table <b>$name</b> verified/created.</p>";
    } catch (PDOException $e) {
        echo "<p style='color:red'>❌ Error creating table $name: " . $e->getMessage() . "</p>";
    }
}

// 2. Add Missing Columns
$columns = [
    ['ussd_requests', 'user_id', 'INT(11) NOT NULL AFTER `id`'],
    ['ussd_requests', 'code_id', 'INT(11) NOT NULL AFTER `user_id`'],
    ['ussd_sessions', 'user_id', 'INT(11) NOT NULL AFTER `id`'],
    ['ussd_sessions', 'code_id', 'INT(11) NOT NULL AFTER `user_id`'],
    ['users', 'ussd_balance', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00'],
    ['users', 'whatsapp_balance', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00'],
    ['users', 'api_client_id', 'VARCHAR(50) DEFAULT NULL'],
    ['users', 'api_key', 'VARCHAR(100) DEFAULT NULL'],
    ['whatsapp_messages', 'external_id', 'VARCHAR(100) DEFAULT NULL AFTER `status`']
];

foreach ($columns as $col) {
    list($table, $name, $def) = $col;
    try {
        $db->exec("ALTER TABLE `$table` ADD `$name` $def");
        echo "<p style='color:green'>✅ Column <b>$name</b> added to <b>$table</b>.</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "<p style='color:blue'>ℹ️ Column <b>$name</b> already exists in <b>$table</b>.</p>";
        } else {
            echo "<p style='color:red'>❌ Error adding column $name to $table: " . $e->getMessage() . "</p>";
        }
    }
}

echo "<h3>🎉 All fixes applied!</h3>";
echo "<p>Try refreshing your USSD Analytics and WhatsApp Chatbot pages now.</p>";
echo "</div>";
