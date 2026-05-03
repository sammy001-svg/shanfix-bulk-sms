-- Shanfix Bulk SMS: USSD Analytics & WhatsApp Chatbot Setup
-- This script ensures all tables for real-time monitoring and automation are present.

-- =============================================================================
-- 1. USSD CORE & ANALYTICS TABLES
-- =============================================================================

-- USSD Codes (The services requested by users)
CREATE TABLE IF NOT EXISTS `ussd_codes` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`           INT UNSIGNED NOT NULL,
    `requested_code`    VARCHAR(50)  NOT NULL,
    `type`              ENUM('dedicated', 'shared') DEFAULT 'dedicated',
    `purpose`           TEXT,
    `status`            ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    `reject_reason`     TEXT,
    `created_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- USSD Sessions (Tracking individual user interactions)
CREATE TABLE IF NOT EXISTS `ussd_sessions` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`           INT UNSIGNED NOT NULL,
    `code_id`           INT UNSIGNED NOT NULL,
    `session_id`        VARCHAR(100) NOT NULL,
    `phone`             VARCHAR(20) NOT NULL,
    `status`            ENUM('active', 'completed', 'timed_out') DEFAULT 'active',
    `created_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_session_id` (`session_id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_code` (`code_id`),
    CONSTRAINT `fk_ussd_sess_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ussd_sess_code` FOREIGN KEY (`code_id`) REFERENCES `ussd_codes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- USSD Requests (Hits/Logs for every interaction)
CREATE TABLE IF NOT EXISTS `ussd_requests` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`           INT UNSIGNED NOT NULL,
    `code_id`           INT UNSIGNED NOT NULL,
    `session_id`        VARCHAR(100) NULL,
    `phone`             VARCHAR(20) NULL,
    `input`             TEXT NULL,
    `response`          TEXT NULL,
    `http_status`       SMALLINT DEFAULT 200,
    `duration_ms`       INT DEFAULT 0,
    `created_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_code` (`code_id`),
    KEY `idx_created` (`created_at`),
    CONSTRAINT `fk_ussd_req_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ussd_req_code` FOREIGN KEY (`code_id`) REFERENCES `ussd_codes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 2. WHATSAPP CHATBOT & DATA HUB TABLES
-- =============================================================================

-- WhatsApp Accounts
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

-- WhatsApp Chatbots (Automation Rules)
CREATE TABLE IF NOT EXISTS `whatsapp_chatbots` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED NOT NULL,
  `parent_id`       INT UNSIGNED DEFAULT NULL,
  `keyword`         VARCHAR(100) NOT NULL,
  `match_type`      ENUM('exact','contains','starts_with') NOT NULL DEFAULT 'exact',
  `response`        TEXT         NOT NULL,
  `media_url`       VARCHAR(255) DEFAULT NULL,
  `is_menu`         TINYINT(1)   NOT NULL DEFAULT 0,
  `is_dynamic`      TINYINT(1)   NOT NULL DEFAULT 0,
  `data_source_table` VARCHAR(100) DEFAULT NULL,
  `trigger_count`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_parent` (`parent_id`),
  CONSTRAINT `fk_chatbot_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chatbot_parent` FOREIGN KEY (`parent_id`) REFERENCES `whatsapp_chatbots`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- WhatsApp Dynamic Data Hub (Rows/Data)
CREATE TABLE IF NOT EXISTS `whatsapp_custom_data` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED NOT NULL,
  `table_name`      VARCHAR(100) NOT NULL,
  `data_key`        VARCHAR(100) NOT NULL,
  `data_value`      JSON         NOT NULL,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_table` (`user_id`, `table_name`),
  CONSTRAINT `fk_data_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- WhatsApp Bot Sessions
CREATE TABLE IF NOT EXISTS `whatsapp_bot_sessions` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_id`      INT UNSIGNED NOT NULL,
  `sender_phone`    VARCHAR(20)  NOT NULL,
  `current_menu_id` INT UNSIGNED DEFAULT NULL,
  `last_interaction` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_acc_sender` (`account_id`, `sender_phone`),
  CONSTRAINT `fk_session_account` FOREIGN KEY (`account_id`) REFERENCES `whatsapp_accounts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
