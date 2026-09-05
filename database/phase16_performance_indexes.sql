-- Phase 16: Performance Indexes
-- Run once against the live database.
-- All statements use IF NOT EXISTS / ADD IF NOT EXISTS so re-running is safe.

-- ─── purchases ────────────────────────────────────────────────────────────────
-- Covers: admin/finances.php date-range scans, analytics revenue queries,
--         and the all-time SUM(amount) aggregate filtered by status.
ALTER TABLE purchases
    ADD KEY IF NOT EXISTS `idx_status_created` (`status`, `created_at`);

-- ─── whatsapp_messages ───────────────────────────────────────────────────────
-- Covers: client/whatsapp-logs.php per-status COUNT queries (was 4 full scans).
-- Also covers ORDER BY created_at DESC LIMIT 100 log fetch.
ALTER TABLE whatsapp_messages
    ADD KEY IF NOT EXISTS `idx_user_status`  (`user_id`, `status`),
    ADD KEY IF NOT EXISTS `idx_user_created` (`user_id`, `created_at`);

-- ─── users ───────────────────────────────────────────────────────────────────
-- Covers: auth_login() lookup by email, password-reset lookup.
-- Use ADD INDEX rather than UNIQUE in case duplicates exist in dev data.
ALTER TABLE users
    ADD KEY IF NOT EXISTS `idx_email` (`email`);

-- ─── ussd_sessions ───────────────────────────────────────────────────────────
-- The ussd_full_integration.sql schema uses ended_at instead of updated_at.
-- Add updated_at first (IF NOT EXISTS), then index it.
-- client/ussd-sessions.php sorts by updated_at DESC.
ALTER TABLE ussd_sessions
    ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE ussd_sessions
    ADD KEY IF NOT EXISTS `idx_user_updated` (`user_id`, `updated_at`);

-- ─── reseller_settings ───────────────────────────────────────────────────────
-- Covers: Branding::init() custom_domain lookup (white-label entry point).
ALTER TABLE reseller_settings
    ADD KEY IF NOT EXISTS `idx_custom_domain` (`custom_domain`(191));

-- ─── messages (fill gap for analytics date-range aggregates) ─────────────────
-- The existing idx_user_created(user_id, created_at) is used for per-user
-- queries but admin analytics scans ALL users. A standalone created_at index
-- lets MySQL use a range scan for global date-range aggregates.
ALTER TABLE messages
    ADD KEY IF NOT EXISTS `idx_created_at` (`created_at`);

-- ─── campaigns ───────────────────────────────────────────────────────────────
-- Covers: analytics created_at range queries and scheduled campaign retrieval.
ALTER TABLE campaigns
    ADD KEY IF NOT EXISTS `idx_created_at`   (`created_at`),
    ADD KEY IF NOT EXISTS `idx_scheduled_at` (`scheduled_at`);
