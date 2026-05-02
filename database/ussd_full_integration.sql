-- Shanfix Bulk SMS - Complete USSD Integration Schema
-- This file contains all tables and column updates required for USSD Code Requests, 
-- Usage Analytics, Session Tracking, and the USSD Wallet system.

-- 1. Update Users Table with USSD Balance
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `ussd_balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `sms_units`;

-- 2. Table for USSD Code Requests and Approvals
CREATE TABLE IF NOT EXISTS `ussd_codes` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED NOT NULL,
  `type`            ENUM('shared','dedicated') NOT NULL DEFAULT 'shared',
  `requested_code`  VARCHAR(20)  DEFAULT NULL, -- e.g. *384*10#
  `purpose`         TEXT         NOT NULL,
  `status`          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reject_reason`   VARCHAR(255) DEFAULT NULL,
  `approved_at`     DATETIME     DEFAULT NULL,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_ussd_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Table for USSD Session Tracking
CREATE TABLE IF NOT EXISTS `ussd_sessions` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED NOT NULL,
  `ussd_code_id`    INT UNSIGNED NOT NULL,
  `session_id`      VARCHAR(100) NOT NULL,
  `phone`           VARCHAR(20)  NOT NULL,
  `status`          ENUM('active','completed','timed_out') NOT NULL DEFAULT 'active',
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ended_at`        DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_code` (`ussd_code_id`),
  KEY `idx_session` (`session_id`),
  CONSTRAINT `fk_usess_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_usess_code` FOREIGN KEY (`ussd_code_id`) REFERENCES `ussd_codes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Table for Individual USSD HTTP Requests (Traffic Analytics)
CREATE TABLE IF NOT EXISTS `ussd_requests` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id`      INT UNSIGNED NOT NULL,
  `input_text`      VARCHAR(255) DEFAULT NULL,
  `response_text`   TEXT         DEFAULT NULL,
  `status_code`     INT          NOT NULL DEFAULT 200, -- HTTP Status Code from customer backend
  `response_time`   INT          NOT NULL DEFAULT 0,   -- Response time in milliseconds
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rsession` (`session_id`),
  CONSTRAINT `fk_ureq_session` FOREIGN KEY (`session_id`) REFERENCES `ussd_sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Table for USSD Wallet Transactions (Financial Ledger)
CREATE TABLE IF NOT EXISTS `ussd_transactions` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED NOT NULL,
  `amount`          DECIMAL(12,2) NOT NULL,
  `type`            ENUM('credit','debit') NOT NULL,
  `status`          ENUM('pending','completed','failed') NOT NULL DEFAULT 'pending',
  `description`     VARCHAR(255)  NOT NULL,
  `reference`       VARCHAR(100)  DEFAULT NULL, -- M-Pesa Code or Internal Ref
  `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_utrans_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
