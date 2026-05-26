-- Phase 15: Fair campaign dispatch + configurable Onfon batch delay
-- Safe to run on an existing database (all statements are idempotent).

-- Configurable pause (ms) between each Onfon API batch call.
-- Increase to 300-500 if you see elevated failure rates under load.
INSERT IGNORE INTO `system_settings` (`key`, `value`) VALUES ('onfon_batch_delay_ms', '100');
