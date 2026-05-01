<?php
require_once 'config.php';
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    
    $sql = "CREATE TABLE IF NOT EXISTS `reseller_settings` (
        `reseller_id`   INT UNSIGNED NOT NULL,
        `system_name`   VARCHAR(120) DEFAULT NULL,
        `system_logo`   VARCHAR(255) DEFAULT NULL,
        `primary_color` VARCHAR(10)  DEFAULT '#2ecc71',
        `support_email` VARCHAR(180) DEFAULT NULL,
        `support_phone` VARCHAR(20)  DEFAULT NULL,
        `custom_domain` VARCHAR(180) DEFAULT NULL,
        `ssl_enabled`   TINYINT(1)   NOT NULL DEFAULT 0,
        `smtp_host`     VARCHAR(120) DEFAULT NULL,
        `smtp_port`     VARCHAR(10)  DEFAULT NULL,
        `smtp_user`     VARCHAR(120) DEFAULT NULL,
        `smtp_pass`     VARCHAR(255) DEFAULT NULL,
        `smtp_encryption` ENUM('none', 'ssl', 'tls') DEFAULT 'tls',
        `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`reseller_id`),
        UNIQUE KEY `idx_custom_domain` (`custom_domain`),
        CONSTRAINT `fk_reseller_settings_user` FOREIGN KEY (`reseller_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sql);
    echo "SUCCESS: Created reseller_settings table";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage();
}
