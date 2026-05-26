-- Phase 6: Rate limiting, brute force protection, and KoPokoPo HMAC auth
-- Run on existing installations (safe to re-run: uses IF NOT EXISTS).

-- Shared sliding-window rate limit log.
-- Buckets:
--   login:<email>    — failed login attempts per email address
--   api:<user_id>    — API sendsms calls per authenticated user
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bucket`   VARCHAR(191) NOT NULL,
  `hit_at`   DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_bucket_time` (`bucket`, `hit_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- KoPokoPo webhook secret setting (admin configures this in Settings > Payments).
-- The value is stored via the existing system_settings mechanism; this INSERT
-- is a no-op if the key already exists.
INSERT IGNORE INTO `system_settings` (`key`, `value`)
VALUES ('kk_webhook_secret', '');
