<?php
/**
 * Authentication Helpers
 * Bulk SMS System
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/white-label.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ─── Auth Functions ───────────────────────────────────────────────────────────

/**
 * Returns the authenticated user array on success,
 * the string 'locked' if too many recent failures, or false for wrong credentials.
 */
function auth_login($email, $password) {
    $bucket     = 'login:' . strtolower(trim($email));
    $maxAttempts = (int)(get_setting('max_login_attempts') ?: 5);
    $windowMins  = 15;

    // Check lockout — wrapped in try/catch so a missing rate_limits table
    // never breaks login (run the phase6 migration to activate enforcement).
    try {
        $recentFails = (int)DB::queryValue(
            "SELECT COUNT(*) FROM rate_limits
              WHERE bucket = ? AND hit_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)",
            [$bucket, $windowMins]
        );
        if ($recentFails >= $maxAttempts) {
            return 'locked';
        }
    } catch (Exception $e) {
        // rate_limits table not yet created — skip lockout check
    }

    $user = DB::queryOne(
        "SELECT * FROM users WHERE email = ? AND status = 'active' LIMIT 1",
        [$email]
    );

    if ($user && password_verify($password, $user['password_hash'])) {
        // Clear failure history and record successful login
        try {
            DB::execute("DELETE FROM rate_limits WHERE bucket = ?", [$bucket]);
        } catch (Exception $e) {}

        DB::execute("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);

        unset($user['password_hash']);
        // Rotate the session ID to prevent session-fixation attacks.
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['last_activity'] = time();
        return $user;
    }

    // Record failure; periodic cleanup to keep the table lean
    try {
        DB::execute("INSERT INTO rate_limits (bucket, hit_at) VALUES (?, NOW())", [$bucket]);
        if (random_int(1, 100) === 1) {
            DB::execute("DELETE FROM rate_limits WHERE hit_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        }
    } catch (Exception $e) {}

    return false;
}

function auth_logout() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p["path"], $p["domain"], $p["secure"], $p["httponly"]
        );
    }
    session_destroy();
    header('Location: /login.php');
    exit;
}

function auth_user() {
    static $cachedUser  = null;
    static $resolved    = false;

    if ($resolved) {
        return $cachedUser;
    }
    $resolved = true;

    // Check session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        auth_logout();
    }
    if (isset($_SESSION['user']['id'])) {
        $_SESSION['last_activity'] = time();

        // Refresh user data from DB once per request to keep units/status current
        $user = DB::queryOne("SELECT * FROM users WHERE id = ? LIMIT 1", [$_SESSION['user']['id']]);
        if ($user) {
            unset($user['password_hash']);
            $_SESSION['user'] = $user;
            $_SESSION['user_id'] = $user['id'];
            $cachedUser = $user;
            return $user;
        } else {
            auth_logout();
        }
    }
    return null;
}

function is_logged_in() {
    return auth_user() !== null;
}

function current_user() {
    return auth_user();
}

function current_role() {
    return auth_user()['role'] ?? null;
}

/**
 * Require the user to be logged in AND have the given role.
 * Redirects to login if not authenticated, or to dashboard if wrong role.
 */
function require_role(...$roles) {
    $user = auth_user();
    if (!$user) {
        header('Location: /login.php?expired=1');
        exit;
    }
    if (!in_array($user['role'], $roles, true)) {
        switch ($user['role']) {
            case 'admin':    $panel = '/admin/'; break;
            case 'reseller': $panel = '/reseller/'; break;
            case 'client':   $panel = '/client/'; break;
            default:         $panel = '/login.php'; break;
        }
        header("Location: $panel");
        exit;
    }
}

/**
 * Auto-redirect logged-in user to their panel dashboard.
 * Call this on login.php to skip login if already authenticated.
 */
function redirect_if_logged_in() {
    $user = auth_user();
    if ($user) {
        switch ($user['role']) {
            case 'admin':    $dest = '/admin/'; break;
            case 'reseller': $dest = '/reseller/'; break;
            case 'client':   $dest = '/client/'; break;
            default:         $dest = '/login.php'; break;
        }
        header("Location: $dest");
        exit;
    }
}

