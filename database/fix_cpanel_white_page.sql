-- ============================================================
-- FIX: MISSING TABLES & COLUMNS FOR CPANEL DEPLOYMENT
-- Run this script in your phpMyAdmin to resolve the 500/White Page errors.
-- ============================================================

-- 1. Create Reseller Settings Table
CREATE TABLE IF NOT EXISTS `reseller_settings` (
    `reseller_id`           INT UNSIGNED NOT NULL,
    `system_name`           VARCHAR(120) DEFAULT NULL,
    `system_logo`           VARCHAR(255) DEFAULT NULL,
    `primary_color`         VARCHAR(10)  DEFAULT '#00c896',
    `sidebar_color`         VARCHAR(10)  DEFAULT '#0e1726',
    `unit_price`            DECIMAL(10,2) DEFAULT NULL,
    `payment_instructions`  TEXT         DEFAULT NULL,
    `support_email`         VARCHAR(180) DEFAULT NULL,
    `support_phone`         VARCHAR(20)  DEFAULT NULL,
    `custom_domain`         VARCHAR(180) DEFAULT NULL,
    `ssl_enabled`           TINYINT(1)   NOT NULL DEFAULT 0,
    `smtp_host`             VARCHAR(120) DEFAULT NULL,
    `smtp_port`             VARCHAR(10)  DEFAULT NULL,
    `smtp_user`             VARCHAR(120) DEFAULT NULL,
    `smtp_pass`             VARCHAR(255) DEFAULT NULL,
    `smtp_encryption`       ENUM('none', 'ssl', 'tls') DEFAULT 'tls',
    `created_at`            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`reseller_id`),
    UNIQUE KEY `idx_custom_domain` (`custom_domain`),
    CONSTRAINT `fk_reseller_settings_user` FOREIGN KEY (`reseller_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create Notifications Tables
CREATE TABLE IF NOT EXISTS `notifications` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT UNSIGNED DEFAULT NULL, -- NULL means broadcast
    `type`       ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
    `title`      VARCHAR(255) NOT NULL,
    `message`    TEXT NOT NULL,
    `image_url`  VARCHAR(255) DEFAULT NULL,
    `is_popup`   TINYINT(1)   DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notification_interactions` (
    `user_id`         INT UNSIGNED NOT NULL,
    `notification_id` INT UNSIGNED NOT NULL,
    `is_read`         TINYINT(1) DEFAULT 0,
    `is_dismissed`    TINYINT(1) DEFAULT 0,
    `interacted_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `notification_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`notification_id`) REFERENCES `notifications`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Add Missing API Columns to Users table
-- Note: cPanel MySQL might not support ADD COLUMN IF NOT EXISTS in older versions.
-- If you get an error, run these without 'IF NOT EXISTS'.
ALTER TABLE `users` ADD COLUMN `api_client_id` VARCHAR(32) DEFAULT NULL AFTER `updated_at`;
ALTER TABLE `users` ADD COLUMN `api_key` VARCHAR(64) DEFAULT NULL AFTER `api_client_id`;
ALTER TABLE `users` ADD UNIQUE (`api_client_id`);
ALTER TABLE `users` ADD UNIQUE (`api_key`);

-- 4. Ensure Migration V2 columns exist
ALTER TABLE `users` ADD COLUMN `custom_unit_price` DECIMAL(10,2) DEFAULT NULL AFTER `sms_units`;
ALTER TABLE `pricing_plans` ADD COLUMN `owner_id` INT UNSIGNED DEFAULT NULL AFTER `id`;

-- 5. Finalize
COMMIT;
