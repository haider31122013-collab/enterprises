<?php
require_once __DIR__ . '/includes/bootstrap.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS website_reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        rating INT NOT NULL,
        comment TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Table 'website_reviews' created successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
