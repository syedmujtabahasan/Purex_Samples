<?php
// ==================== RATE LIMITER (file-based) ====================
// Uses /tmp/purex_rate/ directory for tracking requests per IP

function rate_limit($key, $max_requests, $window_seconds) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $dir = sys_get_temp_dir() . '/purex_rate/';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);

    $file = $dir . md5($key . '_' . $ip) . '.json';
    $now = time();
    $data = [];

    if (file_exists($file)) {
        $raw = @file_get_contents($file);
        $data = $raw ? json_decode($raw, true) : [];
        if (!is_array($data)) $data = [];
    }

    // Purge entries outside the window
    $data = array_values(array_filter($data, function($t) use ($now, $window_seconds) {
        return ($now - $t) < $window_seconds;
    }));

    if (count($data) >= $max_requests) {
        $retry_after = $window_seconds - ($now - $data[0]);
        header('Retry-After: ' . $retry_after);
        http_response_code(429);
        echo json_encode([
            'error' => 'Too many requests. Please try again later.',
            'retry_after' => $retry_after
        ]);
        exit;
    }

    $data[] = $now;
    @file_put_contents($file, json_encode($data), LOCK_EX);
}
