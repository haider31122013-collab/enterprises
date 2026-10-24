-- Resources Enterprises - MySQL/MariaDB schema
--
-- This file matches the schema the PHP app actually queries:
--   users.full_name + users.password_hash + users.role  -> signup.php / login.php / require_admin()
--   orders.total_amount + payment_method/payment_status  -> checkout.php / admin orders
--   order_items.unit_price + line_total                  -> checkout.php / orders.php
--   reviews.comment                                      -> product.php
--   website_reviews.comment                              -> includes/footer.php
--   favorites / collections / product_images             -> favorites.php / collections / product gallery
--   contact_messages                                     -> contact.php / admin messages page
--
-- Every statement is idempotent, so the file can be imported more than once.
-- Alternative: open setup_local_database.php in the browser, which does the same job.

CREATE DATABASE IF NOT EXISTS enterprise_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE enterprise_db;

-- ---------------------------------------------------------------------------
-- Users
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL DEFAULT 'User',
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'user',
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Products (catalog + SEO fields used by admin/products.php and product.php)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    category VARCHAR(100) NOT NULL,
    subcategory VARCHAR(150) DEFAULT NULL,
    description TEXT NOT NULL,
    short_description VARCHAR(500) DEFAULT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    stock INT NOT NULL DEFAULT 100,
    sku VARCHAR(100) DEFAULT NULL,
    weight VARCHAR(50) DEFAULT NULL,
    dimensions VARCHAR(100) DEFAULT NULL,
    image_url TEXT NOT NULL,
    meta_title VARCHAR(255) DEFAULT NULL,
    meta_description VARCHAR(500) DEFAULT NULL,
    meta_keywords VARCHAR(500) DEFAULT NULL,
    tags VARCHAR(500) DEFAULT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    featured TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Product gallery (product.php slider)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image_url TEXT NOT NULL,
    is_main TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Orders
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Placed',
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_method VARCHAR(50) NOT NULL DEFAULT 'cod',
    payment_status VARCHAR(50) NOT NULL DEFAULT 'pending',
    transaction_id VARCHAR(200) DEFAULT NULL,
    shipping_name VARCHAR(255) NOT NULL,
    shipping_phone VARCHAR(50) NOT NULL,
    shipping_address TEXT NOT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Order items
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT DEFAULT NULL,
    product_name VARCHAR(255) NOT NULL,
    image_url TEXT DEFAULT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL,
    line_total DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Payment Methods
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS payment_methods (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Currencies
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS currencies (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Product reviews
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL DEFAULT 5,
    comment TEXT NOT NULL,
    review_text TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Wishlist
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY unique_favorite (user_id, product_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Homepage collections
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS collections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    image_url TEXT,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Homepage testimonials (footer)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS website_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    customer_name VARCHAR(100) NOT NULL DEFAULT '',
    rating INT NOT NULL DEFAULT 5,
    title VARCHAR(255) DEFAULT NULL,
    comment TEXT NOT NULL,
    approved TINYINT(1) NOT NULL DEFAULT 0,
    review_text TEXT DEFAULT NULL,
    visible TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Contact form inquiries (contact.php -> admin/messages.php)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    topic VARCHAR(255) NOT NULL DEFAULT 'General Inquiry',
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    KEY idx_contact_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Default accounts (same credentials as setup_local_database.php)
-- Seeded only when the users table is empty, so importing this file into an
-- existing database never creates duplicates.
-- ---------------------------------------------------------------------------
INSERT INTO users (full_name, email, password_hash, phone, address, role, created_at)
SELECT s.full_name, s.email, s.password_hash, s.phone, s.address, s.role, NOW()
FROM (
    SELECT 'Administrator' AS full_name, 'admin@resource.com' AS email, '$2y$10$00Qb/XhRh.J6ooXaofaYiO/1vlhyKKwRhvr8k97XbYEI.sYrdwp3O' AS password_hash, '+92 300 1234567' AS phone, 'Head Office - Resources Enterprises' AS address, 'admin' AS role
    UNION ALL
    SELECT 'Test Customer', 'customer@test.com', '$2y$10$00Qb/XhRh.J6ooXaofaYiO/1vlhyKKwRhvr8k97XbYEI.sYrdwp3O', '+92 321 9876543', 'House 123, Model Town', 'user'
) AS s
WHERE (SELECT c.cnt FROM (SELECT COUNT(*) AS cnt FROM users) AS c) = 0;

-- ---------------------------------------------------------------------------
-- Seed products - only when the catalog is empty
-- ---------------------------------------------------------------------------
INSERT INTO products (name, slug, category, description, price, stock, image_url, active, created_at)
SELECT s.name, s.slug, s.category, s.description, s.price, s.stock, s.image_url, 1, NOW()
FROM (
    SELECT 'Premium Chef Knife Set' AS name, 'premium-chef-knife-set' AS slug, 'Cutlery' AS category, 'Precision-forged stainless steel knives for professional kitchens and premium retail lines.' AS description, 149.00 AS price, 120 AS stock, 'https://images.unsplash.com/photo-1507648154934-f230d5bd6942?auto=format&fit=crop&w=1400&q=80' AS image_url
    UNION ALL SELECT 'Craft Utility Blade Collection', 'craft-utility-blade-collection', 'Cutlery', 'Durable utility blades with balanced grip and anti-rust finish for daily heavy use.', 89.00, 160, 'https://images.unsplash.com/photo-1580802849130-0f4bc9e1a45b?auto=format&fit=crop&w=1200&q=80'
    UNION ALL SELECT 'Urban Comfort Sneakers', 'urban-comfort-sneakers', 'Footwear', 'Lifestyle sneakers designed for all-day comfort with export-grade stitching and sole grip.', 129.00, 200, 'https://images.unsplash.com/photo-1559513493-8ee89c9e69ee?auto=format&fit=crop&w=1400&q=80'
    UNION ALL SELECT 'Trail Runner Performance Shoes', 'trail-runner-performance-shoes', 'Footwear', 'Lightweight and breathable performance shoes with shock absorption and reinforced support.', 139.00, 150, 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=1400&q=80'
    UNION ALL SELECT 'Himalayan Salt Lamp Duo', 'himalayan-salt-lamp-duo', 'Salt Artisans', 'Handcrafted lamp pair with natural warm tones, ideal for retail, gifting, and decor programs.', 79.00, 180, 'https://images.unsplash.com/photo-1579101450104-5890cd7db9ad?auto=format&fit=crop&w=1400&q=80'
    UNION ALL SELECT 'Salt Serving Bowl Set', 'salt-serving-bowl-set', 'Salt Artisans', 'Food-safe salt serving bowl set crafted for premium dining and hospitality collections.', 69.00, 140, 'https://images.unsplash.com/photo-1579101450453-c9fb7b8a490d?auto=format&fit=crop&w=1200&q=80'
    UNION ALL SELECT 'Modern Ceramic Dinner Set', 'modern-ceramic-dinner-set', 'Ceramics', 'Minimal ceramic dinnerware set for modern homes, cafes, and hotel supply programs.', 119.00, 170, 'https://images.unsplash.com/photo-1610701596007-11502861dcfa?auto=format&fit=crop&w=1200&q=80'
    UNION ALL SELECT 'Artisan Glazed Mug Pack', 'artisan-glazed-mug-pack', 'Ceramics', 'Set of handcrafted mugs with premium glaze finish suitable for retail and custom branding.', 59.00, 220, 'https://images.unsplash.com/photo-1514228742587-6b1558fcca3d?auto=format&fit=crop&w=1200&q=80'
) AS s
WHERE (SELECT c.cnt FROM (SELECT COUNT(*) AS cnt FROM products) AS c) = 0;

-- ---------------------------------------------------------------------------
-- Seed payment methods - only when empty
-- ---------------------------------------------------------------------------
INSERT INTO payment_methods (name, code, account_title, account_number, bank_name, branch_code, instructions, qr_image, icon, active, sort_order, created_at)
SELECT s.name, s.code, s.account_title, s.account_number, s.bank_name, s.branch_code, s.instructions, s.qr_image, s.icon, s.active, s.sort_order, NOW()
FROM (
    SELECT 'Direct Bank Transfer' AS name, 'bank_transfer' AS code, 'Resources Enterprises' AS account_title, 'PK08ASCM0003790900002243' AS account_number, 'Askari Bank' AS bank_name, '' AS branch_code, 'Please transfer the total amount to our bank account. Use your Order ID as payment reference.' AS instructions, '' AS qr_image, '🏦' AS icon, 1 AS active, 1 AS sort_order
    UNION ALL SELECT 'Cash on Delivery', 'cod', '', '', '', '', 'Pay with cash upon receipt of your package at your doorstep.', '', '💵', 1, 2
    UNION ALL SELECT 'JazzCash / Raast', 'jazzcash', 'Resources Enterprises', '984641936', 'JazzCash (Mobilink Bank)', '', 'Dial *786*10# and enter TILL ID 984641936 to pay via JazzCash, or scan the QR code.', 'assets/jazzcash_qr.jpg', '📱', 1, 3
) AS s
WHERE (SELECT c.cnt FROM (SELECT COUNT(*) AS cnt FROM payment_methods) AS c) = 0;

-- ---------------------------------------------------------------------------
-- Seed currencies - only when empty
-- ---------------------------------------------------------------------------
INSERT INTO currencies (code, name, symbol, symbol_position, decimal_places, is_default, active, sort_order, created_at)
SELECT s.code, s.name, s.symbol, s.symbol_position, s.decimal_places, s.is_default, 1, s.sort_order, NOW()
FROM (
    SELECT 'PKR' AS code, 'Pakistani Rupee' AS name, 'Rs. ' AS symbol, 'before' AS symbol_position, 0 AS decimal_places, 1 AS is_default, 1 AS sort_order
    UNION ALL SELECT 'USD', 'US Dollar', '$', 'before', 2, 0, 2
    UNION ALL SELECT 'EUR', 'Euro', '€', 'before', 2, 0, 3
    UNION ALL SELECT 'GBP', 'British Pound', '£', 'before', 2, 0, 4
    UNION ALL SELECT 'AED', 'UAE Dirham', 'AED ', 'before', 2, 0, 5
    UNION ALL SELECT 'SAR', 'Saudi Riyal', 'SAR ', 'before', 2, 0, 6
    UNION ALL SELECT 'CAD', 'Canadian Dollar', 'CA$ ', 'before', 2, 0, 7
    UNION ALL SELECT 'AUD', 'Australian Dollar', 'AU$ ', 'before', 2, 0, 8
    UNION ALL SELECT 'QAR', 'Qatari Riyal', 'QAR ', 'before', 2, 0, 9
    UNION ALL SELECT 'KWD', 'Kuwaiti Dinar', 'KWD ', 'before', 3, 0, 10
    UNION ALL SELECT 'OMR', 'Omani Rial', 'OMR ', 'before', 3, 0, 11
    UNION ALL SELECT 'BHD', 'Bahraini Dinar', 'BHD ', 'before', 3, 0, 12
    UNION ALL SELECT 'TRY', 'Turkish Lira', '₺', 'before', 2, 0, 13
    UNION ALL SELECT 'INR', 'Indian Rupee', '₹', 'before', 2, 0, 14
    UNION ALL SELECT 'CNY', 'Chinese Yuan', '¥', 'before', 2, 0, 15
    UNION ALL SELECT 'JPY', 'Japanese Yen', '¥', 'before', 0, 0, 16
    UNION ALL SELECT 'CHF', 'Swiss Franc', 'CHF ', 'before', 2, 0, 17
    UNION ALL SELECT 'SGD', 'Singapore Dollar', 'SG$ ', 'before', 2, 0, 18
    UNION ALL SELECT 'MYR', 'Malaysian Ringgit', 'RM ', 'before', 2, 0, 19
    UNION ALL SELECT 'NZD', 'New Zealand Dollar', 'NZ$ ', 'before', 2, 0, 20
    UNION ALL SELECT 'ZAR', 'South African Rand', 'R ', 'before', 2, 0, 21
) AS s
WHERE (SELECT c.cnt FROM (SELECT COUNT(*) AS cnt FROM currencies) AS c) = 0;


