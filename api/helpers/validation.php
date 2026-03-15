<?php
// ==================== INPUT VALIDATION & SANITIZATION ====================

/**
 * Sanitize a string value for safe storage and output.
 * - Trims whitespace
 * - Strips HTML/JS tags
 * - Encodes special chars for XSS prevention
 * Note: SQL injection is handled by PDO prepared statements, not this function.
 */
function sanitize($value) {
    if (is_string($value)) {
        $value = trim($value);
        $value = strip_tags($value);
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    if (is_int($value) || is_float($value)) return $value;
    if (is_bool($value)) return $value;
    if (is_null($value)) return '';
    return '';
}

/**
 * Check that all required fields exist and are non-empty strings.
 * Returns the name of the first missing field, or null if all present.
 */
function required_fields($data, $fields) {
    foreach ($fields as $f) {
        if (!isset($data[$f]) || (is_string($data[$f]) && trim($data[$f]) === '')) {
            return $f;
        }
    }
    return null;
}

/**
 * Parse JSON body from request. Returns empty array on failure.
 */
function get_json_body() {
    $raw = file_get_contents('php://input');
    if (!$raw || strlen($raw) > 1048576) return []; // max 1MB body
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Safely cast to integer with default fallback.
 */
function int_val($v, $default = 0) {
    if (is_int($v)) return $v;
    if (is_numeric($v)) return intval($v);
    return $default;
}

/**
 * Validate a date string matches YYYY-MM-DD format.
 */
function validate_date($date) {
    if (!is_string($date)) return false;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * Validate a value is in an allowed list.
 */
function validate_enum($value, $allowed) {
    return in_array($value, $allowed, true);
}

/**
 * Validate a phone number (basic — digits, dashes, spaces, plus).
 */
function validate_phone($phone) {
    return (bool) preg_match('/^[\d\s\-\+\(\)]{7,20}$/', $phone);
}

/**
 * Validate an email address.
 */
function validate_email($email) {
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}
