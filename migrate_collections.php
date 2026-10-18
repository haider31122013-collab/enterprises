<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS collections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            image_url TEXT NOT NULL,
            link_url VARCHAR(255) DEFAULT 'products.php',
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Insert some initial data if empty
    $count = $pdo->query("SELECT COUNT(*) FROM collections")->fetchColumn();
    if ($count == 0) {
        $pdo->exec("
            INSERT INTO collections (title, image_url, link_url, sort_order) VALUES 
            ('Luxury Cutlery', 'https://images.unsplash.com/photo-1594913785162-e6785b48dc51?auto=format&fit=crop&w=1200&q=80', 'products.php?category=Cutlery', 1),
            ('Artisan Salt', 'https://images.unsplash.com/photo-1515488764276-beab7607c1e6?auto=format&fit=crop&w=1200&q=80', 'products.php?category=Salt Artisans', 2),
            ('Premium Kitchenware', 'https://images.unsplash.com/photo-1556910103-1c02745aae4d?auto=format&fit=crop&w=1200&q=80', 'products.php', 3),
            ('Dining Sets', 'https://images.unsplash.com/photo-1516714435131-44d6b64dc6a2?auto=format&fit=crop&w=1200&q=80', 'products.php', 4),
            ('Modern Cookware', 'https://images.unsplash.com/photo-1584990333910-efed3bc72fdf?auto=format&fit=crop&w=1200&q=80', 'products.php', 5),
            ('Artisan Bowls', 'https://images.unsplash.com/photo-1590080874088-eec64895b423?auto=format&fit=crop&w=1200&q=80', 'products.php', 6)
        ");
    }

    echo "Collections table created and seeded successfully!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
