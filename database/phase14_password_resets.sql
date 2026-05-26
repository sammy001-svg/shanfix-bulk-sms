-- Phase 14: Password reset tokens + email sender name
-- Safe to run on an existing database.

-- 1. Password reset token table
CREATE TABLE IF NOT EXISTS `password_resets` (
  `token`      VARCHAR(64)  NOT NULL,
  `email`      VARCHAR(180) NOT NULL,
  `expires_at` DATETIME     NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`token`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Email sender display name setting (optional override for smtp_user)
INSERT IGNORE INTO `system_settings` (`key`, `value`) VALUES ('smtp_from_name', '');
