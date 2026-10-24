<?php
declare(strict_types=1);

echo "<pre style='background:#000;color:#0f0;padding:20px;font-family:monospace;'>";
echo "=== LOCAL DATABASE SETUP FOR RESOURCES ENTERPRISES ===\n\n";

$connections = [
    ['host' => 'localhost', 'port' => 3306, 'user' => 'root', 'pass' => ''],
    ['host' => 'localhost', 'port' => 3306, 'user' => 'root', 'pass' => 'root'],
    ['host' => '127.0.0.1', 'port' => 3306, 'user' => 'root', 'pass' => ''],
    ['host' => '127.0.0.1', 'port' => 3307, 'user' => 'root', 'pass' => ''],
    ['host' => 'localhost', 'port' => 3308, 'user' => 'root', 'pass' => ''],
];

$pdo = null;
$connected = false;

foreach ($connections as $i => $conn) {
    $dsn = "mysql:host={$conn['host']};port={$conn['port']};charset=utf8mb4";
    try {
        echo "Trying: host={$conn['host']} port={$conn['port']} user={$conn['user']} pass=" . ($conn['pass'] === '' ? '(empty)' : $conn['pass']) . "... ";
        $pdo = new PDO($dsn, $conn['user'], $conn['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        echo "✅ SUCCESS!\n";
        $connected = true;
        break;
    } catch (PDOException $e) {
        echo "❌ FAILED: " . $e->getMessage() . "\n";
    }
}

if (!$connected) {
    echo "\n❌ MySQL connection failed on all attempts!\n";
    echo "\nPlease do ONE of the following:\n";
    echo "1. Start XAMPP/WAMP MySQL service\n";
    echo "2. If using custom MySQL, update the \$connections array above\n";
    echo "3. Default credentials: host=localhost, user=root, password=(empty)\n";
    exit(1);
}

echo "\n✅ Connected successfully!\n\n";

$dbName = 'enterprise_db';
echo "Creating database '$dbName' if not exists... ";
$pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `$dbName`");
echo "✅ Done\n\n";

echo "Creating tables...\n";

$tablesSql = [
'users' => "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(50) DEFAULT '',
    address TEXT DEFAULT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'user',
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'products' => "CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    category VARCHAR(100) NOT NULL,
    subcategory VARCHAR(150) DEFAULT '',
    description TEXT NOT NULL,
    short_description VARCHAR(500) DEFAULT '',
    price DECIMAL(10, 2) NOT NULL,
    stock INT NOT NULL DEFAULT 100,
    sku VARCHAR(100) DEFAULT '',
    weight VARCHAR(50) DEFAULT '',
    dimensions VARCHAR(100) DEFAULT '',
    image_url TEXT NOT NULL,
    meta_title VARCHAR(255) DEFAULT '',
    meta_description VARCHAR(500) DEFAULT '',
    meta_keywords VARCHAR(500) DEFAULT '',
    tags VARCHAR(500) DEFAULT '',
    active TINYINT(1) NOT NULL DEFAULT 1,
    featured TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'product_images' => "CREATE TABLE IF NOT EXISTS product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image_url TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'orders' => "CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL DEFAULT 'cod',
    payment_status VARCHAR(50) NOT NULL DEFAULT 'pending',
    shipping_name VARCHAR(255) NOT NULL,
    shipping_phone VARCHAR(50) NOT NULL,
    shipping_address TEXT NOT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'order_items' => "CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT DEFAULT NULL,
    product_name VARCHAR(255) NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    quantity INT NOT NULL,
    line_total DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'reviews' => "CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL DEFAULT 5,
    comment TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'favorites' => "CREATE TABLE IF NOT EXISTS favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_favorite (user_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'collections' => "CREATE TABLE IF NOT EXISTS collections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    image_url TEXT,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'website_reviews' => "CREATE TABLE IF NOT EXISTS website_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    rating INT NOT NULL DEFAULT 5,
    title VARCHAR(255),
    comment TEXT NOT NULL,
    approved TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'payment_methods' => "CREATE TABLE IF NOT EXISTS payment_methods (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'currencies' => "CREATE TABLE IF NOT EXISTS currencies (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

foreach ($tablesSql as $tableName => $sql) {
    echo "  - $tableName... ";
    $pdo->exec($sql);
    echo "✅\n";
}
echo "\n✅ All tables created!\n\n";

echo "Checking and adding missing columns (SEO fields etc)...\n";

$columnsToAdd = [
    'products' => [
        ['col' => 'short_description', 'def' => "VARCHAR(500) DEFAULT '' AFTER description"],
        ['col' => 'sku', 'def' => "VARCHAR(100) DEFAULT '' AFTER stock"],
        ['col' => 'weight', 'def' => "VARCHAR(50) DEFAULT '' AFTER sku"],
        ['col' => 'dimensions', 'def' => "VARCHAR(100) DEFAULT '' AFTER weight"],
        ['col' => 'meta_title', 'def' => "VARCHAR(255) DEFAULT '' AFTER image_url"],
        ['col' => 'meta_description', 'def' => "VARCHAR(500) DEFAULT '' AFTER meta_title"],
        ['col' => 'meta_keywords', 'def' => "VARCHAR(500) DEFAULT '' AFTER meta_description"],
        ['col' => 'tags', 'def' => "VARCHAR(500) DEFAULT '' AFTER meta_keywords"],
        ['col' => 'featured', 'def' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER active"],
        ['col' => 'updated_at', 'def' => "DATETIME DEFAULT NULL AFTER created_at"],
    ],
    'users' => [
        ['col' => 'role', 'def' => "VARCHAR(20) NOT NULL DEFAULT 'user' AFTER address"],
    ],
    'order_items' => [
        ['col' => 'image_url', 'def' => "TEXT DEFAULT NULL AFTER product_name"],
    ],
    'orders' => [
        ['col' => 'transaction_id', 'def' => "VARCHAR(200) DEFAULT NULL AFTER payment_status"],
    ],
];

foreach ($columnsToAdd as $table => $cols) {
    foreach ($cols as $c) {
        try {
            $check = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '{$c['col']}'")->fetchAll();
            if (empty($check)) {
                echo "  - Adding {$c['col']} to $table... ";
                $pdo->exec("ALTER TABLE `$table` ADD COLUMN {$c['col']} {$c['def']}");
                echo "✅\n";
            } else {
                echo "  - Column {$c['col']} exists, skipping.\n";
            }
        } catch (Throwable $e) {
            echo "  ⚠️  {$c['col']}: " . $e->getMessage() . "\n";
        }
    }
}
echo "\n";

echo "Seeding default data...\n";

$adminEmail = 'admin@resource.com';
$checkAdmin = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$checkAdmin->execute([$adminEmail]);
if (!$checkAdmin->fetch()) {
    echo "  - Creating admin account (admin@resource.com / admin123)... ";
    $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, phone, address, role, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute(['Administrator', $adminEmail, $adminPass, '+92 300 1234567', 'Office Address, Pakistan', 'admin']);
    echo "✅\n";
} else {
    echo "  - Admin exists, skipping.\n";
}

$userEmail = 'customer@test.com';
$checkUser = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$checkUser->execute([$userEmail]);
$userId = null;
if (!$checkUser->fetch()) {
    echo "  - Creating test customer (customer@test.com / user123)... ";
    $userPass = password_hash('user123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, phone, address, role, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute(['Test Customer', $userEmail, $userPass, '+92 321 9876543', 'House #123, Street 45, Lahore, Pakistan', 'user']);
    $userId = (int)$pdo->lastInsertId();
    echo "✅\n";
} else {
    $userId = (int)$checkUser->fetchColumn();
}

$seedProducts = [
    [
        'Premium Chef Knife Set', 'premium-chef-knife-set-' . rand(100,999), 'Cutlery', 'Knife Sets',
        'Precision-forged stainless steel knives for professional kitchens and premium retail lines. Ultra-sharp edges with full-tang construction for perfect balance and durability.',
        'Professional 8-piece chef knife set with German steel blades.',
        149.00, 120, 'SKU-CUT-001', '2.5 kg', '45x30x10 cm',
        'Premium Chef Knife Set | Professional Cutlery - Resources Enterprises',
        'Buy professional chef knife sets at wholesale prices. Precision-forged German steel, full tang construction. Perfect for retail, hotels, and culinary businesses.',
        'chef knife set, professional cutlery, kitchen knives, wholesale knives, premium chef set',
        'chef, knives, cutlery, kitchen, professional',
        'https://images.unsplash.com/photo-1507648154934-f230d5bd6942?auto=format&fit=crop&w=1400&q=80',
        1, 1,
    ],
    [
        'Craft Utility Blade Collection', 'craft-utility-blade-' . rand(100,999), 'Cutlery', 'Knives',
        'Durable utility blades with balanced grip and anti-rust finish for daily heavy use. High carbon steel with ergonomic handle design.',
        'Heavy-duty utility knife set with anti-rust coating.',
        89.00, 160, 'SKU-CUT-002', '1.2 kg', '30x20x8 cm',
        'Utility Blade Collection | Industrial Cutlery - Resources Enterprises',
        'Durable utility blades for industrial and everyday use. High carbon steel, anti-rust finish, ergonomic grip. Wholesale export quality.',
        'utility knife, utility blades, industrial knife, heavy duty blade, wholesale cutlery',
        'utility, blade, knife, industrial, heavy duty',
        'https://images.unsplash.com/photo-1580802849130-0f4bc9e1a45b?auto=format&fit=crop&w=1200&q=80',
        1, 0,
    ],
    [
        'Urban Comfort Sneakers', 'urban-comfort-sneakers-' . rand(100,999), 'Footwear', 'Sneakers',
        'Lifestyle sneakers designed for all-day comfort with export-grade stitching and sole grip. Premium mesh upper with memory foam insole.',
        'All-day comfort sneakers with export quality materials.',
        129.00, 200, 'SKU-FW-001', '0.8 kg', '32x22x12 cm',
        'Urban Comfort Sneakers | Premium Footwear - Resources Enterprises',
        'Export quality urban sneakers with memory foam insole and premium mesh. Wholesale footwear for retailers and bulk buyers.',
        'sneakers, urban shoes, comfort footwear, premium sneakers, wholesale shoes',
        'sneakers, shoes, footwear, urban, comfort',
        'https://images.unsplash.com/photo-1559513493-8ee89c9e69ee?auto=format&fit=crop&w=1400&q=80',
        1, 1,
    ],
    [
        'Trail Runner Performance Shoes', 'trail-runner-' . rand(100,999), 'Footwear', 'Sports Shoes',
        'Lightweight and breathable performance shoes with shock absorption and reinforced support. EVA midsole with anti-skid rubber outsole.',
        'Professional trail running shoes with shock absorption.',
        139.00, 150, 'SKU-FW-002', '0.9 kg', '33x23x13 cm',
        'Trail Runner Shoes | Performance Footwear - Resources Enterprises',
        'Performance trail running shoes with EVA shock absorption and anti-skid sole. Export quality sports footwear for wholesale.',
        'running shoes, trail running, sports shoes, performance footwear, athletic shoes',
        'running, sports, athletic, trail, performance',
        'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=1400&q=80',
        1, 0,
    ],
    [
        'Himalayan Salt Lamp Duo', 'himalayan-salt-lamp-' . rand(100,999), 'Salt Crafts', 'Salt Lamps',
        'Handcrafted lamp pair with natural warm tones, ideal for retail, gifting, and decor programs. Authentic Himalayan salt from Punjab mines.',
        'Pair of authentic Himalayan salt lamps with wooden bases.',
        79.00, 180, 'SKU-SC-001', '5 kg', '25x25x40 cm',
        'Himalayan Salt Lamp Duo Pack | Handcrafted Decor - Resources Enterprises',
        'Authentic handcrafted Himalayan salt lamps from Punjab. Warm glow, perfect for gifting, retail, and home decor programs. Wholesale pricing.',
        'himalayan salt lamp, salt lamp, natural decor, wellness lamp, wholesale salt crafts',
        'salt lamp, himalayan, decor, wellness, natural',
        'https://images.unsplash.com/photo-1579101450104-5890cd7db9ad?auto=format&fit=crop&w=1400&q=80',
        1, 1,
    ],
    [
        'Salt Serving Bowl Set', 'salt-serving-bowl-' . rand(100,999), 'Salt Crafts', 'Cooking Slabs/Plates',
        'Food-safe salt serving bowl set crafted for premium dining and hospitality collections. Naturally antibacterial, adds mineral richness.',
        'Food-safe Himalayan salt serving bowls set of 3.',
        69.00, 140, 'SKU-SC-002', '3.5 kg', '30x30x15 cm',
        'Salt Serving Bowl Set | Himalayan Salt Tableware - Resources Enterprises',
        'Premium food-safe Himalayan salt serving bowls. Naturally antibacterial, enhances flavor. Ideal for restaurants, hotels, and premium retail.',
        'salt bowls, himalayan salt, serving bowls, tableware, hospitality supplies',
        'serving, bowl, salt, tableware, dining',
        'https://images.unsplash.com/photo-1579101450453-c9fb7b8a490d?auto=format&fit=crop&w=1200&q=80',
        1, 0,
    ],
    [
        'Modern Ceramic Dinner Set', 'ceramic-dinner-set-' . rand(100,999), 'Value Ceramics', 'Dinnerware Sets',
        'Minimal ceramic dinnerware set for modern homes, cafes, and hotel supply programs. Microwave and dishwasher safe. Chip resistant glaze.',
        '24-piece modern ceramic dinner set, service for 6.',
        119.00, 170, 'SKU-VC-001', '12 kg', '45x45x35 cm',
        'Modern Ceramic Dinnerware Set | Hotel Supply Grade - Resources Enterprises',
        'Premium ceramic dinner sets in modern minimalist design. Hotel supply grade, microwave safe, chip resistant. Bulk wholesale available.',
        'ceramic dinner set, dinnerware, hotel supply, ceramic plates, modern dinner set',
        'ceramic, dinnerware, plates, hotel, modern',
        'https://images.unsplash.com/photo-1610701596007-11502861dcfa?auto=format&fit=crop&w=1200&q=80',
        1, 1,
    ],
    [
        'Artisan Glazed Mug Pack', 'glazed-mug-pack-' . rand(100,999), 'Value Ceramics', 'Mugs & Cups',
        'Set of handcrafted mugs with premium glaze finish suitable for retail and custom branding. Lead-free ceramic, comfortable grip handle.',
        'Pack of 6 handcrafted artisan glazed ceramic mugs.',
        59.00, 220, 'SKU-VC-002', '2 kg', '30x25x20 cm',
        'Artisan Glazed Ceramic Mugs 6-Pack | Wholesale Drinkware - Resources Enterprises',
        'Handcrafted glazed ceramic mugs, set of 6. Lead-free, microwave safe, perfect for custom branding. Wholesale drinkware supplier.',
        'ceramic mugs, coffee mugs, glazed mugs, wholesale mugs, custom drinkware',
        'mug, cup, coffee, ceramic, glazed',
        'https://images.unsplash.com/photo-1514228742587-6b1558fcca3d?auto=format&fit=crop&w=1200&q=80',
        1, 0,
    ],
    [
        'Industrial CNC Precision Parts', 'cnc-precision-parts-' . rand(100,999), 'Engineering Parts', 'Precision Parts',
        'High-precision CNC machined components with tolerances up to ±0.005mm. Stainless steel, aluminum, and brass options available. Export quality ISO certified.',
        'Custom CNC precision engineered components with tight tolerances.',
        299.00, 50, 'SKU-EP-001', '1.8 kg', '20x15x10 cm',
        'CNC Precision Machined Parts | ISO Certified - Resources Enterprises',
        'Export quality CNC precision machined parts. ±0.005mm tolerance, stainless steel/aluminum/brass. ISO certified manufacturing, OEM/ODM accepted.',
        'CNC parts, precision machining, engineering components, industrial parts, OEM manufacturing',
        'CNC, precision, engineering, industrial, machining',
        'https://images.unsplash.com/photo-1565043666747-69f6646db940?auto=format&fit=crop&w=1400&q=80',
        1, 1,
    ],
    [
        'Automotive Fastener Kit', 'automotive-fastener-' . rand(100,999), 'Engineering Parts', 'Fasteners (Bolts, nuts)',
        'Export-grade automotive fastener kit including bolts, nuts, washers, and screws. Corrosion-resistant, high-tensile steel, SAE J429 certified.',
        '500-piece SAE certified automotive fastener assortment kit.',
        89.00, 300, 'SKU-EP-002', '6 kg', '40x30x20 cm',
        'Automotive Fastener Kit 500-pc | SAE Grade 8 - Resources Enterprises',
        'SAE J429 Grade 8 automotive fastener assortment. High tensile corrosion resistant steel. 500 pieces: bolts, nuts, washers, screws. Export quality.',
        'automotive fasteners, bolts nuts, high tensile fasteners, SAE grade 8, export hardware',
        'fastener, bolt, nut, automotive, hardware',
        'https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122?auto=format&fit=crop&w=1200&q=80',
        1, 0,
    ],
    [
        'Stainless Steel Cookware Set', 'stainless-cookware-' . rand(100,999), 'Kitchenware', 'Cookware (Pots, pans)',
        'Premium tri-ply stainless steel cookware set with aluminum core. Induction compatible, PFOA non-stick option, heat-resistant handles.',
        '10-piece tri-ply stainless steel induction cookware set.',
        249.00, 80, 'SKU-KW-001', '15 kg', '55x45x30 cm',
        'Tri-Ply Stainless Cookware Set 10pc | Induction Ready - Resources Enterprises',
        'Professional tri-ply stainless steel cookware set. Aluminum core for even heating. Induction ready, PFOA-free options. Hotel & retail bulk supply.',
        'stainless cookware, induction cookware, tri-ply pots, kitchenware set, hotel cookware',
        'cookware, stainless, pots, pans, kitchen',
        'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?auto=format&fit=crop&w=1400&q=80',
        1, 1,
    ],
    [
        'Feasibility Study Report - Manufacturing', 'feasibility-manufacturing-' . rand(100,999), 'Project Feasibility', 'Business Feasibility Reports',
        'Comprehensive 80+ page manufacturing industry feasibility report including market research, 5-year financial projections, cost analysis, and risk assessment.',
        'Detailed manufacturing project feasibility report with financials.',
        499.00, 25, 'SKU-PF-001', '0.1 kg', 'Digital delivery',
        'Manufacturing Project Feasibility Report | 5-Yr Financials - Resources Enterprises',
        'Professional manufacturing feasibility study: 80+ pages, market research, 5yr P&L/Cashflow/Balance Sheet, ROI analysis, risk matrix, cost structure.',
        'feasibility report, manufacturing business plan, project report, financial projections, market research',
        'feasibility, business plan, report, manufacturing, consulting',
        'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=1400&q=80',
        1, 0,
    ],
];

$prodCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
if ($prodCount < 5) {
    $insertProd = $pdo->prepare("INSERT INTO products (name, slug, category, subcategory, description, short_description, price, stock, sku, weight, dimensions, image_url, meta_title, meta_description, meta_keywords, tags, active, featured, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    foreach ($seedProducts as $i => $p) {
        echo "  - Product " . ($i+1) . ": {$p[0]}... ";
        $insertProd->execute($p);
        echo "✅\n";
    }
} else {
    echo "  - Products already exist ($prodCount found), skipping seed.\n";
}

echo "\nCreating sample order for testing...\n";
$orderCount = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
if ($orderCount < 3 && $userId) {
    $sampleOrders = [
        ['user_id' => $userId, 'status' => 'Delivered', 'total' => 228.00, 'method' => 'cod', 'pstatus' => 'pending', 'items' => [[1, 149.00, 1], [2, 79.00, 1]]],
        ['user_id' => $userId, 'status' => 'Shipped', 'total' => 268.00, 'method' => 'jazzcash', 'pstatus' => 'paid', 'items' => [[3, 129.00, 1], [7, 119.00, 1], [8, 20.00, 0]]],
        ['user_id' => $userId, 'status' => 'Placed', 'total' => 348.00, 'method' => 'jazzcash', 'pstatus' => 'pending', 'items' => [[5, 79.00, 2], [9, 299.00, 1]]],
    ];

    $insertOrder = $pdo->prepare("INSERT INTO orders (user_id, status, total_amount, payment_method, payment_status, shipping_name, shipping_phone, shipping_address, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $insertItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, line_total) VALUES (?, ?, ?, ?, ?, ?)");

    foreach ($sampleOrders as $i => $o) {
        echo "  - Sample Order " . ($i+1) . "... ";
        $name = $i === 0 ? 'Ahmed Raza' : ($i === 1 ? 'Sara Khan' : 'Bilal Ahmed');
        $phone = $i === 0 ? '+92 300 1112233' : ($i === 1 ? '+92 321 2223344' : '+92 333 3334455');
        $addr = $i === 0 ? 'Office 404, Gulberg III, Lahore' : ($i === 1 ? 'House 78, Block 6, PECHS, Karachi' : 'Plot 12, Sector F-10, Islamabad');
        $notes = $i === 1 ? 'Please call before delivery.' : '';

        $insertOrder->execute([$o['user_id'], $o['status'], $o['total'], $o['method'], $o['pstatus'], $name, $phone, $addr, $notes]);
        $orderId = (int)$pdo->lastInsertId();

        $productsStmt = $pdo->query("SELECT id, name, price FROM products");
        $productsById = [];
        foreach ($productsStmt->fetchAll() as $pr) {
            $productsById[(int)$pr['id']] = $pr;
        }

        foreach ($o['items'] as $item) {
            $pid = $item[0];
            if (isset($productsById[$pid])) {
                $qty = $item[2] > 0 ? $item[2] : 1;
                $price = (float)$item[1];
                $lineTotal = $price * $qty;
                $insertItem->execute([$orderId, $pid, $productsById[$pid]['name'], $price, $qty, $lineTotal]);
            }
        }
        echo "✅\n";
    }
} else {
    echo "  - Orders already exist ($orderCount found), skipping sample orders.\n";
}

$pmCount = (int)$pdo->query("SELECT COUNT(*) FROM payment_methods")->fetchColumn();
if ($pmCount === 0) {
    echo "Seeding default payment methods...\n";
    $insertPm = $pdo->prepare("INSERT INTO payment_methods (name, code, account_title, account_number, bank_name, branch_code, instructions, qr_image, icon, active, sort_order, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $insertPm->execute(['Direct Bank Transfer', 'bank_transfer', 'Resources Enterprises', 'PK08ASCM0003790900002243', 'Askari Bank', '', 'Please transfer the total amount to our bank account. Use your Order ID as payment reference.', '', '🏦', 1, 1]);
    $insertPm->execute(['Cash on Delivery', 'cod', '', '', '', '', 'Pay with cash upon receipt of your package at your doorstep.', '', '💵', 1, 2]);
    $insertPm->execute(['JazzCash / Raast', 'jazzcash', 'Resources Enterprises', '984641936', 'JazzCash (Mobilink Bank)', '', 'Dial *786*10# and enter TILL ID 984641936 to pay via JazzCash, or scan the QR code.', 'assets/jazzcash_qr.jpg', '📱', 1, 3]);
    echo "✅ Seeded Bank Transfer, COD, and JazzCash\n";
} else {
    echo "  - Payment methods already exist ($pmCount found), skipping.\n";
}

$curCount = (int)$pdo->query("SELECT COUNT(*) FROM currencies")->fetchColumn();
if ($curCount === 0) {
    echo "Seeding default currencies...\n";
    $currenciesList = [
        ['PKR', 'Pakistani Rupee', 'Rs. ', 'before', 0, 1, 1],
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
    $insertCur = $pdo->prepare("INSERT INTO currencies (code, name, symbol, symbol_position, decimal_places, is_default, active, sort_order, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW())");
    foreach ($currenciesList as $c) {
        $insertCur->execute($c);
    }
    echo "✅ Seeded 21 world & regional currencies\n";
} else {
    echo "  - Currencies already exist ($curCount found), skipping.\n";
}

echo "\n✅ Sample data seeded successfully!\n\n";

echo "=== LOCAL CONFIG CREATION ===\n";
$localConfigPath = __DIR__ . '/includes/config.local.php';
$localConfig = <<<'PHP'
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'enterprise_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
PHP;
file_put_contents($localConfigPath, $localConfig);
echo "✅ Local config created: $localConfigPath\n\n";

echo "=== SUMMARY ===\n";
echo "✅ Database: enterprise_db (created)\n";
echo "✅ Tables: users, products, product_images, orders, order_items, reviews, favorites, collections, website_reviews\n";
echo "✅ SEO Fields: meta_title, meta_description, meta_keywords, tags, short_description, sku, weight, dimensions, featured\n";
echo "✅ Admin Login: admin@resource.com / admin123\n";
echo "✅ Test Customer: customer@test.com / user123\n";
echo "✅ Sample Products: 12 products with full SEO data\n";
echo "✅ Sample Orders: 3 orders (COD + JazzCash, delivered/shipped/placed)\n";
echo "✅ Config: Local config file created (config.local.php)\n\n";
echo "=== NEXT STEPS ===\n";
echo "1. Update includes/bootstrap.php to load config.local.php if exists\n";
echo "2. Visit: http://localhost/your-folder/\n";
echo "3. Admin panel: http://localhost/your-folder/admin/dashboard.php\n";
echo "4. Login with admin@resource.com / admin123\n\n";
echo "🎉 SETUP COMPLETE!\n";
echo "</pre>";
