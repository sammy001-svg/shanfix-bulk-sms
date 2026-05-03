-- Shanfix Bulk SMS: ROBUST Setup Script (USSD & WhatsApp)
-- Optimized for cPanel / MariaDB / Older MySQL Versions

-- =============================================================================
-- 1. CORE TABLES (Safe Creation)
-- =============================================================================

-- USSD Codes
CREATE TABLE IF NOT EXISTS `ussd_codes` (
    `id`                INT(11) NOT NULL AUTO_INCREMENT,
    `user_id`           INT(11) NOT NULL,
    `requested_code`    VARCHAR(50)  NOT NULL,
    `type`              ENUM('dedicated', 'shared') DEFAULT 'dedicated',
    `purpose`           TEXT,
    `status`            ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    `reject_reason`     TEXT,
    `created_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- USSD Sessions
CREATE TABLE IF NOT EXISTS `ussd_sessions` (
    `id`                INT(11) NOT NULL AUTO_INCREMENT,
    `user_id`           INT(11) NOT NULL,
    `code_id`           INT(11) NOT NULL,
    `session_id`        VARCHAR(100) NOT NULL,
    `phone`             VARCHAR(20) NOT NULL,
    `status`            ENUM('active', 'completed', 'timed_out') DEFAULT 'active',
    `created_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_session_id` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- USSD Requests
CREATE TABLE IF NOT EXISTS `ussd_requests` (
    `id`                BIGINT(20) NOT NULL AUTO_INCREMENT,
    `user_id`           INT(11) NOT NULL,
    `code_id`           INT(11) NOT NULL,
    `session_id`        VARCHAR(100) NULL,
    `phone`             VARCHAR(20) NULL,
    `input`             TEXT NULL,
    `response`          TEXT NULL,
    `http_status`       SMALLINT(6) DEFAULT 200,
    `duration_ms`       INT(11) DEFAULT 0,
    `created_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- WhatsApp Accounts
CREATE TABLE IF NOT EXISTS `whatsapp_accounts` (
  `id`              INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`         INT(11) NOT NULL,
  `instance_id`     VARCHAR(100) NOT NULL,
  `token`           VARCHAR(255) NOT NULL,
  `status`          ENUM('pending','active','expired','suspended') NOT NULL DEFAULT 'pending',
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_instance` (`user_id`, `instance_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- WhatsApp Chatbots
CREATE TABLE IF NOT EXISTS `whatsapp_chatbots` (
  `id`              INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`         INT(11) NOT NULL,
  `parent_id`       INT(11) DEFAULT NULL,
  `keyword`         VARCHAR(100) NOT NULL,
  `match_type`      ENUM('exact','contains','starts_with') NOT NULL DEFAULT 'exact',
  `response`        TEXT         NOT NULL,
  `media_url`       VARCHAR(255) DEFAULT NULL,
  `is_menu`         TINYINT(1)   NOT NULL DEFAULT 0,
  `is_dynamic`      TINYINT(1)   NOT NULL DEFAULT 0,
  `data_source_table` VARCHAR(100) DEFAULT NULL,
  `trigger_count`   INT(11)      NOT NULL DEFAULT 0,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- WhatsApp Dynamic Data Hub
CREATE TABLE IF NOT EXISTS `whatsapp_custom_data` (
  `id`              INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`         INT(11) NOT NULL,
  `table_name`      VARCHAR(100) NOT NULL,
  `data_key`        VARCHAR(100) NOT NULL,
  `data_value`      LONGTEXT     NOT NULL, 
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- WhatsApp Bot Sessions
CREATE TABLE IF NOT EXISTS `whatsapp_bot_sessions` (
  `id`              INT(11) NOT NULL AUTO_INCREMENT,
  `account_id`      INT(11) NOT NULL,
  `sender_phone`    VARCHAR(20)  NOT NULL,
  `current_menu_id` INT(11)      DEFAULT NULL,
  `last_interaction` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_acc_sender` (`account_id`, `sender_phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =============================================================================
-- 2. SAFE COLUMN UPDATES (Stored Procedure for Compatibility)
-- =============================================================================
DELIMITER //

CREATE PROCEDURE IF NOT EXISTS AddColumnIfMissing(
    IN tableName VARCHAR(64),
    IN colName VARCHAR(64),
    IN colDef VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT * FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = tableName 
        AND COLUMN_NAME = colName
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', tableName, '` ADD `', colName, '` ', colDef);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //

DELIMITER ;

-- Execute safe additions
CALL AddColumnIfMissing('users', 'ussd_balance', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00');
CALL AddColumnIfMissing('users', 'whatsapp_balance', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00');
CALL AddColumnIfMissing('users', 'api_client_id', 'VARCHAR(50) DEFAULT NULL');
CALL AddColumnIfMissing('users', 'api_key', 'VARCHAR(100) DEFAULT NULL');

-- Cleanup
DROP PROCEDURE IF EXISTS AddColumnIfMissing;
