-- Phase 10: Concurrent campaign worker cap
-- Adds the system setting that controls how many campaigns may send simultaneously.
-- The cron processor reads this value; if absent it falls back to 5 (DEFAULT_MAX_CONCURRENT).

INSERT IGNORE INTO `system_settings` (`key`, `value`)
VALUES ('max_concurrent_campaigns', '5');

-- Index to make the per-minute rescue query fast at scale.
-- Scans only rows in 'sending' status with a non-null locked_at rather than a full table scan.
ALTER TABLE `campaigns`
  ADD INDEX IF NOT EXISTS `idx_status_locked` (`status`, `locked_at`);
