<?php
// ==================== SECURITY HEADERS ====================
// Industry-standard headers to protect against common web vulnerabilities

// Prevent MIME-type sniffing
header('X-Content-Type-Options: nosniff');

// Prevent clickjacking
header('X-Frame-Options: DENY');

// XSS protection (legacy browsers)
header('X-XSS-Protection: 1; mode=block');

// Enforce HTTPS for 1 year (enable after confirming HTTPS works)
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// Prevent sending referrer to external sites
header('Referrer-Policy: strict-origin-when-cross-origin');

// Restrict what browser features the API can use
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

// Remove PHP version exposure
header_remove('X-Powered-By');
