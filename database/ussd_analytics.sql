-- Shanfix Bulk SMS - USSD Analytics Module
-- Tables for tracking USSD sessions and individual HTTP requests

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

CREATE TABLE IF NOT EXISTS `ussd_requests` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id`      INT UNSIGNED NOT NULL,
  `input_text`      VARCHAR(255) DEFAULT NULL,
  `response_text`   TEXT         DEFAULT NULL,
  `status_code`     INT          NOT NULL DEFAULT 200, -- HTTP Status Code returned by customer backend
  `response_time`   INT          NOT NULL DEFAULT 0,   -- Response time in milliseconds
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rsession` (`session_id`),
  CONSTRAINT `fk_ureq_session` FOREIGN KEY (`session_id`) REFERENCES `ussd_sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
