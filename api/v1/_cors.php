<?php
/**
 * CORS headers for API v1 endpoints.
 * Included at the top of every api/v1/*.php file before any output.
 *
 * Allowed origins are controlled by the `api_cors_origins` system setting
 * (comma-separated list).  Leave blank to allow any origin (*).
 */
$allowedOrigins = array_filter(array_map('trim', explode(',', get_setting('api_cors_origins', ''))));
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (empty($allowedOrigins)) {
    header('Access-Control-Allow-Origin: *');
} elseif ($origin && in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
} else {
    // Unknown origin — still send headers so browsers get a proper CORS error
    // rather than a network-level failure.
    header('Access-Control-Allow-Origin: ' . ($allowedOrigins[0] ?? '*'));
}

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Client-ID, X-API-Key');
header('Access-Control-Max-Age: 86400');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
