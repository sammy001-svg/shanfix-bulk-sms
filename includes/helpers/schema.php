<?php
/**
 * Schema inspection and self-service migration — Shanfix Technology
 *
 * Two jobs:
 *   1. Let code ask whether a column exists, so a page degrades gracefully
 *      instead of dying with a fatal when a migration has not been applied.
 *   2. Apply the outstanding additive changes from one place, so the platform
 *      does not depend on someone remembering to run a .sql file by hand.
 *
 * Only additive, idempotent operations live here — ADD COLUMN and ADD INDEX,
 * plus INSERT IGNORE of settings keys. Nothing drops, renames, or rewrites
 * data, so applying it is safe on a live database and safe to repeat.
 */
require_once __DIR__ . '/../db.php';

class Schema {

    /** Per-request cache: "table.column" => bool */
    private static $columns = [];

    /**
     * The additive changes the current code expects. Each entry is checked
     * before it is applied, so this doubles as the "what is missing" report.
     */
    public static function required(): array {
        return [
            [
                'key'     => 'messages.dlr_status',
                'type'    => 'column',
                'table'   => 'messages',
                'column'  => 'dlr_status',
                'sql'     => "ALTER TABLE `messages` ADD COLUMN `dlr_status` VARCHAR(60) DEFAULT NULL
                              COMMENT 'Raw carrier delivery status from the DLR webhook'",
                'purpose' => 'Delivery Reports — the granular carrier status columns',
            ],
            [
                'key'     => 'purchases.gateway_ref',
                'type'    => 'column',
                'table'   => 'purchases',
                'column'  => 'gateway_ref',
                'sql'     => "ALTER TABLE `purchases` ADD COLUMN `gateway_ref` VARCHAR(255) DEFAULT NULL
                              COMMENT 'Kopo Kopo payment resource URL'",
                'purpose' => 'Kopo Kopo — confirming a payment when the webhook does not arrive',
            ],
            [
                'key'     => 'ussd_transactions.gateway_ref',
                'type'    => 'column',
                'table'   => 'ussd_transactions',
                'column'  => 'gateway_ref',
                'sql'     => "ALTER TABLE `ussd_transactions` ADD COLUMN `gateway_ref` VARCHAR(255) DEFAULT NULL
                              COMMENT 'Kopo Kopo payment resource URL'",
                'purpose' => 'Kopo Kopo — USSD wallet top-up confirmation',
            ],
            [
                'key'     => 'messages.idx_user_created_dlr',
                'type'    => 'index',
                'table'   => 'messages',
                'index'   => 'idx_user_created_dlr',
                'sql'     => "ALTER TABLE `messages` ADD KEY `idx_user_created_dlr` (`user_id`, `created_at`, `dlr_status`)",
                'purpose' => 'Delivery Reports — keeps the per-day pivot fast',
                'depends' => 'messages.dlr_status',
            ],
            [
                'key'     => 'messages.idx_gateway_msg_id',
                'type'    => 'index',
                'table'   => 'messages',
                'index'   => 'idx_gateway_msg_id',
                'sql'     => "ALTER TABLE `messages` ADD KEY `idx_gateway_msg_id` (`gateway_msg_id`)",
                'purpose' => 'Delivery receipts — avoids a full scan on every DLR callback',
            ],
            [
                'key'     => 'purchases.idx_status_created',
                'type'    => 'index',
                'table'   => 'purchases',
                'index'   => 'idx_status_created',
                'sql'     => "ALTER TABLE `purchases` ADD KEY `idx_status_created` (`status`, `created_at`)",
                'purpose' => 'Payment reconciliation sweep',
            ],
            [
                'key'     => 'ussd_transactions.idx_status_created',
                'type'    => 'index',
                'table'   => 'ussd_transactions',
                'index'   => 'idx_status_created',
                'sql'     => "ALTER TABLE `ussd_transactions` ADD KEY `idx_status_created` (`status`, `created_at`)",
                'purpose' => 'Payment reconciliation sweep',
            ],
            [
                'key'      => 'settings.kk_api_key',
                'type'     => 'setting',
                'setting'  => 'kk_api_key',
                'sql'      => "INSERT IGNORE INTO `system_settings` (`key`, `value`) VALUES ('kk_api_key', '')",
                'purpose'  => 'Kopo Kopo — API Key used to verify webhook signatures',
            ],
            [
                'key'      => 'settings.kk_webhook_token',
                'type'     => 'setting',
                'setting'  => 'kk_webhook_token',
                'sql'      => "INSERT IGNORE INTO `system_settings` (`key`, `value`) VALUES ('kk_webhook_token', '')",
                'purpose'  => 'Kopo Kopo — optional webhook URL token',
            ],
        ];
    }

