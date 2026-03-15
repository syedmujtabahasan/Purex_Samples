<?php
// ==================== AUTH MIDDLEWARE ====================
// JWT issued as HttpOnly Secure SameSite=Strict cookie.
// Also accepts Bearer header as fallback for API-only clients.

require_once __DIR__ . '/../config/constants.php';

// ---- JWT encode/decode ----

function jwt_encode($payload) {
    $header = base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
    $payload['exp'] = time() + JWT_EXPIRY;
    $payload['iat'] = time();
    $p = base64url_encode(json_encode($payload));
    $sig = base64url_encode(hash_hmac('sha256', "$header.$p", JWT_SECRET, true));
    return "$header.$p.$sig";
}

function jwt_decode($token) {
    if (!$token || !is_string($token)) return null;
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;

    // Validate each part is valid base64url (no special chars that could cause issues)
    foreach ($parts as $part) {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $part)) return null;
    }

    $sig = base64url_encode(hash_hmac('sha256', "$parts[0].$parts[1]", JWT_SECRET, true));
    if (!hash_equals($sig, $parts[2])) return null;

    $payload = json_decode(base64url_decode($parts[1]), true);
    if (!$payload || !isset($payload['exp']) || $payload['exp'] < time()) return null;

    return $payload;
}

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data) {
    return base64_decode(strtr($data, '-_', '+/'));
}

// ---- Cookie helpers ----

/**
 * Set the JWT as an HttpOnly, Secure, SameSite=Strict cookie.
 * This prevents JavaScript from ever reading the token (XSS-proof).
 */
function set_jwt_cookie($token) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie('purex_jwt', $token, [
        'expires'  => time() + JWT_EXPIRY,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly'  => true,
        'samesite' => 'Strict',
    ]);
}

/**
 * Clear the JWT cookie on logout.
 */
function clear_jwt_cookie() {
    setcookie('purex_jwt', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'domain'   => '',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly'  => true,
        'samesite' => 'Strict',
    ]);
}

// ---- Auth extraction ----

/**
 * Extract the authenticated user from:
 *   1. HttpOnly cookie (primary — XSS-proof)
 *   2. Authorization: Bearer header (fallback for API clients)
 */
function get_auth_user() {
    // 1. Try HttpOnly cookie first (most secure)
    if (!empty($_COOKIE['purex_jwt'])) {
        $payload = jwt_decode($_COOKIE['purex_jwt']);
        if ($payload) return $payload;
    }

    // 2. Fallback: Authorization header (for API clients / mobile)
    $header = '';
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $header = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if (preg_match('/^Bearer\s+([A-Za-z0-9_.\-]+)$/', $header, $m)) {
        return jwt_decode($m[1]);
    }
    return null;
}

function require_auth() {
    $user = get_auth_user();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        exit;
    }
    return $user;
}

function require_admin() {
    $user = require_auth();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        exit;
    }
    return $user;
}
