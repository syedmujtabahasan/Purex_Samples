<?php
// ==================== DAILY SALES ROUTES ====================

// GET /sales — list with optional date filter
if ($method === 'GET' && !$id) {
    $user = require_auth();
    $from = $_GET['from'] ?? date('Y-m-d', strtotime('-90 days'));
    $to = $_GET['to'] ?? date('Y-m-d');

    // Validate date formats to prevent injection via query params
    if (!validate_date($from)) $from = date('Y-m-d', strtotime('-90 days'));
    if (!validate_date($to)) $to = date('Y-m-d');

    $stmt = $pdo->prepare('SELECT * FROM daily_sales WHERE sale_date BETWEEN ? AND ? ORDER BY created_at DESC');
    $stmt->execute([$from, $to]);
    json_response($stmt->fetchAll());
}

// GET /sales/kpis — aggregated KPIs
if ($method === 'GET' && $id === 'kpis') {
    $user = require_auth();

    $today = date('Y-m-d');
    $thisMonth = date('Y-m-01');
    $lastMonthStart = date('Y-m-01', strtotime('-1 month'));
    $lastMonthEnd = date('Y-m-t', strtotime('-1 month'));
    $weekAgo = date('Y-m-d', strtotime('-7 days'));

    // This month revenue
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(total), 0) as revenue FROM daily_sales WHERE sale_date >= ?');
    $stmt->execute([$thisMonth]);
    $thisMonthRev = $stmt->fetchColumn();

    // Last month revenue
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(total), 0) as revenue FROM daily_sales WHERE sale_date BETWEEN ? AND ?');
    $stmt->execute([$lastMonthStart, $lastMonthEnd]);
    $lastMonthRev = $stmt->fetchColumn();

    // Today sales
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(total), 0) as total, COUNT(*) as count FROM daily_sales WHERE sale_date = ?');
    $stmt->execute([$today]);
    $todayStats = $stmt->fetch();

    // Weekly sales (7 days)
    $stmt = $pdo->prepare('SELECT sale_date, SUM(total) as total FROM daily_sales WHERE sale_date >= ? GROUP BY sale_date ORDER BY sale_date');
    $stmt->execute([$weekAgo]);
    $weeklySales = $stmt->fetchAll();

    // Category breakdown
    $stmt = $pdo->prepare('SELECT category, SUM(total) as total, COUNT(*) as count FROM daily_sales WHERE sale_date >= ? GROUP BY category');
    $stmt->execute([$thisMonth]);
    $categoryBreakdown = $stmt->fetchAll();

    // Top products
    $stmt = $pdo->prepare('SELECT product_name, SUM(qty) as total_qty, SUM(total) as total_revenue FROM daily_sales WHERE sale_date >= ? GROUP BY product_name ORDER BY total_qty DESC LIMIT 5');
    $stmt->execute([$thisMonth]);
    $topProducts = $stmt->fetchAll();

    // Most sold product
    $mostSold = !empty($topProducts) ? $topProducts[0]['product_name'] : 'N/A';

    // Total profit
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(profit), 0) FROM daily_sales WHERE sale_date >= ?');
    $stmt->execute([$thisMonth]);
    $monthProfit = $stmt->fetchColumn();

    json_response([
        'thisMonthRevenue' => (int)$thisMonthRev,
        'lastMonthRevenue' => (int)$lastMonthRev,
        'todaySales' => (int)$todayStats['total'],
        'todayCount' => (int)$todayStats['count'],
        'weeklySales' => $weeklySales,
        'categoryBreakdown' => $categoryBreakdown,
        'topProducts' => $topProducts,
        'mostSoldProduct' => $mostSold,
        'monthProfit' => (int)$monthProfit,
    ]);
}

// POST /sales — record a sale
if ($method === 'POST' && !$id) {
    $user = require_auth();
    $data = get_json_body();

    $productId = int_val($data['product_id'] ?? 0);
    $qty = int_val($data['qty'] ?? 1);
    $unitPrice = int_val($data['unit_price'] ?? $data['price'] ?? 0);
    $buyPrice = int_val($data['buy_price'] ?? $data['buyPrice'] ?? 0);
    $total = $unitPrice * $qty;
    $profit = ($unitPrice - $buyPrice) * $qty;

    $stmt = $pdo->prepare('INSERT INTO daily_sales (product_id, product_name, sku, volume, category, qty, unit_price, buy_price, total, profit, sale_date, channel, customer_id, customer_name, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?)');
    $stmt->execute([
        $productId,
        sanitize($data['product_name'] ?? $data['name'] ?? ''),
        sanitize($data['sku'] ?? ''),
        sanitize($data['volume'] ?? ''),
        sanitize($data['category'] ?? ''),
        $qty, $unitPrice, $buyPrice, $total, $profit,
        sanitize($data['channel'] ?? 'Walk-in'),
        int_val($data['customer_id'] ?? 0) ?: null,
        sanitize($data['customer_name'] ?? ''),
        $user['name'],
    ]);

    // Reduce stock
    if ($productId) {
        $pdo->prepare('UPDATE products SET stock = GREATEST(0, stock - ?) WHERE id = ?')->execute([$qty, $productId]);
    }

    success_response(['id' => $pdo->lastInsertId(), 'total' => $total], 'Sale recorded');
}

// DELETE /sales/{id}
if ($method === 'DELETE' && $id) {
    $user = require_auth();
    $id = int_val($id);
    $restock = ($_GET['restock'] ?? '1') === '1';
    if ($restock) {
        $stmt = $pdo->prepare('SELECT product_id, qty FROM daily_sales WHERE id = ?');
        $stmt->execute([$id]);
        $sale = $stmt->fetch();
        if ($sale && $sale['product_id']) {
            $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?')
                ->execute([$sale['qty'], $sale['product_id']]);
        }
    }
    $pdo->prepare('DELETE FROM daily_sales WHERE id = ?')->execute([$id]);
    success_response(null, $restock ? 'Sale deleted and stock restored' : 'Sale deleted');
}

error_response('Invalid sales endpoint', 404);
