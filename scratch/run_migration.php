<?php
require_once __DIR__ . '/../includes/db.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS `sms_templates` (
      `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `user_id`     INT UNSIGNED NOT NULL,
      `title`       VARCHAR(120) NOT NULL,
      `message`     TEXT         NOT NULL,
      `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_user` (`user_id`),
      CONSTRAINT `fk_template_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    DB::execute($sql);
    echo "SUCCESS: sms_templates table created or already exists.\n";
    
    // Also add the metadata column if missing
    try {
        DB::execute("ALTER TABLE `contacts` ADD COLUMN `metadata` JSON DEFAULT NULL AFTER `email`;");
        echo "SUCCESS: metadata column added to contacts table.\n";
    } catch (Exception $e) {
        echo "INFO: metadata column might already exist: " . $e->getMessage() . "\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
