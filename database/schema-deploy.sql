-- ============================================================
-- BULK SMS SYSTEM - MySQL Database Schema (Deployment Version)
-- ============================================================
-- Use this version for cPanel or Shared Hosting where you 
-- cannot use CREATE DATABASE via SQL.
--
-- IMPORTANT: Create the database manually in cPanel first!
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================================
-- Table: users
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(120)      NOT NULL,
  `email`         VARCHAR(180)      NOT NULL UNIQUE,
  `phone`         VARCHAR(20)       DEFAULT NULL,
  `password_hash` VARCHAR(255)      NOT NULL,
  `role`          ENUM('admin','reseller','client') NOT NULL DEFAULT 'client',
  `parent_id`     INT UNSIGNED      DEFAULT NULL,
  `company`       VARCHAR(120)      DEFAULT NULL,
  `sms_units`     DECIMAL(12,2)     NOT NULL DEFAULT 0.00,
  `custom_unit_price` DECIMAL(10,2)  DEFAULT NULL,
  `status`        ENUM('active','suspended','pending') NOT NULL DEFAULT 'active',
  `avatar`        VARCHAR(255)      DEFAULT NULL,
  `last_login`    DATETIME          DEFAULT NULL,
  `created_at`    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_role` (`role`),
  KEY `idx_parent` (`parent_id`),
  CONSTRAINT `fk_user_parent` FOREIGN KEY (`parent_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: sender_ids
-- ============================================================
CREATE TABLE IF NOT EXISTS `sender_ids` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `sender_id`   VARCHAR(20)  NOT NULL,
  `purpose`     TEXT         DEFAULT NULL,
  `status`      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reject_reason` VARCHAR(255) DEFAULT NULL,
  `approved_by` INT UNSIGNED  DEFAULT NULL,
  `approved_at` DATETIME      DEFAULT NULL,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_sid_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sid_approver` FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: contact_groups
