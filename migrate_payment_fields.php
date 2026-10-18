<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

try {
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) NOT NULL DEFAULT 'cod' AFTER total_amount");
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_status VARCHAR(50) NOT NULL DEFAULT 'pending' AFTER payment_method");
    echo "Migration completed successfully! Added payment_method and payment_status columns to orders table.\n";
    echo "Default payment_method: 'cod' (Cash on Delivery)\n";
    echo "Default payment_status: 'pending'\n";
} catch (Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    echo "Attempting alternative approach (for older MySQL versions)...\n";
    try {
        $columns = $pdo->query("SHOW COLUMNS FROM orders LIKE 'payment_method'")->fetchAll();
        if (empty($columns)) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN payment_method VARCHAR(50) NOT NULL DEFAULT 'cod' AFTER total_amount");
            echo "Added payment_method column.\n";
        } else {
            echo "payment_method column already exists.\n";
        }
        $columns = $pdo->query("SHOW COLUMNS FROM orders LIKE 'payment_status'")->fetchAll();
        if (empty($columns)) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN payment_status VARCHAR(50) NOT NULL DEFAULT 'pending' AFTER payment_method");
            echo "Added payment_status column.\n";
        } else {
            echo "payment_status column already exists.\n";
        }
        echo "Migration completed successfully!\n";
    } catch (Throwable $e2) {
        echo "Alternative approach also failed: " . $e2->getMessage() . "\n";
        exit(1);
    }
}
