<?php
// ==================== DATABASE CONNECTION ====================
// Credentials loaded from .env file — NEVER hardcode secrets here

$DB_HOST = env('DB_HOST', 'localhost');
$DB_NAME = env('DB_NAME', '');
$DB_USER = env('DB_USER', '');
$DB_PASS = env('DB_PASS', '');

if (!$DB_NAME || !$DB_USER) {
    http_response_code(500);
    echo json_encode(['error' => 'Database not configured. Check .env file.']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    // Log the real error server-side, return generic message to client
    error_log('Purex DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}
