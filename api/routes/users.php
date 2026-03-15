<?php
// ==================== USERS ROUTES (ADMIN ONLY) ====================

// GET /users
if ($method === 'GET' && !$id) {
    $user = require_admin();
    $stmt = $pdo->prepare('SELECT id, username, name, role, active, permissions, created_at FROM users ORDER BY id');
    $stmt->execute();
    $users = $stmt->fetchAll();
    foreach ($users as &$u) {
        $u['permissions'] = json_decode($u['permissions'], true) ?: [];
    }
    json_response($users);
}

// POST /users — create
if ($method === 'POST' && !$id) {
    $user = require_admin();
    $data = get_json_body();
    $missing = required_fields($data, ['username', 'password', 'name']);
    if ($missing) error_response("Field '$missing' is required");

    // Check duplicate username
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([sanitize($data['username'])]);
    if ($stmt->fetch()) error_response('Username already exists');

    $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, name, role, active, permissions) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        sanitize($data['username']),
        password_hash($data['password'], PASSWORD_BCRYPT),
        sanitize($data['name']),
        validate_enum($data['role'] ?? 'editor', ['admin', 'editor']) ? ($data['role'] ?? 'editor') : 'editor',
        ($data['active'] ?? 1) ? 1 : 0,
        json_encode($data['permissions'] ?? []),
    ]);
    success_response(['id' => $pdo->lastInsertId()], 'User created');
}

// PUT /users/{id} — update
if ($method === 'PUT' && $id) {
    $user = require_admin();
    $id = int_val($id);
    $data = get_json_body();

    $fields = [];
    $values = [];

    if (isset($data['name'])) { $fields[] = "`name` = ?"; $values[] = sanitize($data['name']); }
    if (isset($data['username'])) { $fields[] = "`username` = ?"; $values[] = sanitize($data['username']); }
    if (isset($data['role'])) {
        if (!validate_enum($data['role'], ['admin', 'editor'])) error_response('Invalid role');
        $fields[] = "`role` = ?"; $values[] = $data['role'];
    }
    if (isset($data['active'])) { $fields[] = "`active` = ?"; $values[] = $data['active'] ? 1 : 0; }
    if (isset($data['permissions'])) { $fields[] = "`permissions` = ?"; $values[] = json_encode($data['permissions']); }
    if (!empty($data['password'])) { $fields[] = "`password_hash` = ?"; $values[] = password_hash($data['password'], PASSWORD_BCRYPT); }

    if (empty($fields)) error_response('No fields to update');

    $values[] = $id;
    $pdo->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($values);
    success_response(null, 'User updated');
}

// DELETE /users/{id}
if ($method === 'DELETE' && $id) {
    $user = require_admin();
    $id = int_val($id);
    if ($id == $user['user_id']) error_response('Cannot delete yourself');
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    success_response(null, 'User deleted');
}

// PATCH /users/{id}/toggle — enable/disable
if ($method === 'PATCH' && $id && $action === 'toggle') {
    $user = require_admin();
    $id = int_val($id);
    $pdo->prepare('UPDATE users SET active = NOT active WHERE id = ?')->execute([$id]);
    success_response(null, 'User toggled');
}

error_response('Invalid users endpoint', 404);
