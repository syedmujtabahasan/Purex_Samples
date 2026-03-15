<?php
// ==================== CUSTOMERS ROUTES ====================

// GET /customers
if ($method === 'GET' && !$id) {
    $user = require_auth();
    $stmt = $pdo->prepare('SELECT * FROM customers ORDER BY last_order DESC, name ASC');
    $stmt->execute();
    json_response($stmt->fetchAll());
}

// GET /customers/{id}
if ($method === 'GET' && $id) {
    $user = require_auth();
    $id = int_val($id);
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
    $stmt->execute([$id]);
    $customer = $stmt->fetch();
    if (!$customer) error_response('Customer not found', 404);
    json_response($customer);
}

// POST /customers
if ($method === 'POST' && !$id) {
    $user = require_auth();
    $data = get_json_body();
    $missing = required_fields($data, ['name']);
    if ($missing) error_response("Field '$missing' is required");

    $stmt = $pdo->prepare('INSERT INTO customers (name, phone, email, city, area, address, company, type, notes, order_count, total_spent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        sanitize($data['name']),
        sanitize($data['phone']),
        sanitize($data['email'] ?? ''),
        sanitize($data['city'] ?? ''),
        sanitize($data['area'] ?? ''),
        sanitize($data['address'] ?? ''),
        sanitize($data['company'] ?? ''),
        sanitize($data['type'] ?? 'Walk-in'),
        sanitize($data['notes'] ?? ''),
        int_val($data['order_count'] ?? 0),
        int_val($data['total_spent'] ?? $data['totalSpent'] ?? 0),
    ]);
    success_response(['id' => $pdo->lastInsertId()], 'Customer created');
}

// PUT /customers/{id}
if ($method === 'PUT' && $id) {
    $user = require_auth();
    $id = int_val($id);
    $data = get_json_body();

    $fields = [];
    $values = [];
    foreach (['name', 'phone', 'email', 'city', 'area', 'address', 'company', 'type', 'notes', 'order_count', 'total_spent'] as $f) {
        if (array_key_exists($f, $data)) {
            $fields[] = "`$f` = ?";
            $values[] = in_array($f, ['order_count', 'total_spent']) ? int_val($data[$f]) : sanitize($data[$f]);
        }
    }
    if (empty($fields)) error_response('No fields to update');

    $values[] = $id;
    $pdo->prepare('UPDATE customers SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($values);
    success_response(null, 'Customer updated');
}

// DELETE /customers/{id}
if ($method === 'DELETE' && $id) {
    $user = require_admin();
    $id = int_val($id);
    $pdo->prepare('DELETE FROM customers WHERE id = ?')->execute([$id]);
    success_response(null, 'Customer deleted');
}

error_response('Invalid customers endpoint', 404);
