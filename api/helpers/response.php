<?php
// ==================== RESPONSE HELPERS ====================
// All JSON output goes through escape_output() to prevent stored XSS.

/**
 * Recursively escape all string values in an array/object before JSON output.
 * Prevents stored XSS — even if malicious data made it into the database,
 * it will be escaped before reaching the browser.
 */
function escape_output($data) {
    if (is_string($data)) {
        return htmlspecialchars($data, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    if (is_array($data)) {
        $escaped = [];
        foreach ($data as $key => $value) {
            $escaped[$key] = escape_output($value);
        }
        return $escaped;
    }
    // int, float, bool, null — pass through unchanged
    return $data;
}

/**
 * Safe redirect — prevents open redirect vulnerabilities.
 *
 * Rules enforced:
 *   1. Path must be in the ALLOWED_REDIRECTS whitelist, OR
 *   2. Path must be strictly relative (starts with /, no protocol, no //)
 *
 * If validation fails, redirects to the fallback (default: /).
 * This function should be used instead of raw header('Location: ...').
 */
function safe_redirect($path, $fallback = '/') {
    // Allowlist of known safe destinations
    $allowed = [
        '/login.html',
        '/admin.html',
        '/shop.html',
        '/index.html',
        '/checkout.html',
        '/contact.html',
        '/about.html',
        '/sale.html',
        '/product.html',
        '/products/index.html',
        '/suppliers.html',
        '/customers.html',
        '/users.html',
        '/activity.html',
        '/audit.html',
        '/',
    ];

    // Check allowlist first (exact match)
    if (in_array($path, $allowed, true)) {
        header('Location: ' . $path);
        exit;
    }

    // Validate as strictly relative path:
    // - Must start with /
    // - Must NOT start with // (protocol-relative URL → open redirect)
    // - Must NOT contain :// (absolute URL → open redirect)
    // - Must NOT contain backslashes (bypass attempt)
    // - Must NOT contain newlines (header injection)
    // - Must NOT contain @ (user-info URL → redirect to attacker.com)
    if (
        is_string($path) &&
        strlen($path) > 0 &&
        $path[0] === '/' &&
        !preg_match('#^//|://|[\\\\@\r\n\x00]#', $path)
    ) {
        header('Location: ' . $path);
        exit;
    }

    // Validation failed — redirect to safe fallback
    error_log('Purex safe_redirect blocked: ' . substr($path, 0, 200));
    header('Location: ' . $fallback);
    exit;
}

function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(escape_output($data), JSON_UNESCAPED_UNICODE);
    exit;
}

function error_response($message, $code = 400) {
    json_response(['error' => $message], $code);
}

function success_response($data = null, $message = 'OK') {
    $resp = ['success' => true, 'message' => $message];
    if ($data !== null) $resp['data'] = $data;
    json_response($resp);
}
