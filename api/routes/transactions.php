<?php
// ==================== SALE TRANSACTIONS (POS) ====================

// GET /transactions — list with optional date filter
if ($method === 'GET' && !$id) {
    $user = require_auth();
    $from = $_GET['from'] ?? date('Y-m-d', strtotime('-90 days'));
    $to = $_GET['to'] ?? date('Y-m-d');
    $customerId = $_GET['customer_id'] ?? null;

    if (!validate_date($from)) $from = date('Y-m-d', strtotime('-90 days'));
    if (!validate_date($to)) $to = date('Y-m-d');

    if ($customerId) {
        $stmt = $pdo->prepare('SELECT * FROM sale_transactions WHERE customer_id = ? ORDER BY created_at DESC');
        $stmt->execute([$customerId]);
    } else {
        $stmt = $pdo->prepare('SELECT * FROM sale_transactions WHERE sale_date BETWEEN ? AND ? ORDER BY created_at DESC');
        $stmt->execute([$from, $to]);
    }
    $transactions = $stmt->fetchAll();

    // Attach items for each transaction
    foreach ($transactions as &$txn) {
        $itemStmt = $pdo->prepare('SELECT * FROM daily_sales WHERE transaction_id = ?');
        $itemStmt->execute([$txn['id']]);
        $txn['items'] = $itemStmt->fetchAll();
    }

    json_response($transactions);
}

// GET /transactions/{id}
if ($method === 'GET' && $id && $id !== 'kpis') {
    $user = require_auth();
    $id = int_val($id);
    $stmt = $pdo->prepare('SELECT * FROM sale_transactions WHERE id = ?');
    $stmt->execute([$id]);
    $txn = $stmt->fetch();
    if (!$txn) error_response('Transaction not found', 404);

    $itemStmt = $pdo->prepare('SELECT * FROM daily_sales WHERE transaction_id = ?');
    $itemStmt->execute([$txn['id']]);
    $txn['items'] = $itemStmt->fetchAll();

    json_response($txn);
}

