<?php
// ==================== CSRF PROTECTION ====================
// Double-submit cookie pattern — works with SPA/AJAX without server-side sessions.
//
// Flow:
// 1. GET /auth/csrf → sets encrypted HttpOnly cookie + returns token in JSON body
// 2. Frontend stores the JSON token and sends it as X-CSRF-Token header on POST/PUT/PATCH/DELETE
// 3. Server compares the header value against the cookie value
//
// Why double-submit works: an attacker on evil.com can cause the browser to SEND
// the cookie automatically, but cannot READ the cookie (HttpOnly + SameSite=Strict)
// and therefore cannot supply the matching X-CSRF-Token header.

define('CSRF_COOKIE_NAME', 'purex_csrf');
define('CSRF_HEADER_NAME', 'X-CSRF-Token');
define('CSRF_EXPIRY', 14400); // 4 hours, matches JWT

/**
 * Generate a new CSRF token pair (cookie + returnable token).
 * Uses HMAC so the cookie and header values are cryptographically linked
 * but an attacker cannot forge one from the other without the server secret.
 */
function csrf_generate() {
    $raw = bin2hex(random_bytes(32));
    $signed = hash_hmac('sha256', $raw, JWT_SECRET);

    // Set HttpOnly cookie — browser sends it automatically
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie(CSRF_COOKIE_NAME, $signed, [
        'expires'  => time() + CSRF_EXPIRY,
        'path'     => '/api/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly'  => true,
        'samesite' => 'Strict',
    ]);

    // Return the raw token — frontend must send this as X-CSRF-Token header
    return $raw;
}

/**
 * Verify the CSRF token on state-changing requests.
 * Compares HMAC(header_value, secret) === cookie_value.
 */
function csrf_verify() {
    $cookie_val = $_COOKIE[CSRF_COOKIE_NAME] ?? '';
    $header_val = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if (!$cookie_val || !$header_val) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF token missing. Fetch /auth/csrf first.']);
        exit;
    }

    $expected = hash_hmac('sha256', $header_val, JWT_SECRET);
    if (!hash_equals($expected, $cookie_val)) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF token invalid or expired.']);
        exit;
    }
}

/**
 * Call this on every state-changing route (POST/PUT/PATCH/DELETE).
 * Skips CSRF check for:
 *   - Public POST endpoints (checkout order creation, contact form) where no auth cookie exists
 *   - OPTIONS preflight
 */
function require_csrf() {
    $method = $_SERVER['REQUEST_METHOD'];

    // Only enforce on state-changing methods
    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) return;

    // Skip for unauthenticated public endpoints (they don't have the CSRF cookie)
    // Those endpoints are: POST /orders (checkout), POST /contacts
    // They are identified by having no purex_jwt cookie
    if (empty($_COOKIE['purex_jwt'])) return;

    csrf_verify();
}