// ─── Utility ──────────────────────────────────────────────────────────────────

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_verify() {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function validate_csrf($token) {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function sanitize($str) {
    return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES, 'UTF-8');
}

function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function redirect($url, $code = 302) {
    http_response_code($code);
    header("Location: $url");
    exit;
}

/**
 * Return the HTTP Referer only when it is on the same host.
 * Falls back to $default to prevent open-redirect via a crafted Referer header.
 */
function safe_referer(string $default = '/'): string {
    $ref  = $_SERVER['HTTP_REFERER'] ?? '';
    if ($ref === '') return $default;
    $host = parse_url($ref, PHP_URL_HOST);
    if (!$host || $host !== ($_SERVER['HTTP_HOST'] ?? '')) return $default;
    return $ref;
}

function flash_set($type, $message) {
    $_SESSION['flash'] = compact('type', 'message');
}

function flash_get() {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

// ─── Notifications ────────────────────────────────────────────────────────────

/**
 * Create a new notification.
 * @param int|null $userId Specific user ID or NULL for all users (broadcast).
 */
function notify($userId, $title, $message, $type = 'info', $isPopup = false, $imageUrl = null, $isBanner = false) {
    $res = DB::insert(
        "INSERT INTO notifications (user_id, title, message, type, is_popup, is_banner, image_url, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
        [$userId, $title, $message, $type, $isPopup ? 1 : 0, $isBanner ? 1 : 0, $imageUrl]
    );
    return $res ? (int)$res : false;
}

/**
 * Get all unread notifications for a user (including broadcasts not yet read).
 */
function get_unread_notifications($userId) {
    // 1. Get personal notifications not in interactions (or marked not read)
    // 2. Get broadcast (user_id IS NULL) notifications not in interactions (or marked not read)
    $sql = "SELECT n.* FROM notifications n
            LEFT JOIN notification_interactions ni ON n.id = ni.notification_id AND ni.user_id = ?
            WHERE (n.user_id = ? OR n.user_id IS NULL)
            AND (ni.is_read IS NULL OR ni.is_read = 0)
            AND (ni.is_dismissed IS NULL OR ni.is_dismissed = 0)
            ORDER BY n.created_at DESC
            LIMIT 20";
    return DB::query($sql, [$userId, $userId]) ?: [];
}

/**
 * Mark a notification as read for a specific user.
 */
function mark_notification_read($userId, $notificationId) {
    return DB::execute(
        "INSERT INTO notification_interactions (user_id, notification_id, is_read) 
         VALUES (?, ?, 1) 
         ON DUPLICATE KEY UPDATE is_read = 1, interacted_at = NOW()",
        [$userId, $notificationId]
    );
}

/**
 * Dismiss a notification (wont show up in popups or header anymore).
 */
function dismiss_notification($userId, $notificationId) {
    return DB::execute(
        "INSERT INTO notification_interactions (user_id, notification_id, is_dismissed) 
         VALUES (?, ?, 1) 
         ON DUPLICATE KEY UPDATE is_dismissed = 1, interacted_at = NOW()",
        [$userId, $notificationId]
    );
}

/**
 * Get undismissed popup notifications for a user.
 * Returns at most 3, oldest first so they display in creation order.
 */
function get_dashboard_popups($userId) {
    try {
        return DB::query(
            "SELECT n.id, n.title, n.message, n.type, n.image_url
             FROM notifications n
             LEFT JOIN notification_interactions ni
                ON n.id = ni.notification_id AND ni.user_id = ?
             WHERE n.is_popup = 1
               AND (n.user_id = ? OR n.user_id IS NULL)
               AND n.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
               AND (ni.is_dismissed IS NULL OR ni.is_dismissed = 0)
             ORDER BY n.created_at ASC
             LIMIT 3",
            [$userId, $userId]
        ) ?: [];
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get active banner notifications for the dashboard.
 */
function get_dashboard_banners($userId) {
    try {
        return DB::query(
            "SELECT * FROM notifications 
             WHERE is_banner = 1 
             AND (user_id = ? OR user_id IS NULL) 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             ORDER BY created_at DESC",
            [$userId]
        ) ?: [];
    } catch (Exception $e) {
        // Fallback if column is missing (e.g. before migration)
        return [];
    }
}
// ─── API Integration ─────────────────────────────────────────────────────────

/**
 * Generate a new set of API credentials for a user.
 */
function generate_user_api_keys($userId) {
    // Client ID format: SHX + 5-digit padded ID + 4 random hex chars
    $clientId = 'SHX' . str_pad($userId, 5, '0', STR_PAD_LEFT) . strtoupper(bin2hex(random_bytes(2)));
    
    // API Key format: sk_live_ + 32 random hex chars
    $apiKey = 'sk_live_' . bin2hex(random_bytes(16));
    
    return (bool)DB::execute(
        "UPDATE users SET api_client_id = ?, api_key = ? WHERE id = ?",
        [$clientId, $apiKey, $userId]
    );
}

/**
 * Validate API credentials and return the user record if valid.
 */
function validate_api_credentials($clientId, $apiKey) {
    $user = DB::queryOne(
        "SELECT * FROM users WHERE api_client_id = ? AND api_key = ? AND status = 'active' LIMIT 1",
        [$clientId, $apiKey]
    );
    if ($user) {
        unset($user['password_hash']);
        return $user;
    }
    return null;
}
