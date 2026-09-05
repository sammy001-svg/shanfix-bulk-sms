<?php
/**
 * JSON fatal guard — Shanfix Technology
 *
 * AJAX endpoints that die on an uncaught exception or a PHP fatal emit an HTML
 * error page. The browser's response.json() then throws, and the UI shows a
 * useless "An unexpected error occurred" while the real cause is invisible.
 *
 * Calling json_fatal_guard() makes such a request answer with JSON instead, so
 * the caller always gets {success:false, error:"..."} and the real reason lands
 * in the log.
 *
 * Usage, before any work:
 *     require_once __DIR__ . '/../helpers/json-fatal-guard.php';
 *     json_fatal_guard(isset($_POST['ajax']));
 */

/**
 * Write a diagnostic line to tmp/ajax_errors.log.
 */
function json_fatal_log(string $msg): void {
    $dir = __DIR__ . '/../../tmp';
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    @file_put_contents($dir . '/ajax_errors.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
    error_log('AJAX fatal: ' . $msg);
}

/**
 * Turn a low-level failure into something an operator can act on.
 * A missing column almost always means a migration has not been run.
 */
function json_fatal_hint(string $raw): string {
    if (stripos($raw, 'gateway_ref') !== false) {
        return 'Database is missing the gateway_ref column. Run database/kopokopo_autocredit_migration.sql.';
    }
    if (stripos($raw, 'dlr_status') !== false) {
        return 'Database is missing the dlr_status column. Run database/dlr_status_migration.sql.';
    }
    if (preg_match('/Unknown column .([a-z_]+)./i', $raw, $m)) {
        return "Database is missing the '{$m[1]}' column — a migration in database/ has not been run yet.";
    }
    if (stripos($raw, "doesn't exist") !== false || stripos($raw, 'Base table') !== false) {
        return 'A required database table is missing — a migration in database/ has not been run yet.';
    }
    return 'A server error occurred. Check tmp/ajax_errors.log for details.';
}

/**
 * Install JSON-emitting handlers for uncaught exceptions and fatal errors.
 *
 * @param bool $active Pass false for a normal form post so redirects still work.
 */
function json_fatal_guard(bool $active = true): void {
    if (!$active) return;

    set_exception_handler(function (Throwable $e): void {
        $raw = get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
        json_fatal_log($raw);

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        echo json_encode(['success' => false, 'error' => json_fatal_hint($e->getMessage())]);
        exit;
    });

    register_shutdown_function(function (): void {
        $err = error_get_last();
        if (!$err || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }
        $raw = $err['message'] . ' in ' . $err['file'] . ':' . $err['line'];
        json_fatal_log($raw);

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        echo json_encode(['success' => false, 'error' => json_fatal_hint($err['message'])]);
    });
}
