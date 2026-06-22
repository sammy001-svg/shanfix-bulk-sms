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
    private static $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            self::connect();
        }
        return self::$instance;
    }

    private static function connect(): void {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION wait_timeout=28800, SESSION interactive_timeout=28800",
        ];
        try {
            self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('DB Connection Failed: ' . $e->getMessage());
            die(json_encode(['error' => 'Database connection failed. Please try again.']));
        }
    }

    /**
     * Ping the DB and silently reconnect if the connection was dropped.
     * Call this before each batch in long-running background processes so
     * that MySQL's wait_timeout never kills a multi-hour campaign run.
     */
    public static function keepAlive(): void {
        try {
            self::getInstance()->query('SELECT 1');
        } catch (PDOException $e) {
            self::$instance = null;
            self::connect();
        }
    }

    /** Execute a statement, reconnecting once on "MySQL server has gone away". */
    private static function run(string $sql, array $params, string $mode) {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $stmt = self::getInstance()->prepare($sql);
                $stmt->execute($params);
                return $stmt;
            } catch (PDOException $e) {
                $gone = str_contains($e->getMessage(), 'gone away')
                     || str_contains($e->getMessage(), 'Lost connection')
                     || $e->errorInfo[1] === 2006;
                if ($gone && $attempt === 0) {
                    self::$instance = null;
                    self::connect();
                    continue;
                }
                throw $e;
            }
        }
    }

    /** Run a SELECT query and return all rows */
    public static function query($sql, $params = []) {
        return self::run($sql, $params, 'query')->fetchAll();
    }

    /** Return a single row */
    public static function queryOne($sql, $params = []) {
        $row = self::run($sql, $params, 'queryOne')->fetch();
        return $row ?: null;
    }

    /** Return a single value from the first column of the first row */
    public static function queryValue($sql, $params = []) {
        return self::run($sql, $params, 'queryValue')->fetchColumn();
    }

    /** Execute INSERT/UPDATE/DELETE and return affected rows */
    public static function execute($sql, $params = []) {
        return self::run($sql, $params, 'execute')->rowCount();
    }

    /** INSERT and return last insert ID */
    public static function insert($sql, $params = []) {
        self::run($sql, $params, 'insert');
        return self::getInstance()->lastInsertId();
    }

    public static function beginTransaction() { self::getInstance()->beginTransaction(); }
    public static function commit()           { self::getInstance()->commit(); }
    public static function rollback()         { self::getInstance()->rollBack(); }
}

/**
 * Global Helper: Get system setting by key.
 * All settings are loaded in one SELECT on the first call and cached
 * in a static array for the lifetime of the request, so subsequent
 * calls are pure PHP array lookups with no DB round-trip.
 */
function get_setting($key, $default = null) {
    static $cache = null;
    if ($cache === null) {
        try {
            $rows  = DB::query("SELECT `key`, `value` FROM system_settings");
            $cache = array_column($rows, 'value', 'key');
        } catch (Exception $e) {
            $cache = [];
        }
    }
    return $cache[$key] ?? $default;
}
