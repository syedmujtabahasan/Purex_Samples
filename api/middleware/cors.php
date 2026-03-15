<?php
// ==================== CORS — STRICT ORIGIN WHITELIST ====================
// Only allows requests from domains listed in .env ALLOWED_ORIGINS

$allowed_origins_str = env('ALLOWED_ORIGINS', '');
$allowed_origins = array_filter(array_map('trim', explode(',', $allowed_origins_str)));

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($origin && in_array($origin, $allowed_origins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
} else if (empty($origin) && php_sapi_name() === 'cli') {
    // Allow CLI usage (cron, testing)
} else if (empty($origin)) {
    // Same-origin requests (no Origin header) — allowed
    // Server-to-server requests also have no Origin
} else {
    // Origin present but not in whitelist — deny
    http_response_code(403);
    echo json_encode(['error' => 'Origin not allowed']);
    exit;
}

header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
header('Access-Control-Max-Age: 86400');
header('Access-Control-Allow-Credentials: true');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
