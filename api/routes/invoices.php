<?php
// ==================== SUPPLIER INVOICES ROUTES ====================

// GET /invoices — list with optional supplier_id filter
if ($method === 'GET' && !$id) {
    $user = require_auth();
    $supplierId = $_GET['supplier_id'] ?? null;

    if ($supplierId) {
        $stmt = $pdo->prepare('SELECT * FROM supplier_invoices WHERE supplier_id = ? ORDER BY created_at DESC');
        $stmt->execute([$supplierId]);
    } else {
        $stmt = $pdo->prepare('SELECT * FROM supplier_invoices ORDER BY created_at DESC');
        $stmt->execute();
    }
    $invoices = $stmt->fetchAll();

    // Attach items
    foreach ($invoices as &$inv) {
        $stmt2 = $pdo->prepare('SELECT * FROM supplier_invoice_items WHERE invoice_id = ?');
        $stmt2->execute([$inv['id']]);
        $inv['items'] = $stmt2->fetchAll();
    }
    json_response($invoices);
}

// GET /invoices/{id}
if ($method === 'GET' && $id && !$action) {
    $user = require_auth();
    $id = int_val($id);
    $stmt = $pdo->prepare('SELECT * FROM supplier_invoices WHERE id = ?');
    $stmt->execute([$id]);
    $inv = $stmt->fetch();
    if (!$inv) error_response('Invoice not found', 404);

    $stmt2 = $pdo->prepare('SELECT * FROM supplier_invoice_items WHERE invoice_id = ?');
    $stmt2->execute([$id]);
    $inv['items'] = $stmt2->fetchAll();
    json_response($inv);
}

// POST /invoices — create
if ($method === 'POST' && !$id) {
    $user = require_auth();
    $data = get_json_body();
    $missing = required_fields($data, ['invoice_number', 'invoice_date', 'supplier_id']);
    if ($missing) error_response("Field '$missing' is required");

    $stmt = $pdo->prepare('INSERT INTO supplier_invoices (invoice_number, invoice_date, supplier_id, supplier_name, amount, description, photo_path, status, added_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        sanitize($data['invoice_number']),
        $data['invoice_date'],
        int_val($data['supplier_id']),
        sanitize($data['supplier_name'] ?? ''),
        int_val($data['amount'] ?? 0),
        sanitize($data['description'] ?? ''),
        sanitize($data['photo_path'] ?? ''),
        validate_enum($data['status'] ?? 'draft', ['draft', 'posted']) ? ($data['status'] ?? 'draft') : 'draft',
        $user['name'],
    ]);
    $invId = $pdo->lastInsertId();

    // Insert items
    if (!empty($data['items'])) {
        $stmtItem = $pdo->prepare('INSERT INTO supplier_invoice_items (invoice_id, product_id, description, qty, unit_price, total) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($data['items'] as $item) {
            $qty = int_val($item['qty'] ?? 1);
            $price = int_val($item['unit_price'] ?? $item['rate'] ?? 0);
            $stmtItem->execute([
                $invId,
                int_val($item['product_id'] ?? $item['productId'] ?? 0) ?: null,
                sanitize($item['description'] ?? $item['item'] ?? ''),
                $qty,
                $price,
                $qty * $price,
            ]);
        }
    }

    success_response(['id' => $invId], 'Invoice created');
}

