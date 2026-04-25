<?php
/**
 * Database Configuration & Connection
 * Bulk SMS System
 */

// Load custom config if available
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
}

if (!defined('DB_HOST'))    define('DB_HOST',    getenv('DB_HOST') ?: '127.0.0.1');
if (!defined('DB_NAME'))    define('DB_NAME',    getenv('DB_NAME') ?: 'bulk_sms_system');
if (!defined('DB_USER'))    define('DB_USER',    getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS'))    define('DB_PASS',    getenv('DB_PASS') ?: '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

if (!defined('SITE_NAME'))       define('SITE_NAME',       'Shanfix Technology');
if (!defined('SITE_URL'))        define('SITE_URL',        'http://localhost:8080');
if (!defined('SITE_VERSION'))    define('SITE_VERSION',    '1.0.0');
if (!defined('SESSION_TIMEOUT')) define('SESSION_TIMEOUT', 3600);

class DB {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST, DB_NAME, DB_CHARSET
            );
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                error_log('DB Connection Failed: ' . $e->getMessage());
                die(json_encode(['error' => 'Database connection failed. Please try again.']));
            }
        }
        return self::$instance;
    }

    /** Run a SELECT query and return all rows */
    public static function query(string $sql, array $params = []): array {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Return a single row */
    public static function queryOne(string $sql, array $params = []): ?array {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Execute INSERT/UPDATE/DELETE and return affected rows */
    public static function execute(string $sql, array $params = []): int {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** INSERT and return last insert ID */
    public static function insert(string $sql, array $params = []): string {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return self::getInstance()->lastInsertId();
    }

    public static function beginTransaction(): void  { self::getInstance()->beginTransaction(); }
    public static function commit(): void             { self::getInstance()->commit(); }
    public static function rollback(): void           { self::getInstance()->rollBack(); }
}
