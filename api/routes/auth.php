<?php
// ==================== AUTH ROUTES ====================
$action = $id ?? '';

// GET /auth/csrf — issue a CSRF token (must be called before any POST/PUT/DELETE)
if ($method === 'GET' && $action === 'csrf') {
    $token = csrf_generate();
    json_response(['csrf_token' => $token]);
}

// POST /auth/login — rate limited, issues JWT as HttpOnly cookie
if ($method === 'POST' && $action === 'login') {
    // Rate limit: 5 attempts per 15 minutes per IP
    $login_limit = (int) env('RATE_LIMIT_LOGIN', 5);
    $login_window = (int) env('RATE_LIMIT_LOGIN_WINDOW', 900);
    rate_limit('login', $login_limit, $login_window);

    $data = get_json_body();
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';

    if (!$username || !$password) {
        error_response('Username and password required');
    }

    // Validate input lengths
    if (strlen($username) > 255 || strlen($password) > 255) {
        error_response('Invalid credentials', 401);
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? AND active = 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        error_response('Invalid credentials', 401);
    }

    $permissions = json_decode($user['permissions'], true) ?: [];
    $token = jwt_encode([
        'user_id' => $user['id'],
        'name' => $user['name'],
        'role' => $user['role'],
        'username' => $user['username'],
        'permissions' => $permissions,
    ]);

    // Set JWT as HttpOnly Secure SameSite=Strict cookie — XSS cannot steal this
    set_jwt_cookie($token);

    // Also issue a CSRF token for subsequent state-changing requests
    $csrf = csrf_generate();

    json_response([
        'token' => $token,  // Still returned in body for backward compat / sessionStorage
        'csrf_token' => $csrf,
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'role' => $user['role'],
            'username' => $user['username'],
            'permissions' => $permissions,
        ]
    ]);
}

// POST /auth/logout — clear JWT cookie
if ($method === 'POST' && $action === 'logout') {
    clear_jwt_cookie();
    // Clear CSRF cookie too
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie('purex_csrf', '', [
        'expires'  => time() - 3600,
        'path'     => '/api/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly'  => true,
        'samesite' => 'Strict',
    ]);
    success_response(null, 'Logged out');
}

// GET /auth/me
if ($method === 'GET' && $action === 'me') {
    $user = require_auth();
    json_response(['user' => $user]);
}

error_response('Invalid auth endpoint', 404);
