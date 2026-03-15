<?php
// ==================== PRODUCTS ROUTES ====================

// GET /products — list all (public)
if ($method === 'GET' && !$id) {
    $stmt = $pdo->prepare('SELECT * FROM products ORDER BY id');
    $stmt->execute();
    json_response($stmt->fetchAll());
}

// GET /products/{id}
if ($method === 'GET' && $id && !$action) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([int_val($id)]);
    $product = $stmt->fetch();
    if (!$product) error_response('Product not found', 404);
    json_response($product);
}

// POST /products — create
if ($method === 'POST' && !$id) {
    $user = require_auth();
    $data = get_json_body();
    $missing = required_fields($data, ['name', 'sku', 'category', 'price']);
    if ($missing) error_response("Field '$missing' is required");

    $stmt = $pdo->prepare('INSERT INTO products (name, sku, volume, category, price, buy_price, stock, capacity, status, on_sale, sale_price, description, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        sanitize($data['name']),
        sanitize($data['sku']),
        sanitize($data['volume'] ?? ''),
        sanitize($data['category']),
        int_val($data['price']),
        int_val($data['buy_price'] ?? 0),
        int_val($data['stock'] ?? 0),
        int_val($data['capacity'] ?? 100),
        validate_enum($data['status'] ?? 'active', ['active', 'inactive']) ? $data['status'] : 'active',
        ($data['on_sale'] ?? 0) ? 1 : 0,
        int_val($data['sale_price'] ?? 0),
        sanitize($data['description'] ?? ''),
        sanitize($data['image'] ?? ''),
    ]);

    $newId = $pdo->lastInsertId();
    $stmt2 = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt2->execute([$newId]);
    json_response($stmt2->fetch(), 201);
}

// PUT /products/{id} — update
if ($method === 'PUT' && $id && !$action) {
    $user = require_auth();
    $data = get_json_body();

    $id = int_val($id);

    // Check editor capacity restrictions
    if ($user['role'] !== 'admin' && isset($data['stock'])) {
        $stmt = $pdo->prepare('SELECT capacity FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $existing = $stmt->fetch();
        if ($existing && int_val($data['stock']) > $existing['capacity']) {
            error_response('Stock (' . int_val($data['stock']) . ') exceeds capacity (' . $existing['capacity'] . '). Contact admin to increase capacity.', 403);
        }
    }

    // Editors cannot change capacity
    if ($user['role'] !== 'admin') {
        unset($data['capacity']);
    }

    $fields = [];
    $values = [];
    $allowed = ['name', 'sku', 'volume', 'category', 'price', 'buy_price', 'stock', 'capacity', 'status', 'on_sale', 'sale_price', 'description', 'image'];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $data)) {
            $fields[] = "`$f` = ?";
            $values[] = in_array($f, ['price', 'buy_price', 'stock', 'capacity', 'sale_price', 'on_sale']) ? int_val($data[$f]) : sanitize($data[$f]);
        }
    }

    if (empty($fields)) error_response('No fields to update');

    $values[] = $id;
    $stmt = $pdo->prepare('UPDATE products SET ' . implode(', ', $fields) . ' WHERE id = ?');
    $stmt->execute($values);

    $stmt2 = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt2->execute([$id]);
    json_response($stmt2->fetch());
}

// DELETE /products/{id}
if ($method === 'DELETE' && $id && !$action) {
    $user = require_auth();
    if ($user['role'] !== 'admin') {
        $perms = $user['permissions'] ?? [];
        if (empty($perms['deleteProducts'])) error_response('No permission to delete products', 403);
    }
    $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
    $stmt->execute([int_val($id)]);
    success_response(null, 'Product deleted');
}

// PATCH /products/{id}/toggle-sale
if ($method === 'PATCH' && $id && $action === 'toggle-sale') {
    $user = require_auth();
    $data = get_json_body();
    $stmt = $pdo->prepare('UPDATE products SET on_sale = ?, sale_price = ? WHERE id = ?');
    $stmt->execute([$data['on_sale'] ? 1 : 0, int_val($data['sale_price'] ?? 0), int_val($id)]);
    success_response(null, 'Sale toggled');
}

// PATCH /products/{id}/toggle-status
if ($method === 'PATCH' && $id && $action === 'toggle-status') {
    $user = require_auth();
    $data = get_json_body();
    $stmt = $pdo->prepare('UPDATE products SET status = ? WHERE id = ?');
    $status = $data['status'] ?? 'active';
    if (!validate_enum($status, ['active', 'inactive'])) error_response('Invalid status value');
    $stmt->execute([$status, int_val($id)]);
    success_response(null, 'Status toggled');
}

error_response('Invalid products endpoint', 404);
