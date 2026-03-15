<?php
// ==================== ORDERS ROUTES ====================

// GET /orders — list all
if ($method === 'GET' && !$id) {
    $user = require_auth();
    $stmt = $pdo->prepare('SELECT * FROM orders ORDER BY created_at DESC');
    $stmt->execute();
    $orders = $stmt->fetchAll();

    // Attach items to each order
    foreach ($orders as &$order) {
        $stmt2 = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
        $stmt2->execute([$order['id']]);
        $order['itemDetails'] = $stmt2->fetchAll();
    }
    json_response($orders);
}

// GET /orders/{id}
if ($method === 'GET' && $id && !$action) {
    $user = require_auth();
    $id = int_val($id);
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    if (!$order) error_response('Order not found', 404);

    $stmt2 = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
    $stmt2->execute([$id]);
    $order['itemDetails'] = $stmt2->fetchAll();
    json_response($order);
}

// POST /orders — create (public, from checkout)
if ($method === 'POST' && !$id) {
    $data = get_json_body();
    $missing = required_fields($data, ['customer_name', 'phone']);
    if ($missing) error_response("Field '$missing' is required");

    // Generate order number
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('UPDATE order_counter SET counter = counter + 1 WHERE id = ?');
    $stmt->execute([1]);
    $stmtC = $pdo->prepare('SELECT counter FROM order_counter WHERE id = ?');
    $stmtC->execute([1]);
    $counter = $stmtC->fetchColumn();
    $orderNumber = 'PX-' . date('Ymd') . '-' . str_pad($counter, 3, '0', STR_PAD_LEFT);

    $items = $data['items'] ?? [];
    $total = int_val($data['total'] ?? 0);
    $itemCount = 0;
    foreach ($items as $item) {
        $itemCount += int_val($item['qty'] ?? 1);
    }

    $stmt = $pdo->prepare('INSERT INTO orders (order_number, customer_name, phone, email, city, address, notes, item_count, total, status, channel, order_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE())');
    $stmt->execute([
        $orderNumber,
        sanitize($data['customer_name']),
        sanitize($data['phone']),
        sanitize($data['email'] ?? ''),
        sanitize($data['city'] ?? ''),
        sanitize($data['address'] ?? ''),
        sanitize($data['notes'] ?? ''),
        $itemCount,
        $total,
        'pending',
        sanitize($data['channel'] ?? 'WhatsApp'),
    ]);
    $orderId = $pdo->lastInsertId();

    // Insert order items
    $stmtItem = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, sku, volume, price, buy_price, qty) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($items as $item) {
        $stmtItem->execute([
            $orderId,
            int_val($item['product_id'] ?? $item['id'] ?? 0),
            sanitize($item['name'] ?? $item['product_name'] ?? ''),
            sanitize($item['sku'] ?? ''),
            sanitize($item['volume'] ?? ''),
            int_val($item['price'] ?? 0),
            int_val($item['buy_price'] ?? $item['buyPrice'] ?? 0),
            int_val($item['qty'] ?? 1),
        ]);
    }

    // Auto-create/update customer
    $phone = sanitize($data['phone']);
    $stmtC = $pdo->prepare('SELECT id FROM customers WHERE phone = ?');
    $stmtC->execute([$phone]);
    $existing = $stmtC->fetch();
    if ($existing) {
        $pdo->prepare('UPDATE customers SET order_count = order_count + 1, total_spent = total_spent + ?, last_order = CURDATE(), name = ?, city = ?, address = ? WHERE id = ?')
            ->execute([$total, sanitize($data['customer_name']), sanitize($data['city'] ?? ''), sanitize($data['address'] ?? ''), $existing['id']]);
    } else {
        $pdo->prepare('INSERT INTO customers (name, phone, email, city, address, order_count, total_spent, first_order, last_order) VALUES (?, ?, ?, ?, ?, 1, ?, CURDATE(), CURDATE())')
            ->execute([sanitize($data['customer_name']), $phone, sanitize($data['email'] ?? ''), sanitize($data['city'] ?? ''), sanitize($data['address'] ?? ''), $total]);
    }

    $pdo->commit();

    json_response([
        'order_number' => $orderNumber,
        'order_id' => $orderId,
        'total' => $total,
    ], 201);
}

