-- Shanfix Bulk SMS - WhatsApp & Dynamic Data Hub Integration
-- This script ensures all tables for WhatsApp Connect, Bulk, Contacts, Automations, and Data Hub are present.

-- 1. Ensure Users table has required columns
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `whatsapp_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `ussd_balance`;

-- 2. WhatsApp Accounts (Connect WhatsApp)
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

-- 3. WhatsApp Contact Groups
CREATE TABLE IF NOT EXISTS `whatsapp_contact_groups` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_wa_cg_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. WhatsApp Contacts
CREATE TABLE IF NOT EXISTS `whatsapp_contacts` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `group_id`    INT UNSIGNED DEFAULT NULL,
  `name`        VARCHAR(120) DEFAULT NULL,
  `phone`       VARCHAR(20)  NOT NULL,
  `email`       VARCHAR(120) DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_group` (`group_id`),
  CONSTRAINT `fk_wa_c_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wa_c_group` FOREIGN KEY (`group_id`) REFERENCES `whatsapp_contact_groups`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. WhatsApp Messages (Bulk & Individual Logs)
CREATE TABLE IF NOT EXISTS `whatsapp_messages` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED    NOT NULL,
  `account_id`      INT UNSIGNED    NOT NULL,
  `recipient`       VARCHAR(20)     NOT NULL,
  `message`         TEXT            NOT NULL,
  `media_url`       VARCHAR(255)    DEFAULT NULL,
  `status`          ENUM('queued','sent','delivered','read','failed') NOT NULL DEFAULT 'queued',
  `external_id`     VARCHAR(100)    DEFAULT NULL,
  `created_at`      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at`         DATETIME        DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_account` (`account_id`),
  CONSTRAINT `fk_wamsg_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wamsg_account` FOREIGN KEY (`account_id`) REFERENCES `whatsapp_accounts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. WhatsApp Chatbots (Smart Automations)
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

-- 7. WhatsApp Bot Sessions (Tracking current menu)
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

-- 8. WhatsApp Dynamic Data Hub (Schemas)
CREATE TABLE IF NOT EXISTS `whatsapp_data_schemas` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED NOT NULL,
  `table_name`      VARCHAR(100) NOT NULL,
  `columns`         JSON         NOT NULL,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_table` (`user_id`, `table_name`),
  CONSTRAINT `fk_schema_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. WhatsApp Dynamic Data Hub (Rows)
CREATE TABLE IF NOT EXISTS `whatsapp_custom_data` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED NOT NULL,
  `table_name`      VARCHAR(100) NOT NULL,
  `data_key`        VARCHAR(100) NOT NULL COMMENT 'The lookup ID (e.g. Order No)',
  `data_value`      JSON         NOT NULL COMMENT 'The full row data',
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_table` (`user_id`, `table_name`),
  KEY `idx_data_key` (`data_key`),
  CONSTRAINT `fk_data_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. WhatsApp Self-Service (Extra configurations)
CREATE TABLE IF NOT EXISTS `whatsapp_self_service` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED NOT NULL,
  `module_type`     ENUM('order_status','appointment','delivery','account') NOT NULL,
  `trigger_keyword` VARCHAR(50)  NOT NULL,
  `response_template` TEXT       NOT NULL,
  `is_enabled`      TINYINT(1)   NOT NULL DEFAULT 1,
  `config_json`     JSON         DEFAULT NULL,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_module` (`user_id`, `module_type`),
  CONSTRAINT `fk_ss_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Demo Tables
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