// POST /transactions — create multi-item POS sale
if ($method === 'POST' && !$id) {
    $user = require_auth();
    $data = get_json_body();

    $items = $data['items'] ?? [];
    if (empty($items)) error_response('At least one item is required');

    $pdo->beginTransaction();
    try {
        // Generate transaction number
        $pdo->prepare('UPDATE transaction_counter SET counter = counter + 1 WHERE id = ?')->execute([1]);
        $stmtC = $pdo->prepare('SELECT counter FROM transaction_counter WHERE id = ?');
        $stmtC->execute([1]);
        $counter = $stmtC->fetchColumn();
        $txnNumber = 'TXN-' . date('Ymd') . '-' . str_pad($counter, 3, '0', STR_PAD_LEFT);

        $customerId = int_val($data['customer_id'] ?? 0) ?: null;
        $customerName = sanitize($data['customer_name'] ?? 'Walk-in Customer');
        $customerPhone = sanitize($data['customer_phone'] ?? '');
        $channel = sanitize($data['channel'] ?? 'Walk-in');
        $paymentMethod = sanitize($data['payment_method'] ?? 'Cash');
        $notes = sanitize($data['notes'] ?? '');
        $discount = int_val($data['discount'] ?? 0);

        $subtotal = 0;
        $totalProfit = 0;
        $itemCount = 0;
        $resolvedItems = [];

        // Resolve product details for each item
        foreach ($items as $item) {
            $productId = int_val($item['product_id'] ?? 0);
            $qty = int_val($item['qty'] ?? 1);
            if (!$productId || $qty < 1) continue;

            $pStmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
            $pStmt->execute([$productId]);
            $product = $pStmt->fetch();
            if (!$product) continue;

            $unitPrice = ($product['on_sale'] && $product['sale_price']) ? $product['sale_price'] : $product['price'];
            $buyPrice = $product['buy_price'];
            $lineTotal = $unitPrice * $qty;
            $lineProfit = ($unitPrice - $buyPrice) * $qty;

            $subtotal += $lineTotal;
            $totalProfit += $lineProfit;
            $itemCount += $qty;

            $resolvedItems[] = [
                'product_id' => $productId,
                'product_name' => $product['name'],
                'sku' => $product['sku'],
                'volume' => $product['volume'],
                'category' => $product['category'],
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'buy_price' => $buyPrice,
                'total' => $lineTotal,
                'profit' => $lineProfit,
            ];
        }

        if (empty($resolvedItems)) {
            $pdo->rollBack();
            error_response('No valid items found');
        }

        $grandTotal = $subtotal - $discount;

        // Insert transaction
        $stmt = $pdo->prepare('INSERT INTO sale_transactions (transaction_number, customer_id, customer_name, customer_phone, item_count, subtotal, discount, total, profit, channel, payment_method, notes, sale_date, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)');
        $stmt->execute([
            $txnNumber, $customerId, $customerName, $customerPhone,
            count($resolvedItems), $subtotal, $discount, $grandTotal, $totalProfit,
            $channel, $paymentMethod, $notes, $user['name'],
        ]);
        $txnId = $pdo->lastInsertId();

        // Insert daily_sales for each item
        $saleStmt = $pdo->prepare('INSERT INTO daily_sales (transaction_id, product_id, product_name, sku, volume, category, qty, unit_price, buy_price, total, profit, sale_date, channel, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?)');

        $insertedItems = [];
        foreach ($resolvedItems as $ri) {
            $saleStmt->execute([
                $txnId, $ri['product_id'], $ri['product_name'], $ri['sku'],
                $ri['volume'], $ri['category'], $ri['qty'], $ri['unit_price'],
                $ri['buy_price'], $ri['total'], $ri['profit'],
                $channel, $user['name'],
            ]);
            $ri['id'] = $pdo->lastInsertId();
            $insertedItems[] = $ri;

            // Deduct stock
            $pdo->prepare('UPDATE products SET stock = GREATEST(0, stock - ?) WHERE id = ?')
                ->execute([$ri['qty'], $ri['product_id']]);
        }

        // Update customer stats
        if ($customerId) {
            $pdo->prepare('UPDATE customers SET order_count = order_count + 1, total_spent = total_spent + ?, last_order = CURDATE(), first_order = COALESCE(first_order, CURDATE()) WHERE id = ?')
                ->execute([$grandTotal, $customerId]);
        }

        $pdo->commit();

        success_response([
            'id' => (int)$txnId,
            'transaction_number' => $txnNumber,
            'customer_name' => $customerName,
            'item_count' => count($resolvedItems),
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $grandTotal,
            'profit' => $totalProfit,
            'items' => $insertedItems,
        ], 'Transaction recorded');
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Purex transaction error: ' . $e->getMessage());
        error_response('Transaction failed. Please try again.', 500);
    }
}

// DELETE /transactions/{id}
if ($method === 'DELETE' && $id) {
    $user = require_auth();
    $id = int_val($id);
    $restock = ($_GET['restock'] ?? '1') === '1';

    $pdo->beginTransaction();
    try {
        // Get transaction
        $stmt = $pdo->prepare('SELECT * FROM sale_transactions WHERE id = ?');
        $stmt->execute([$id]);
        $txn = $stmt->fetch();
        if (!$txn) {
            $pdo->rollBack();
            error_response('Transaction not found', 404);
        }

        // Restore stock if requested
        if ($restock) {
            $items = $pdo->prepare('SELECT product_id, qty FROM daily_sales WHERE transaction_id = ?');
            $items->execute([$id]);
            foreach ($items->fetchAll() as $item) {
                if ($item['product_id']) {
                    $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?')
                        ->execute([$item['qty'], $item['product_id']]);
                }
            }
        }

        // Update customer stats
        if ($txn['customer_id']) {
            $pdo->prepare('UPDATE customers SET order_count = GREATEST(0, order_count - 1), total_spent = GREATEST(0, total_spent - ?) WHERE id = ?')
                ->execute([$txn['total'], $txn['customer_id']]);
        }

        // Delete daily_sales entries and transaction
        $pdo->prepare('DELETE FROM daily_sales WHERE transaction_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM sale_transactions WHERE id = ?')->execute([$id]);

        $pdo->commit();
        success_response(null, $restock ? 'Transaction deleted and stock restored' : 'Transaction deleted');
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Purex transaction delete error: ' . $e->getMessage());
        error_response('Delete failed. Please try again.', 500);
    }
}

error_response('Invalid transactions endpoint', 404);
