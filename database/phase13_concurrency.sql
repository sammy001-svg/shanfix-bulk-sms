-- Phase 13: Concurrency hardening
-- Safe to run on an existing database — all statements are no-ops if already applied.

-- 1. Cursor column for group contact pagination (replaces OFFSET scan)
ALTER TABLE `campaigns`
  ADD COLUMN IF NOT EXISTS `resume_contact_id` INT UNSIGNED NOT NULL DEFAULT 0
    COMMENT 'Last contacts.id fetched — cursor for group crash-recovery'
    AFTER `processed_rows`;

-- 2. Covering index for message reports (makes date-range queries sargable)
ALTER TABLE `messages`
  ADD INDEX IF NOT EXISTS `idx_user_created` (`user_id`, `created_at`);

-- 3. Atomic API rate-limit counter table
CREATE TABLE IF NOT EXISTS `api_rate_counters` (
  `bucket`       VARCHAR(100) NOT NULL,
  `window_start` DATETIME     NOT NULL,
  `hits`         INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`bucket`, `window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
