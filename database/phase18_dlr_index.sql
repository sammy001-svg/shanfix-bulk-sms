-- Phase 18: DLR webhook lookup index
-- Run once against the live database. Safe to re-run (IF NOT EXISTS).
--
-- api/v1/dlr.php updates messages by gateway_msg_id on every Onfon delivery
-- callback (one callback per SMS). Without this index each callback does a
-- full table scan on messages, which grows linearly with table size and can
-- saturate the DB during bulk sends.
ALTER TABLE messages
    ADD KEY IF NOT EXISTS `idx_gateway_msg_id` (`gateway_msg_id`);
