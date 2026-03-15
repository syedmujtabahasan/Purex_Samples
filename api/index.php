<?php
// ==================== PUREX API ROUTER ====================

// Production: suppress errors in output, log to file
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// Load environment variables FIRST (before anything that needs them)
require_once __DIR__ . '/helpers/env.php';

// Security headers on every response
require_once __DIR__ . '/middleware/security_headers.php';

// CORS (uses env for allowed origins)
require_once __DIR__ . '/middleware/cors.php';

// Database connection (uses env for credentials)
require_once __DIR__ . '/config/database.php';

// Helpers
require_once __DIR__ . '/helpers/response.php';
require_once __DIR__ . '/helpers/validation.php';

// Auth middleware (JWT via HttpOnly cookie + Bearer fallback)
require_once __DIR__ . '/middleware/auth.php';

// CSRF middleware (double-submit cookie pattern)
require_once __DIR__ . '/middleware/csrf.php';

// Rate limiter
require_once __DIR__ . '/middleware/rate_limit.php';

// Global rate limit: 120 requests per minute per IP
$global_limit = (int) env('RATE_LIMIT_API', 120);
$global_window = (int) env('RATE_LIMIT_API_WINDOW', 60);
rate_limit('global', $global_limit, $global_window);

// Parse URL
$url = isset($_GET['url']) ? trim($_GET['url'], '/') : '';
$method = $_SERVER['REQUEST_METHOD'];
$parts = $url ? explode('/', $url) : [];
$resource = $parts[0] ?? '';
$id = $parts[1] ?? null;
$action = $parts[2] ?? null;

// Enforce CSRF on all authenticated state-changing requests
require_csrf();

// Route to appropriate handler
switch ($resource) {
    case 'auth':
        require __DIR__ . '/routes/auth.php';
        break;
    case 'products':
        require __DIR__ . '/routes/products.php';
        break;
    case 'orders':
        require __DIR__ . '/routes/orders.php';
        break;
    case 'sales':
        require __DIR__ . '/routes/sales.php';
        break;
    case 'transactions':
        require __DIR__ . '/routes/transactions.php';
        break;
    case 'customers':
        require __DIR__ . '/routes/customers.php';
        break;
    case 'suppliers':
        require __DIR__ . '/routes/suppliers.php';
        break;
    case 'invoices':
        require __DIR__ . '/routes/invoices.php';
        break;
    case 'users':
        require __DIR__ . '/routes/users.php';
        break;
    case 'activity':
        require __DIR__ . '/routes/activity.php';
        break;
    case 'contacts':
        require __DIR__ . '/routes/contacts.php';
        break;
    case 'uploads':
        require __DIR__ . '/routes/uploads.php';
        break;
    default:
        json_response(['api' => 'Purex Chemicals API', 'status' => 'running']);
}
