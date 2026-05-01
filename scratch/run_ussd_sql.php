<?php
require_once __DIR__ . '/../includes/db.php';

$sqls = [
    "CREATE TABLE IF NOT EXISTS `ussd_sessions` (
      `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `user_id`         INT UNSIGNED NOT NULL,
      `ussd_code_id`    INT UNSIGNED NOT NULL,
      `session_id`      VARCHAR(100) NOT NULL,
      `phone`           VARCHAR(20)  NOT NULL,
      `status`          ENUM('active','completed','timed_out') NOT NULL DEFAULT 'active',
      `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `ended_at`        DATETIME     DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `idx_user` (`user_id`),
      KEY `idx_code` (`ussd_code_id`),
      KEY `idx_session` (`session_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS `ussd_requests` (
      `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `session_id`      INT UNSIGNED NOT NULL,
      `input_text`      VARCHAR(255) DEFAULT NULL,
      `response_text`   TEXT         DEFAULT NULL,
      `status_code`     INT          NOT NULL DEFAULT 200,
      `response_time`   INT          NOT NULL DEFAULT 0,
      `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_rsession` (`session_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

foreach ($sqls as $sql) {
    try {
        DB::execute($sql);
        echo "Executed: " . substr($sql, 0, 50) . "...\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
