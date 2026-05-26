-- Phase 11: Campaign worker heartbeat
-- Adds locked_at (if missing) and last_heartbeat_at to the campaigns table.
-- The rescue query in process_campaigns.php now watches last_heartbeat_at so
-- slow-but-alive campaigns are never mistakenly re-queued and double-sent.

ALTER TABLE `campaigns`
  ADD COLUMN IF NOT EXISTS `locked_at`         DATETIME DEFAULT NULL
    COMMENT 'Set when a worker claims this campaign',
  ADD COLUMN IF NOT EXISTS `last_heartbeat_at` DATETIME DEFAULT NULL
    COMMENT 'Updated after every batch; stale = dead worker';

-- Ensure the index covering the rescue query exists (no-op if already present)
ALTER TABLE `campaigns`
  ADD INDEX IF NOT EXISTS `idx_status_locked` (`status`, `locked_at`),
  ADD INDEX IF NOT EXISTS `idx_heartbeat`     (`status`, `last_heartbeat_at`);
