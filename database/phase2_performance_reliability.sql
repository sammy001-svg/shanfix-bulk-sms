-- ============================================================
-- Phase 2: Performance & Reliability Migration
-- Run once; safe to re-run (uses IF NOT EXISTS / CREATE IF).
-- ============================================================

-- 1. Composite index for campaign-status polling and failed-message queries.
--    Replaces the separate idx_campaign + idx_status scans with a single seek.
ALTER TABLE `messages`
  ADD INDEX `idx_campaign_status` (`campaign_id`, `status`);

-- 2. Composite index for the live-campaigns polling query:
--    WHERE user_id = ? AND status IN ('queued','running','sending')
ALTER TABLE `campaigns`
  ADD INDEX `idx_user_status` (`user_id`, `status`);
