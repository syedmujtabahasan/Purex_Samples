<?php
// ==================== ACTIVITY LOG ROUTES ====================

// GET /activity
if ($method === 'GET' && !$id) {
    $user = require_auth();
    $limit = int_val($_GET['limit'] ?? 500);
    if ($limit > 1000) $limit = 1000;
    if ($limit < 1) $limit = 1;

    $stmt = $pdo->prepare('SELECT * FROM activity_log ORDER BY created_at DESC LIMIT ?');
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    json_response($stmt->fetchAll());
}

// POST /activity — log an action
if ($method === 'POST' && !$id) {
    $user = require_auth();
    $data = get_json_body();

    $stmt = $pdo->prepare('INSERT INTO activity_log (user_name, user_role, action, detail) VALUES (?, ?, ?, ?)');
    $stmt->execute([
        sanitize($user['name']),
        sanitize($user['role']),
        sanitize($data['action'] ?? ''),
        sanitize($data['detail'] ?? ''),
    ]);
    success_response(null, 'Logged');
}

// DELETE /activity — clear all (admin only)
if ($method === 'DELETE' && !$id) {
    $user = require_admin();
    $pdo->prepare('DELETE FROM activity_log WHERE 1=1')->execute();
    success_response(null, 'Activity log cleared');
}

error_response('Invalid activity endpoint', 404);
