-- Shanfix Bulk SMS - Database Update v1.1
-- Includes: Metadata for Personalized SMS and SMS Templates Feature

-- 1. Add Metadata column to Contacts table for 'Send from File' personalization
-- This stores extra columns from CSV files (e.g. {name}, {balance}) as JSON
ALTER TABLE `contacts` ADD COLUMN IF NOT EXISTS `metadata` JSON DEFAULT NULL AFTER `email`;

-- 2. Create SMS Templates Table
-- Allows users to save frequently used messages
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
