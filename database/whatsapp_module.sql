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
