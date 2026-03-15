<?php
// ==================== DATABASE SEEDER ====================
// Run ONCE: https://qwebtesting.tech/api/seed.php
// DELETE THIS FILE AFTER RUNNING!

require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=utf-8');
echo '<h2>Purex Database Seeder</h2><pre>';

try {
    // ---- ADMIN USER ----
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute(['admin@purex.com']);
    if (!$stmt->fetch()) {
        $pdo->prepare('INSERT INTO users (username, password_hash, name, role, active, permissions) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([
                'admin@purex.com',
                password_hash('purex2026', PASSWORD_BCRYPT),
                'Admin',
                'admin',
                1,
                json_encode([
                    'products' => true, 'orders' => true, 'inventory' => true,
                    'sales' => true, 'profits' => true, 'customers' => true,
                    'users' => true, 'invoices' => true, 'deleteProducts' => true,
                    'editPrices' => true, 'reduceStock' => true, 'exportData' => true,
                ])
            ]);
        echo "✓ Admin user created\n";
    } else {
        echo "• Admin user already exists\n";
    }

    // ---- PRODUCTS (31 SKUs) ----
    $stmt = $pdo->query('SELECT COUNT(*) FROM products');
    if ($stmt->fetchColumn() == 0) {
        $products = [
            ['Taizab Cleaning Acid', 'PX-001', '500ml', 'Cleaning', 120, 58, 100, 100, 'Powerful cleaning acid for tough stains and surfaces.', 'assets/products/product_1.png'],
            ['Taizab Cleaning Acid', 'PX-002', '1000ml', 'Cleaning', 200, 98, 100, 100, 'Powerful cleaning acid for tough stains and surfaces.', 'assets/products/product_2.png'],
            ['Taizab Cleaning Acid', 'PX-003', '2500ml', 'Cleaning', 500, 245, 100, 100, 'Powerful cleaning acid for tough stains and surfaces.', 'assets/products/product_3.png'],
            ['Sweep Phenyl', 'PX-004', '500ml', 'Cleaning', 100, 48, 100, 100, 'Floor cleaner phenyl for hygiene and freshness.', 'assets/products/product_4.png'],
            ['Sweep Phenyl', 'PX-005', '1000ml', 'Cleaning', 160, 78, 100, 100, 'Floor cleaner phenyl for hygiene and freshness.', 'assets/products/product_5.png'],
            ['Sweep Phenyl', 'PX-006', '2500ml', 'Cleaning', 380, 186, 100, 100, 'Floor cleaner phenyl for hygiene and freshness.', 'assets/products/product_6.png'],
            ['White Wash', 'PX-007', '500ml', 'Cleaning', 90, 44, 100, 100, 'Premium white wash solution.', 'assets/products/product_7.png'],
            ['White Wash', 'PX-008', '1000ml', 'Cleaning', 150, 73, 100, 100, 'Premium white wash solution.', 'assets/products/product_8.png'],
            ['White Wash', 'PX-009', '2500ml', 'Cleaning', 350, 172, 100, 100, 'Premium white wash solution.', 'assets/products/product_9.jpg'],
            ['Neel', 'PX-010', '120ml', 'Whitener', 20, 10, 100, 100, 'Fabric whitener for bright clothes.', 'assets/products/product_10.png'],
            ['Neel', 'PX-011', '225ml', 'Whitener', 40, 20, 100, 100, 'Fabric whitener for bright clothes.', 'assets/products/product_11.jpg'],
            ['Neel', 'PX-012', '500ml', 'Whitener', 70, 34, 100, 100, 'Fabric whitener for bright clothes.', 'assets/products/product_12.jpg'],
            ['Neel', 'PX-013', '1000ml', 'Whitener', 120, 59, 100, 100, 'Fabric whitener for bright clothes.', 'assets/products/product_13.jpg'],
            ['Neel', 'PX-014', '2500ml', 'Whitener', 270, 132, 100, 100, 'Fabric whitener for bright clothes.', 'assets/products/product_14.jpg'],
            ['Bleach', 'PX-015', '500ml', 'Bleach', 80, 39, 100, 100, 'Household bleach for cleaning and whitening.', 'assets/products/product_15.jpg'],
            ['Bleach', 'PX-016', '1000ml', 'Bleach', 130, 64, 100, 100, 'Household bleach for cleaning and whitening.', 'assets/products/product_16.jpg'],
            ['Bleach', 'PX-017', '2500ml', 'Bleach', 300, 147, 100, 100, 'Household bleach for cleaning and whitening.', 'assets/products/product_17.jpg'],
            ['Glass Cleaner', 'PX-018', '500ml', 'Cleaning', 110, 54, 100, 100, 'Glass and surface cleaner for streak-free shine.', 'assets/products/product_18.jpg'],
            ['Glass Cleaner', 'PX-019', '1000ml', 'Cleaning', 180, 88, 100, 100, 'Glass and surface cleaner for streak-free shine.', 'assets/products/product_19.jpg'],
            ['Toilet Cleaner', 'PX-020', '500ml', 'Cleaning', 100, 49, 100, 100, 'Toilet and bathroom cleaning solution.', 'assets/products/product_20.jpg'],
            ['Toilet Cleaner', 'PX-021', '1000ml', 'Cleaning', 170, 83, 100, 100, 'Toilet and bathroom cleaning solution.', 'assets/products/product_21.png'],
            ['Dish Wash', 'PX-022', '500ml', 'Cleaning', 95, 47, 100, 100, 'Dish washing liquid for spotless dishes.', 'assets/products/product_22.webp'],
            ['Dish Wash', 'PX-023', '1000ml', 'Cleaning', 160, 78, 100, 100, 'Dish washing liquid for spotless dishes.', 'assets/products/product_23.png'],
            ['Carbolic Soap', 'PX-024', '100g', 'Soap', 35, 17, 100, 100, 'Antibacterial carbolic soap for hygiene.', 'assets/products/product_24.png'],
            ['Carbolic Soap', 'PX-025', '150g', 'Soap', 50, 25, 100, 100, 'Antibacterial carbolic soap for hygiene.', 'assets/products/product_25.jpg'],
            ['Detergent Bar', 'PX-026', '200g', 'Soap', 30, 15, 100, 100, 'Detergent bar for hand-wash laundry.', 'assets/products/product_26.jpg'],
            ['Detergent Bar', 'PX-027', '400g', 'Soap', 55, 27, 100, 100, 'Detergent bar for hand-wash laundry.', 'assets/products/product_27.jpg'],
            ['Surface Cleaner', 'PX-028', '500ml', 'Cleaning', 105, 51, 100, 100, 'All-purpose surface cleaner.', 'assets/products/product_28.jpg'],
            ['Surface Cleaner', 'PX-029', '1000ml', 'Cleaning', 175, 86, 100, 100, 'All-purpose surface cleaner.', 'assets/products/product_29.png'],
            ['Handwash', 'PX-030', '250ml', 'Soap', 85, 42, 100, 100, 'Liquid handwash with antibacterial formula.', 'assets/products/product_30.jpg'],
            ['Handwash', 'PX-031', '500ml', 'Soap', 150, 73, 100, 100, 'Liquid handwash with antibacterial formula.', 'assets/products/product_31.jpg'],
        ];
        $stmt = $pdo->prepare('INSERT INTO products (name, sku, volume, category, price, buy_price, stock, capacity, description, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($products as $p) {
            $stmt->execute($p);
        }
        echo "✓ 31 products inserted\n";
    } else {
        echo "• Products already exist\n";
    }

    // ---- SUPPLIERS ----
    $stmt = $pdo->query('SELECT COUNT(*) FROM suppliers');
    if ($stmt->fetchColumn() == 0) {
        $suppliers = [
            ['Ali Chemicals', '0321-4567890', 'ali@chemicals.pk', 'Cleaning Acid, Bleach', 'Multan Road, Lahore', 'Reliable bulk supplier'],
            ['Karachi Soap Works', '0300-1234567', 'info@karachisoap.pk', 'Soap, Detergent Bars', 'SITE Area, Karachi', 'Monthly supply contract'],
            ['Punjab Chemicals Ltd', '0333-9876543', 'sales@punjabchem.pk', 'Neel, Whitener, Bleach', 'Industrial Area, Faisalabad', 'Best rates on whiteners'],
            ['Raza Traders', '0345-6543210', 'raza.traders@gmail.com', 'Packaging, Labels, Bottles', 'Anarkali, Lahore', 'Packaging materials only'],
            ['National Fragrance Co', '0312-7778899', 'orders@nationalfrag.pk', 'Fragrance Oils, Surfactants', 'Korangi, Karachi', 'Raw material supplier'],
        ];
        $stmt = $pdo->prepare('INSERT INTO suppliers (name, phone, email, products, address, notes) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($suppliers as $s) { $stmt->execute($s); }
        echo "✓ 5 suppliers inserted\n";
    } else {
        echo "• Suppliers already exist\n";
    }

    // ---- CUSTOMERS ----
    $stmt = $pdo->query('SELECT COUNT(*) FROM customers');
    if ($stmt->fetchColumn() == 0) {
        $customers = [
            ['Ahmed Khan', '0301-2345678', '', 'Lahore', 'Model Town, Block C', 3, 4500, '2026-03-05', '2026-03-05'],
            ['Fatima Bibi', '0312-8765432', '', 'Karachi', 'Gulshan-e-Iqbal, Block 13', 5, 7200, '2026-02-15', '2026-03-08'],
            ['Usman Retailer', '0333-5551234', '', 'Faisalabad', 'Ghulam Muhammad Abad', 8, 15600, '2026-01-20', '2026-03-10'],
            ['Nadia Store', '0345-9998877', '', 'Rawalpindi', 'Commercial Market, Satellite Town', 2, 2800, '2026-02-10', '2026-02-28'],
            ['Bilal Wholesale', '0300-1112233', '', 'Multan', 'Hussain Agahi Bazaar', 12, 32000, '2025-12-01', '2026-03-09'],
        ];
        $stmt = $pdo->prepare('INSERT INTO customers (name, phone, email, city, address, order_count, total_spent, first_order, last_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($customers as $c) { $stmt->execute($c); }
        echo "✓ 5 customers inserted\n";
    } else {
        echo "• Customers already exist\n";
    }

    // ---- ORDER COUNTER ----
    $stmt = $pdo->query('SELECT COUNT(*) FROM order_counter');
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec('INSERT INTO order_counter (id, counter) VALUES (1, 0)');
        echo "✓ Order counter initialized\n";
    } else {
        echo "• Order counter already exists\n";
    }

    echo "\n<b style='color:green'>✓ Database seeded successfully!</b>\n";
    echo "\n⚠ DELETE THIS FILE NOW: api/seed.php\n";

} catch (Exception $e) {
    echo "\n<b style='color:red'>ERROR: " . htmlspecialchars($e->getMessage()) . "</b>\n";
}

echo '</pre>';
