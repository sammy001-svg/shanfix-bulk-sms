-- Shanfix Bulk SMS - USSD Module
-- Table to store USSD code requests and approvals

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