// PUT /invoices/{id} — update
if ($method === 'PUT' && $id && !$action) {
    $user = require_auth();
    $id = int_val($id);

    // Check if posted — only admin can edit posted invoices
    $stmt = $pdo->prepare('SELECT status FROM supplier_invoices WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) error_response('Invoice not found', 404);
    if ($existing['status'] === 'posted' && $user['role'] !== 'admin') {
        error_response('Only admin can edit posted invoices', 403);
    }

    $data = get_json_body();
    $fields = [];
    $values = [];
    foreach (['invoice_number', 'invoice_date', 'supplier_id', 'supplier_name', 'amount', 'description', 'photo_path', 'status'] as $f) {
        if (array_key_exists($f, $data)) {
            $fields[] = "`$f` = ?";
            if (in_array($f, ['supplier_id', 'amount'])) $values[] = int_val($data[$f]);
            else if ($f === 'photo_path') $values[] = sanitize($data[$f] ?? '');
            else $values[] = sanitize($data[$f]);
        }
    }
    $fields[] = "`last_edited_by` = ?";
    $values[] = $user['name'];
    $fields[] = "`last_edited_at` = NOW()";

    $values[] = $id;
    $pdo->prepare('UPDATE supplier_invoices SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($values);

    // Update items if provided
    if (isset($data['items'])) {
        $pdo->prepare('DELETE FROM supplier_invoice_items WHERE invoice_id = ?')->execute([$id]);
        $stmtItem = $pdo->prepare('INSERT INTO supplier_invoice_items (invoice_id, product_id, description, qty, unit_price, total) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($data['items'] as $item) {
            $qty = int_val($item['qty'] ?? 1);
            $price = int_val($item['unit_price'] ?? $item['rate'] ?? 0);
            $stmtItem->execute([$id, int_val($item['product_id'] ?? $item['productId'] ?? 0) ?: null, sanitize($item['description'] ?? $item['item'] ?? ''), $qty, $price, $qty * $price]);
        }
    }

    success_response(null, 'Invoice updated');
}

// PATCH /invoices/{id}/post — mark as posted + add stock
if ($method === 'PATCH' && $id && $action === 'post') {
    $user = require_auth();
    $id = int_val($id);
    $pdo->prepare('UPDATE supplier_invoices SET status = "posted", last_edited_by = ?, last_edited_at = NOW() WHERE id = ?')
        ->execute([$user['name'], $id]);

    // Add stock from line items
    $stmtItems = $pdo->prepare('SELECT * FROM supplier_invoice_items WHERE invoice_id = ?');
    $stmtItems->execute([$id]);
    $items = $stmtItems->fetchAll();
    foreach ($items as $item) {
        $pid = $item['product_id'] ?? null;
        if ($pid) {
            $stmt = $pdo->prepare('SELECT id, stock, capacity FROM products WHERE id = ?');
            $stmt->execute([$pid]);
            $product = $stmt->fetch();
            if ($product) {
                $newStock = $product['stock'] + $item['qty'];
                $newCap = $product['capacity'];
                if ($newStock > $newCap) $newCap = $newStock;
                $pdo->prepare('UPDATE products SET stock = ?, capacity = ? WHERE id = ?')
                    ->execute([$newStock, $newCap, $product['id']]);
            }
        }
    }

    success_response(null, 'Invoice posted — stock updated');
}

// DELETE /invoices/{id}
if ($method === 'DELETE' && $id) {
    $user = require_auth();
    $id = int_val($id);
    $stmt = $pdo->prepare('SELECT status FROM supplier_invoices WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if ($existing && $existing['status'] === 'posted' && $user['role'] !== 'admin') {
        error_response('Only admin can delete posted invoices', 403);
    }

    // If posted, reverse stock additions
    if ($existing && $existing['status'] === 'posted') {
        $stmtItems = $pdo->prepare('SELECT * FROM supplier_invoice_items WHERE invoice_id = ?');
        $stmtItems->execute([$id]);
        $items = $stmtItems->fetchAll();
        foreach ($items as $item) {
            $pid = $item['product_id'] ?? null;
            if ($pid) {
                $pdo->prepare('UPDATE products SET stock = GREATEST(0, stock - ?) WHERE id = ?')
                    ->execute([$item['qty'], $pid]);
            }
        }
    }

    $pdo->prepare('DELETE FROM supplier_invoice_items WHERE invoice_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM supplier_invoices WHERE id = ?')->execute([$id]);
    success_response(null, 'Invoice deleted');
}

error_response('Invalid invoices endpoint', 404);
