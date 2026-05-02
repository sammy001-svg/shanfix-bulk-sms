-- Shanfix Bulk SMS - WhatsApp Module Schema
-- This file contains tables and column updates required for WhatsApp Integration.

-- 1. Update Users Table with WhatsApp Balance
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `whatsapp_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `ussd_balance`;

-- 2. Table for WhatsApp Instance Connections
CREATE TABLE IF NOT EXISTS `whatsapp_accounts` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED NOT NULL,
  `instance_id`     VARCHAR(100) NOT NULL,
  `token`           VARCHAR(255) NOT NULL,
  `status`          ENUM('pending','active','expired','suspended') NOT NULL DEFAULT 'pending',
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_instance` (`user_id`, `instance_id`),
  CONSTRAINT `fk_wa_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Table for WhatsApp Message Logs
CREATE TABLE IF NOT EXISTS `whatsapp_messages` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED    NOT NULL,
  `account_id`      INT UNSIGNED    NOT NULL,
  `recipient`       VARCHAR(20)     NOT NULL,
  `message`         TEXT            NOT NULL,
  `media_url`       VARCHAR(255)    DEFAULT NULL,
  `status`          ENUM('queued','sent','delivered','read','failed') NOT NULL DEFAULT 'queued',
  `external_id`     VARCHAR(100)    DEFAULT NULL, -- ID from the provider
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at`         DATETIME        DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_account` (`account_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_wamsg_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wamsg_account` FOREIGN KEY (`account_id`) REFERENCES `whatsapp_accounts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Table for WhatsApp Chatbot / Automation Rules
CREATE TABLE IF NOT EXISTS `whatsapp_chatbots` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED NOT NULL,
  `keyword`         VARCHAR(100) NOT NULL,
  `match_type`      ENUM('exact','contains','starts_with') NOT NULL DEFAULT 'exact',
  `response`        TEXT         NOT NULL,
  `media_url`       VARCHAR(255) DEFAULT NULL,
  `trigger_count`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_chatbot_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Table for WhatsApp Self-Service Module Configurations
CREATE TABLE IF NOT EXISTS `whatsapp_self_service` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED NOT NULL,
  `module_type`     ENUM('order_status','appointment','delivery','account') NOT NULL,
  `trigger_keyword` VARCHAR(50)  NOT NULL,
  `response_template` TEXT       NOT NULL,
  `is_enabled`      TINYINT(1)   NOT NULL DEFAULT 1,
  `config_json`     JSON         DEFAULT NULL, -- For extra fields like API endpoints or custom logic
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_module` (`user_id`, `module_type`),
  CONSTRAINT `fk_ss_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Demo Tables for Self-Service Logic (Placeholders)
CREATE TABLE IF NOT EXISTS `demo_orders` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_no`        VARCHAR(20)  NOT NULL UNIQUE,
  `customer_phone`  VARCHAR(20)  NOT NULL,
  `status`          VARCHAR(50)  NOT NULL,
  `delivery_date`   DATE         DEFAULT NULL,
  `amount`          DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `demo_appointments` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_phone`  VARCHAR(20)  NOT NULL,
  `service_type`    VARCHAR(100) NOT NULL,
  `appointment_at`  DATETIME     NOT NULL,
  `status`          VARCHAR(50)  NOT NULL DEFAULT 'confirmed',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed some demo data for testing
INSERT IGNORE INTO `demo_orders` (`order_no`, `customer_phone`, `status`, `delivery_date`, `amount`) VALUES
('ORD-1001', '254700000000', 'In Transit', '2026-05-05', 4500.00),
('ORD-1002', '254700000000', 'Pending', '2026-05-06', 1200.00);

INSERT IGNORE INTO `demo_appointments` (`customer_phone`, `service_type`, `appointment_at`, `status`) VALUES
('254700000000', 'Hair Cut', '2026-05-03 10:00:00', 'confirmed');
