<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

echo "Running Currencies Migration...\n";

try {
    // 1. Create currencies table
    $pdo->exec("CREATE TABLE IF NOT EXISTS currencies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(10) NOT NULL UNIQUE,
        name VARCHAR(100) NOT NULL,
        symbol VARCHAR(20) NOT NULL,
        symbol_position ENUM('before', 'after') NOT NULL DEFAULT 'before',
        decimal_places TINYINT NOT NULL DEFAULT 2,
        thousands_sep VARCHAR(5) NOT NULL DEFAULT ',',
        decimal_sep VARCHAR(5) NOT NULL DEFAULT '.',
        exchange_rate DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
        is_default TINYINT(1) NOT NULL DEFAULT 0,
        active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        updated_at DATETIME DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✓ Table 'currencies' created or verified.\n";

    // 2. Check if table has records, if not, seed maximum world & regional currencies
    $count = (int)$pdo->query("SELECT COUNT(*) FROM currencies")->fetchColumn();
    if ($count === 0) {
        $currencies = [
            ['PKR', 'Pakistani Rupee', 'Rs. ', 'before', 0, 1, 1], // Set PKR as default initially
            ['USD', 'US Dollar', '$', 'before', 2, 0, 2],
            ['EUR', 'Euro', '€', 'before', 2, 0, 3],
            ['GBP', 'British Pound', '£', 'before', 2, 0, 4],
            ['AED', 'UAE Dirham', 'AED ', 'before', 2, 0, 5],
            ['SAR', 'Saudi Riyal', 'SAR ', 'before', 2, 0, 6],
            ['CAD', 'Canadian Dollar', 'CA$ ', 'before', 2, 0, 7],
            ['AUD', 'Australian Dollar', 'AU$ ', 'before', 2, 0, 8],
            ['QAR', 'Qatari Riyal', 'QAR ', 'before', 2, 0, 9],
            ['KWD', 'Kuwaiti Dinar', 'KWD ', 'before', 3, 0, 10],
            ['OMR', 'Omani Rial', 'OMR ', 'before', 3, 0, 11],
            ['BHD', 'Bahraini Dinar', 'BHD ', 'before', 3, 0, 12],
            ['TRY', 'Turkish Lira', '₺', 'before', 2, 0, 13],
            ['INR', 'Indian Rupee', '₹', 'before', 2, 0, 14],
            ['CNY', 'Chinese Yuan', '¥', 'before', 2, 0, 15],
            ['JPY', 'Japanese Yen', '¥', 'before', 0, 0, 16],
            ['CHF', 'Swiss Franc', 'CHF ', 'before', 2, 0, 17],
            ['SGD', 'Singapore Dollar', 'SG$ ', 'before', 2, 0, 18],
            ['MYR', 'Malaysian Ringgit', 'RM ', 'before', 2, 0, 19],
            ['NZD', 'New Zealand Dollar', 'NZ$ ', 'before', 2, 0, 20],
            ['ZAR', 'South African Rand', 'R ', 'before', 2, 0, 21],
        ];

        $stmt = $pdo->prepare("INSERT INTO currencies 
            (code, name, symbol, symbol_position, decimal_places, is_default, active, sort_order, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW())");

        foreach ($currencies as $c) {
            $stmt->execute([
                $c[0], $c[1], $c[2], $c[3], $c[4], $c[5], $c[6]
            ]);
        }
        echo "✓ Seeded " . count($currencies) . " world & regional currencies successfully!\n";
    } else {
        echo "✓ Currencies table already contains $count currencies.\n";
    }

    echo "\nCurrencies migration completed successfully!\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
