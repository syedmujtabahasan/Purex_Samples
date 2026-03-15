<?php
// ==================== APP CONSTANTS ====================
// Secrets loaded from .env — NEVER hardcode here

define('JWT_SECRET', env('JWT_SECRET', ''));
define('JWT_EXPIRY', (int) env('JWT_EXPIRY', 14400)); // 4 hours default
define('UPLOAD_DIR', __DIR__ . '/../../uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

// Validate JWT secret is configured
if (!JWT_SECRET || JWT_SECRET === 'CHANGE_ME_TO_A_RANDOM_64_CHAR_STRING_use_openssl_rand_hex_32') {
    error_log('Purex CRITICAL: JWT_SECRET not configured in .env');
    // Don't expose this to client, but log it
}