    /** Does a table exist? */
    public static function hasTable(string $table): bool {
        try {
            return (bool)DB::queryOne(
                "SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
                [$table]
            );
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Does a column exist? Cached for the request, so a page can call this on
     * every query without extra round trips.
     */
    public static function hasColumn(string $table, string $column): bool {
        $cacheKey = "$table.$column";
        if (isset(self::$columns[$cacheKey])) return self::$columns[$cacheKey];

        try {
            $found = (bool)DB::queryOne(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
                [$table, $column]
            );
        } catch (Throwable $e) {
            $found = false;
        }

        return self::$columns[$cacheKey] = $found;
    }

    /** Does an index exist? */
    public static function hasIndex(string $table, string $index): bool {
        try {
            return (bool)DB::queryOne(
                "SELECT 1 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?",
                [$table, $index]
            );
        } catch (Throwable $e) {
            return false;
        }
    }

    /** Does a settings key exist? */
    public static function hasSetting(string $key): bool {
        try {
            return (bool)DB::queryOne("SELECT 1 FROM system_settings WHERE `key` = ?", [$key]);
        } catch (Throwable $e) {
            return false;
        }
    }

    /** Is one required change already in place? */
    public static function isApplied(array $item): bool {
        switch ($item['type']) {
            case 'column':  return self::hasColumn($item['table'], $item['column']);
            case 'index':   return self::hasIndex($item['table'], $item['index']);
            case 'setting': return self::hasSetting($item['setting']);
            default:        return false;
        }
    }

    /**
     * Every required change with its current state attached.
     * @return array<int, array> each entry gains 'applied' => bool
     */
    public static function status(): array {
        $out = [];
        foreach (self::required() as $item) {
            // Skip anything whose table is absent — that is a different problem
            // and running the ALTER would only produce a confusing error.
            if (isset($item['table']) && !self::hasTable($item['table'])) {
                $item['applied'] = false;
                $item['blocked'] = 'Table ' . $item['table'] . ' does not exist';
                $out[] = $item;
                continue;
            }
            $item['applied'] = self::isApplied($item);
            $out[] = $item;
        }
        return $out;
    }

    /** How many required changes are still outstanding. */
    public static function pendingCount(): int {
        return count(array_filter(self::status(), static fn($i) => !$i['applied']));
    }

    /**
     * Apply every outstanding change. Each runs on its own so one failure does
     * not prevent the rest, and each is re-checked first so this is safe to run
     * repeatedly.
     *
     * @return array{applied:string[], skipped:string[], failed:array<string,string>}
     */
    public static function apply(): array {
        $result = ['applied' => [], 'skipped' => [], 'failed' => []];

        foreach (self::status() as $item) {
            if (!empty($item['blocked'])) {
                $result['failed'][$item['key']] = $item['blocked'];
                continue;
            }
            if ($item['applied']) {
                $result['skipped'][] = $item['key'];
                continue;
            }
            // A dependency that is still missing means this one cannot work yet;
            // it will be picked up on the next run.
            if (!empty($item['depends']) && !self::hasColumn(...explode('.', $item['depends']))) {
                $result['failed'][$item['key']] = 'Waiting on ' . $item['depends'];
                continue;
            }

            try {
                DB::execute($item['sql']);
                $result['applied'][] = $item['key'];
                self::$columns = [];   // invalidate the cache
            } catch (Throwable $e) {
                $result['failed'][$item['key']] = $e->getMessage();
                error_log('Schema::apply failed for ' . $item['key'] . ': ' . $e->getMessage());
            }
        }

        return $result;
    }
}