// PUT /orders/{id} — update items/details
if ($method === 'PUT' && $id && !$action) {
    $user = require_auth();
    $id = int_val($id);
    $data = get_json_body();

    // Update order fields
    $fields = [];
    $values = [];
    foreach (['customer_name', 'phone', 'email', 'city', 'address', 'notes', 'channel', 'corrected'] as $f) {
        if (array_key_exists($f, $data)) {
            $fields[] = "`$f` = ?";
            $values[] = $f === 'corrected' ? ($data[$f] ? 1 : 0) : sanitize($data[$f]);
        }
    }

    // Update items if provided
    if (isset($data['items'])) {
        // Check if order is confirmed/shipped/delivered — need to adjust stock + daily_sales
        $stmtStatus = $pdo->prepare('SELECT status, channel FROM orders WHERE id = ?');
        $stmtStatus->execute([$id]);
        $orderInfo = $stmtStatus->fetch();
        $isConfirmed = in_array($orderInfo['status'], ['confirmed', 'shipped', 'delivered']);

        if ($isConfirmed) {
            // Get old items to calculate stock difference
            $stmtOld = $pdo->prepare('SELECT product_id, qty FROM order_items WHERE order_id = ?');
            $stmtOld->execute([$id]);
            $oldItems = $stmtOld->fetchAll();

            // Restore stock for old items
            foreach ($oldItems as $oi) {
                if ($oi['product_id']) {
                    $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?')
                        ->execute([$oi['qty'], $oi['product_id']]);
                }
            }

            // Remove old daily_sales for this order
            $pdo->prepare('DELETE FROM daily_sales WHERE order_id = ?')->execute([$id]);
        }

        // Delete old items and insert new
        $pdo->prepare('DELETE FROM order_items WHERE order_id = ?')->execute([$id]);
        $stmtItem = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, sku, volume, price, buy_price, qty) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $total = 0;
        $itemCount = 0;
        foreach ($data['items'] as $item) {
            $qty = int_val($item['qty'] ?? 1);
            $price = int_val($item['price'] ?? 0);
            $buyPrice = int_val($item['buy_price'] ?? $item['buyPrice'] ?? 0);
            $productId = int_val($item['product_id'] ?? $item['id'] ?? 0);
            $productName = sanitize($item['name'] ?? $item['product_name'] ?? '');
            $sku = sanitize($item['sku'] ?? '');
            $volume = sanitize($item['volume'] ?? '');

            $stmtItem->execute([$id, $productId, $productName, $sku, $volume, $price, $buyPrice, $qty]);
            $total += $price * $qty;
            $itemCount += $qty;

            if ($isConfirmed) {
                // Deduct stock for new quantities
                if ($productId) {
                    $pdo->prepare('UPDATE products SET stock = GREATEST(0, stock - ?) WHERE id = ?')
                        ->execute([$qty, $productId]);
                }

                // Re-record daily_sales
                $profit = ($price - $buyPrice) * $qty;
                $pdo->prepare('INSERT INTO daily_sales (order_id, product_id, product_name, sku, volume, category, qty, unit_price, buy_price, total, profit, sale_date, channel, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?)')
                    ->execute([$id, $productId, $productName, $sku, $volume, '', $qty, $price, $buyPrice, $price * $qty, $profit, $orderInfo['channel'] ?? 'WhatsApp', $user['name']]);
            }
        }
        $fields[] = "`total` = ?";
        $values[] = $total;
        $fields[] = "`item_count` = ?";
        $values[] = $itemCount;
    }

    if (array_key_exists('total', $data) && !isset($data['items'])) {
        $fields[] = "`total` = ?";
        $values[] = int_val($data['total']);
    }

    if (!empty($fields)) {
        $values[] = $id;
        $pdo->prepare('UPDATE orders SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($values);
    }

    // Return updated order
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$id]);
    $order = $stmt->fetch();
    $stmt2 = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
    $stmt2->execute([$id]);
    $order['itemDetails'] = $stmt2->fetchAll();
    json_response($order);
}

// PATCH /orders/{id}/status
if ($method === 'PATCH' && $id && $action === 'status') {
    $user = require_auth();
    $id = int_val($id);
    $data = get_json_body();
    $status = $data['status'] ?? '';
    $allowed = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];
    if (!in_array($status, $allowed)) error_response('Invalid status');

    $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
    $stmt->execute([$status, $id]);

    // If confirming, record daily sales and reduce stock
    if ($status === 'confirmed') {
        $stmtOrder = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $stmtOrder->execute([$id]);
        $order = $stmtOrder->fetch();

        $stmtItems = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
        $stmtItems->execute([$id]);
        $items = $stmtItems->fetchAll();

        foreach ($items as $item) {
            // Record daily sale (with order_id for cancellation tracking)
            $profit = ($item['price'] - $item['buy_price']) * $item['qty'];
            $pdo->prepare('INSERT INTO daily_sales (order_id, product_id, product_name, sku, volume, category, qty, unit_price, buy_price, total, profit, sale_date, channel, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?)')
                ->execute([
                    $id,
                    $item['product_id'], $item['product_name'], $item['sku'], $item['volume'],
                    '', $item['qty'], $item['price'], $item['buy_price'],
                    $item['price'] * $item['qty'], $profit,
                    $order['channel'] ?? 'WhatsApp', $user['name']
                ]);

            // Reduce stock
            if ($item['product_id']) {
                $pdo->prepare('UPDATE products SET stock = GREATEST(0, stock - ?) WHERE id = ?')
                    ->execute([$item['qty'], $item['product_id']]);
            }
        }
    }

    // If cancelling, remove daily sales entries and restore stock
    if ($status === 'cancelled') {
        $stmtItems = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
        $stmtItems->execute([$id]);
        $items = $stmtItems->fetchAll();

        // Remove daily sales entries for this order
        $pdo->prepare('DELETE FROM daily_sales WHERE order_id = ?')->execute([$id]);

        // Restore stock
        foreach ($items as $item) {
            if ($item['product_id']) {
                $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?')
                    ->execute([$item['qty'], $item['product_id']]);
            }
        }
    }

    success_response(null, "Order status updated to $status");
}

// DELETE /orders/{id}
if ($method === 'DELETE' && $id) {
    $user = require_admin();
    $id = int_val($id);

    // Check if order was confirmed — restore stock and remove daily sales
    $stmt = $pdo->prepare('SELECT status FROM orders WHERE id = ?');
    $stmt->execute([$id]);
    $order = $stmt->fetch();

    if ($order && in_array($order['status'], ['confirmed', 'shipped', 'delivered'])) {
        // Restore stock from order items
        $stmtItems = $pdo->prepare('SELECT product_id, qty FROM order_items WHERE order_id = ?');
        $stmtItems->execute([$id]);
        $items = $stmtItems->fetchAll();
        foreach ($items as $item) {
            if ($item['product_id']) {
                $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?')
                    ->execute([$item['qty'], $item['product_id']]);
            }
        }
        // Remove associated daily sales entries
        $pdo->prepare('DELETE FROM daily_sales WHERE order_id = ?')->execute([$id]);
    }

    $pdo->prepare('DELETE FROM order_items WHERE order_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$id]);
    success_response(null, 'Order deleted');
}

error_response('Invalid orders endpoint', 404);
