<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

echo "Running Payment Methods & Order Items Migration...\n";

try {
    // 1. Create payment_methods table
    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_methods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        code VARCHAR(50) NOT NULL UNIQUE,
        account_title VARCHAR(255) DEFAULT NULL,
        account_number VARCHAR(100) DEFAULT NULL,
        bank_name VARCHAR(150) DEFAULT NULL,
        branch_code VARCHAR(50) DEFAULT NULL,
        instructions TEXT DEFAULT NULL,
        qr_image TEXT DEFAULT NULL,
        icon VARCHAR(50) DEFAULT '💳',
        active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        updated_at DATETIME DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ Table 'payment_methods' created or verified.\n";

    // 2. Seed default payment methods if table is empty
    $count = (int)$pdo->query("SELECT COUNT(*) FROM payment_methods")->fetchColumn();
    if ($count === 0) {
        $insertStmt = $pdo->prepare("INSERT INTO payment_methods 
            (name, code, account_title, account_number, bank_name, branch_code, instructions, qr_image, icon, active, sort_order, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

        // Bank Transfer with the exact requested details
        $insertStmt->execute([
            'Direct Bank Transfer',
            'bank_transfer',
            'Resources Enterprises',
            'PK08ASCM0003790900002243',
            'Askari Bank',
            '',
            'Please transfer the total amount to our bank account. Use your Order ID as payment reference. Your order will be processed after payment verification.',
            '',
            '🏦',
            1,
            1
        ]);

        // Cash on Delivery
        $insertStmt->execute([
            'Cash on Delivery',
            'cod',
            '',
            '',
            '',
            '',
            'Pay with cash upon receipt of your package at your doorstep.',
            '',
            '💵',
            1,
            2
        ]);

        // JazzCash / Raast
        $insertStmt->execute([
            'JazzCash / Raast',
            'jazzcash',
            'Resources Enterprises',
            '984641936',
            'JazzCash (Mobilink Bank)',
            '',
            'Dial *786*10# and enter TILL ID 984641936 to pay via JazzCash, or scan the QR code on screen.',
            'assets/jazzcash_qr.jpg',
            '📱',
            1,
            3
        ]);
        echo "✓ Seeded default payment methods (Bank Transfer, COD, JazzCash).\n";
    } else {
        echo "✓ Table 'payment_methods' already contains data ($count records).\n";
    }

    // 3. Add image_url column to order_items if not exists
    $cols = $pdo->query("SHOW COLUMNS FROM order_items LIKE 'image_url'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE order_items ADD COLUMN image_url TEXT DEFAULT NULL AFTER product_name");
        echo "✓ Added 'image_url' column to order_items.\n";
    } else {
        echo "✓ 'image_url' column already exists in order_items.\n";
    }

    // 4. Backfill existing order_items image_url from products table
    $updateStmt = $pdo->exec("
        UPDATE order_items oi
        JOIN products p ON oi.product_id = p.id
        SET oi.image_url = p.image_url
        WHERE (oi.image_url IS NULL OR oi.image_url = '') AND p.image_url IS NOT NULL
    ");
    echo "✓ Backfilled product images for $updateStmt order items.\n";

    // 5. Ensure orders table has transaction_id column
    $orderCols = $pdo->query("SHOW COLUMNS FROM orders LIKE 'transaction_id'")->fetchAll();
    if (empty($orderCols)) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN transaction_id VARCHAR(200) DEFAULT NULL AFTER payment_status");
        echo "✓ Added 'transaction_id' column to orders.\n";
    } else {
        echo "✓ 'transaction_id' column already exists in orders.\n";
    }

    echo "\nAll migrations completed successfully!\n";
} catch (Throwable $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
    exit(1);
}
