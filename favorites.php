<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!is_logged_in()) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Please login to manage favorites.']);
            exit;
        }
        set_flash('error', 'Please login to manage favorites.');
        redirect('login.php');
    }

    $userId = current_user_id();
    $productId = (int) ($_POST['product_id'] ?? 0);

    if ($_POST['action'] === 'toggle') {
        try {
            // Check if already in favorites
            $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = :user_id AND product_id = :product_id");
            $stmt->execute([':user_id' => $userId, ':product_id' => $productId]);
            $fav = $stmt->fetch();

            if ($fav) {
                // Remove
                $del = $pdo->prepare("DELETE FROM favorites WHERE id = :id");
                $del->execute([':id' => $fav['id']]);
                $status = 'removed';
            } else {
                // Add
                $ins = $pdo->prepare("INSERT INTO favorites (user_id, product_id) VALUES (:user_id, :product_id)");
                $ins->execute([':user_id' => $userId, ':product_id' => $productId]);
                $status = 'added';
            }

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'status' => $status]);
                exit;
            }
        } catch (PDOException $e) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Database error.']);
                exit;
            }
        }
    }
}

// Display Page
$pageTitle = 'My Favorites | ' . APP_NAME;
$pageDescription = 'View and manage your favorite products.';

$userId = current_user_id();
$favorites = [];

if (is_logged_in()) {
    $stmt = $pdo->prepare("
        SELECT p.* FROM products p 
        JOIN favorites f ON p.id = f.product_id 
        WHERE f.user_id = :user_id AND p.active = 1 
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([':user_id' => $userId]);
    $favorites = $stmt->fetchAll();
}

include __DIR__ . '/includes/header.php';
?>

<section class="page-hero fade">
  <div class="section-inner">
    <p class="eyebrow">Wishlist</p>
    <h2>My Favorites</h2>
    <p>Your curated collection of premium products you love.</p>
  </div>
</section>

<section class="section">
  <div class="section-inner">
    <?php if (!is_logged_in()): ?>
      <article class="empty-state fade">
        <div style="font-size: 4rem; margin-bottom: 20px; opacity: 0.2;">🔒</div>
        <h3 style="font-family: 'Playfair Display', serif; font-size: 2rem; color: var(--brand); margin-bottom: 10px;">Login Required</h3>
        <p class="muted" style="margin-bottom: 30px;">Please login to see your favorites.</p>
        <a class="btn" href="login.php">Login Now</a>
      </article>
    <?php elseif ($favorites === []): ?>
      <article class="empty-state fade">
        <div style="font-size: 4rem; margin-bottom: 20px; opacity: 0.2;">❤️</div>
        <h3 style="font-family: 'Playfair Display', serif; font-size: 2rem; color: var(--brand); margin-bottom: 10px;">No Favorites Yet</h3>
        <p class="muted" style="margin-bottom: 30px;">Explore our collection and click the heart icon to save products here.</p>
        <a class="btn" href="products.php">Explore Products</a>
      </article>
    <?php else: ?>
      <div class="product-grid">
        <?php foreach ($favorites as $product): ?>
          <article class="product-card fade">
            <figure>
              <img src="<?= h($product['image_url']) ?>" alt="<?= h($product['name']) ?>">
              <div class="icons-overlay">
                <a href="product.php?slug=<?= urlencode($product['slug']) ?>" class="icon-action view-icon" title="View product">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                </a>
                <button class="icon-action heart-icon active" data-id="<?= h((string) $product['id']) ?>" title="Remove from favorites">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l8.78-8.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                  </svg>
                </button>
              </div>
            </figure>
            <div class="product-card-content">
              <p class="product-category"><?= h($product['category']) ?></p>
              <h3><?= h($product['name']) ?></h3>
              <p class="price"><?= h(money((float) $product['price'])) ?></p>
            </div>
            <form method="post" action="cart.php" class="bucket-form">
              <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="add">
              <input type="hidden" name="product_id" value="<?= h((string) $product['id']) ?>">
              <input type="hidden" name="quantity" value="1">
              <button type="submit" class="product-bucket">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"></path>
                  <line x1="3" y1="6" x2="21" y2="6"></line>
                  <path d="M16 10a4 4 0 0 1-8 0"></path>
                </svg>
                Add to Bucket
              </button>
            </form>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
