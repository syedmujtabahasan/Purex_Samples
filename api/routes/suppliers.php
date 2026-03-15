<?php
// ==================== SUPPLIERS ROUTES ====================

// GET /suppliers
if ($method === 'GET' && !$id) {
    $user = require_auth();
    $stmt = $pdo->prepare('SELECT * FROM suppliers ORDER BY name');
    $stmt->execute();
    json_response($stmt->fetchAll());
}

// GET /suppliers/{id}
if ($method === 'GET' && $id) {
    $user = require_auth();
    $id = int_val($id);
    $stmt = $pdo->prepare('SELECT * FROM suppliers WHERE id = ?');
    $stmt->execute([$id]);
    $supplier = $stmt->fetch();
    if (!$supplier) error_response('Supplier not found', 404);
    json_response($supplier);
}

// POST /suppliers
if ($method === 'POST' && !$id) {
    $user = require_auth();
    $data = get_json_body();
    $missing = required_fields($data, ['name', 'phone']);
    if ($missing) error_response("Field '$missing' is required");

    $stmt = $pdo->prepare('INSERT INTO suppliers (name, phone, email, products, address, notes) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        sanitize($data['name']),
        sanitize($data['phone']),
        sanitize($data['email'] ?? ''),
        sanitize($data['products'] ?? ''),
        sanitize($data['address'] ?? ''),
        sanitize($data['notes'] ?? ''),
    ]);
    success_response(['id' => $pdo->lastInsertId()], 'Supplier created');
}

// PUT /suppliers/{id}
if ($method === 'PUT' && $id) {
    $user = require_auth();
    $id = int_val($id);
    $data = get_json_body();

    $fields = [];
    $values = [];
    foreach (['name', 'phone', 'email', 'products', 'address', 'notes'] as $f) {
        if (array_key_exists($f, $data)) {
            $fields[] = "`$f` = ?";
            $values[] = sanitize($data[$f]);
        }
    }
    if (empty($fields)) error_response('No fields to update');

    $values[] = $id;
    $pdo->prepare('UPDATE suppliers SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($values);
    success_response(null, 'Supplier updated');
}

// DELETE /suppliers/{id}
if ($method === 'DELETE' && $id) {
    $user = require_admin();
    $id = int_val($id);
    $pdo->prepare('DELETE FROM suppliers WHERE id = ?')->execute([$id]);
    success_response(null, 'Supplier deleted');
}

error_response('Invalid suppliers endpoint', 404);
