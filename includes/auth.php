<?php
/**
 * Authentication Helpers
 * Bulk SMS System
 */

require_once __DIR__ . '/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'path'     => '/',
        'secure'   => false, // set true in production over HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ─── Auth Functions ───────────────────────────────────────────────────────────

function auth_login(string $email, string $password): array|false {
    $user = DB::queryOne(
        "SELECT * FROM users WHERE email = ? AND status = 'active' LIMIT 1",
        [$email]
    );
    if ($user && password_verify($password, $user['password_hash'])) {
        // Update last login
        DB::execute("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);

        // Store in session (never store password_hash)
        unset($user['password_hash']);
        $_SESSION['user'] = $user;
        $_SESSION['user_id'] = $user['id']; // Set for compatibility
        $_SESSION['last_activity'] = time();
        return $user;
    }
    return false;
}

function auth_logout(): void {
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

function auth_user(): ?array {
    // Check session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        auth_logout();
    }
    if (isset($_SESSION['user']['id'])) {
        $_SESSION['last_activity'] = time();
        
        // Refresh user data from DB to ensure units and status are always up-to-date
        $user = DB::queryOne("SELECT * FROM users WHERE id = ? LIMIT 1", [$_SESSION['user']['id']]);
        if ($user) {
            unset($user['password_hash']);
            $_SESSION['user'] = $user;
            $_SESSION['user_id'] = $user['id'];
            return $user;
        } else {
            auth_logout();
        }
    }
    return null;
}

function is_logged_in(): bool {
    return auth_user() !== null;
}

function current_user(): ?array {
    return auth_user();
}

function current_role(): ?string {
    return auth_user()['role'] ?? null;
}

/**
 * Require the user to be logged in AND have the given role.
 * Redirects to login if not authenticated, or to dashboard if wrong role.
 */
function require_role(string ...$roles): void {
    $user = auth_user();
    if (!$user) {
        header('Location: /login.php?expired=1');
        exit;
    }
    if (!in_array($user['role'], $roles, true)) {
        $panel = match ($user['role']) {
            'admin'    => '/admin/',
            'reseller' => '/reseller/',
            'client'   => '/client/',
            default    => '/login.php',
        };
        header("Location: $panel");
        exit;
    }
}

/**
 * Auto-redirect logged-in user to their panel dashboard.
 * Call this on login.php to skip login if already authenticated.
 */
function redirect_if_logged_in(): void {
    $user = auth_user();
    if ($user) {
        $dest = match ($user['role']) {
            'admin'    => '/admin/',
            'reseller' => '/reseller/',
            'client'   => '/client/',
            default    => '/login.php',
        };
        header("Location: $dest");
        exit;
    }
}

// ─── Utility ──────────────────────────────────────────────────────────────────

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_verify(): bool {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function validate_csrf(string $token): bool {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function sanitize(string $str): string {
    return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES, 'UTF-8');
}

function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function redirect(string $url, int $code = 302): void {
    http_response_code($code);
    header("Location: $url");
    exit;
}

function flash_set(string $type, string $message): void {
    $_SESSION['flash'] = compact('type', 'message');
}

function flash_get(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}