-- ============================================================
CREATE TABLE IF NOT EXISTS `contact_groups` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `name`        VARCHAR(120) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_cg_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: contacts
-- ============================================================
CREATE TABLE IF NOT EXISTS `contacts` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `group_id`    INT UNSIGNED DEFAULT NULL,
  `name`        VARCHAR(120) DEFAULT NULL,
  `phone`       VARCHAR(20)  NOT NULL,
  `email`       VARCHAR(120) DEFAULT NULL,
  `metadata`    JSON         DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_group` (`group_id`),
  CONSTRAINT `fk_c_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_c_group` FOREIGN KEY (`group_id`) REFERENCES `contact_groups`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: campaigns
-- ============================================================
CREATE TABLE IF NOT EXISTS `campaigns` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED NOT NULL,
  `name`          VARCHAR(180) NOT NULL,
  `sender_id`     VARCHAR(20)  NOT NULL,
  `message`       TEXT         NOT NULL,
  `group_id`      INT UNSIGNED DEFAULT NULL,
  `recipients`    TEXT         DEFAULT NULL,
  `total_count`   INT UNSIGNED NOT NULL DEFAULT 0,
  `sent_count`    INT UNSIGNED NOT NULL DEFAULT 0,
  `failed_count`  INT UNSIGNED NOT NULL DEFAULT 0,
  `units_used`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status`        ENUM('draft','queued','sending','completed','failed') NOT NULL DEFAULT 'draft',
  `scheduled_at`  DATETIME     DEFAULT NULL,
  `sent_at`       DATETIME     DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_camp_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_camp_group` FOREIGN KEY (`group_id`) REFERENCES `contact_groups`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: messages
-- ============================================================
CREATE TABLE IF NOT EXISTS `messages` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `campaign_id`   INT UNSIGNED    DEFAULT NULL,
  `user_id`       INT UNSIGNED    NOT NULL,
  `sender_id`     VARCHAR(20)     NOT NULL,
  `recipient`     VARCHAR(20)     NOT NULL,
  `message`       TEXT            NOT NULL,
  `units_charged` DECIMAL(8,4)    NOT NULL DEFAULT 1.0000,
  `status`        ENUM('queued','sent','delivered','failed','undelivered') NOT NULL DEFAULT 'queued',
  `gateway_msg_id` VARCHAR(100)   DEFAULT NULL,
  `sent_at`       DATETIME        DEFAULT NULL,
  `delivered_at`  DATETIME        DEFAULT NULL,
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_campaign` (`campaign_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_msg_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_msg_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: pricing_plans
-- ============================================================
CREATE TABLE IF NOT EXISTS `pricing_plans` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `owner_id`    INT UNSIGNED  DEFAULT NULL,
  `name`        VARCHAR(80)   NOT NULL,
  `units`       INT UNSIGNED  NOT NULL,
  `price`       DECIMAL(10,2) NOT NULL,
  `currency`    VARCHAR(5)    NOT NULL DEFAULT 'KES',
  `is_active`   TINYINT(1)    NOT NULL DEFAULT 1,
  `is_popular`  TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: purchases
-- ============================================================
CREATE TABLE IF NOT EXISTS `purchases` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED  NOT NULL,
  `type`          ENUM('sms','whatsapp') NOT NULL DEFAULT 'sms',
  `plan_id`       INT UNSIGNED  DEFAULT NULL,
  `units`         INT UNSIGNED  NOT NULL,
  `amount`        DECIMAL(10,2) NOT NULL,
  `currency`      VARCHAR(5)    NOT NULL DEFAULT 'KES',
  `payment_method` VARCHAR(40)  NOT NULL DEFAULT 'mpesa',
  `transaction_ref` VARCHAR(100) DEFAULT NULL,
  `status`        ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_purch_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_purch_plan` FOREIGN KEY (`plan_id`) REFERENCES `pricing_plans`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: unit_allocations
-- ============================================================
CREATE TABLE IF NOT EXISTS `unit_allocations` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `from_user`   INT UNSIGNED  NOT NULL,
  `to_user`     INT UNSIGNED  NOT NULL,
  `units`       DECIMAL(12,2) NOT NULL,
  `note`        VARCHAR(255)  DEFAULT NULL,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_from` (`from_user`),
  KEY `idx_to` (`to_user`),
  CONSTRAINT `fk_alloc_from` FOREIGN KEY (`from_user`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_alloc_to`   FOREIGN KEY (`to_user`)   REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: system_settings
-- ============================================================
CREATE TABLE IF NOT EXISTS `system_settings` (
  `key`   VARCHAR(80)  NOT NULL,
  `value` TEXT         DEFAULT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: reseller_settings
-- ============================================================
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

-- ============================================================
-- Table: notifications
-- ============================================================
CREATE TABLE IF NOT EXISTS `notifications` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT UNSIGNED DEFAULT NULL,
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

-- ============================================================
-- Table: sms_templates
-- ============================================================
CREATE TABLE IF NOT EXISTS `sms_templates` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NOT NULL,
  `title`       VARCHAR(120) NOT NULL,
  `message`     TEXT         NOT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_template_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Seed Data
-- ============================================================
INSERT IGNORE INTO `users` (`name`, `email`, `phone`, `password_hash`, `role`, `sms_units`, `status`)
VALUES ('Shanfix Admin', 'info@shanfixtechnology.com', '+254700000000', '$2y$12$CR7NANdPPdjMuG0lUc13yeLiM4DtIIBm7RnJAO1w4hIK8eLX6RJgy', 'admin', 999999.00, 'active');

INSERT IGNORE INTO `pricing_plans` (`name`, `units`, `price`, `currency`) VALUES
  ('Starter', 100, 50.00, 'KES'),
  ('Basic', 500, 200.00, 'KES'),
  ('Standard', 1000, 350.00, 'KES'),
  ('Business', 5000, 1500.00, 'KES'),
  ('Enterprise', 10000, 2500.00, 'KES');

INSERT IGNORE INTO `system_settings` (`key`, `value`) VALUES
  ('site_name', 'Shanfix Technology'),
  ('site_logo', '/assets/images/logo.png'),
  ('default_currency', 'KES'),
  ('sms_cost_per_unit', '1'),
  ('mpesa_shortcode', '174379'),
  ('smtp_host', 'smtp.gmail.com'),
  ('smtp_port', '587'),
  ('support_email', 'support@bulksms.com'),
  ('onfon_api_key', ''),
  ('onfon_user_id', ''),
  ('onfon_access_key', ''),
  ('kk_client_id', ''),
  ('kk_client_secret', ''),
  ('kk_base_url', 'https://api.kopokopo.com');

COMMIT;
