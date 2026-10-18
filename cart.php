<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    enforce_csrf();

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add') {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $quantity = max(1, (int) ($_POST['quantity'] ?? 1));

        $stmt = $pdo->prepare('SELECT id, name, price, image_url, stock FROM products WHERE active = 1 AND id = :id LIMIT 1');
        $stmt->execute([':id' => $productId]);
        $product = $stmt->fetch();

        if ($product) {
            $quantity = min($quantity, max(1, (int) $product['stock']));
            add_to_cart($productId, $quantity);
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Product added to your cart.',
                    'cart_count' => cart_count()
                ]);
                exit;
            }
            set_flash('success', 'Product added to your cart.');
        } else {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Product is unavailable.']);
                exit;
            }
            set_flash('error', 'Product is unavailable.');
        }

        redirect('cart.php');
    }

    if ($action === 'update') {
        if (isset($_POST['qty']) && is_array($_POST['qty'])) {
            foreach ($_POST['qty'] as $productId => $qty) {
                update_cart_item((int) $productId, max(0, (int) $qty));
            }
        } elseif (isset($_POST['product_id']) && isset($_POST['quantity'])) {
            update_cart_item((int) $_POST['product_id'], max(0, (int) $_POST['quantity']));
        }
        
        if ($isAjax) {
            $items = load_cart_items($pdo);
            $subtotal = cart_subtotal($items);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Cart updated successfully.',
                'cart_count' => cart_count(),
                'subtotal' => money($subtotal),
                'items' => $items
            ]);
            exit;
        }
        
        set_flash('success', 'Cart updated successfully.');
        redirect('cart.php');
    }

    if ($action === 'remove') {
        $productId = (int) ($_POST['product_id'] ?? 0);
        remove_cart_item($productId);
        set_flash('success', 'Item removed from cart.');
        redirect('cart.php');
    }

    if ($action === 'clear') {
        clear_cart();
        set_flash('success', 'Cart cleared.');
        redirect('cart.php');
    }
}

$pageTitle = 'Your Cart | ' . APP_NAME;
$pageDescription = 'Review and update your shopping cart before checkout.';

$items = load_cart_items($pdo);
$subtotal = cart_subtotal($items);

include __DIR__ . '/includes/header.php';
?>
<section class="page-hero fade">
  <div class="section-inner">
    <p class="eyebrow">Shopping</p>
    <h2 class="cart-title">Your Cart</h2>
    <p>Review your selected products before placing your order.</p>
  </div>
</section>

<section class="cart-grid">
  <div>
    <?php if ($items === []): ?>
      <article class="empty-state fade">
        <div style="font-size: 4rem; margin-bottom: 20px; opacity: 0.2;">🛒</div>
        <h3 style="margin-bottom: 10px; font-family: 'Playfair Display', serif; font-size: 2rem;">Cart Is Empty</h3>
        <p class="muted" style="margin-bottom: 30px;">Add products to continue with checkout.</p>
        <a href="products.php" class="btn">Browse Products</a>
      </article>
    <?php else: ?>
      <div class="cart-list">
        <?php foreach ($items as $item): ?>
          <article class="cart-item fade">
            <figure class="cart-item-media">
              <img src="<?= h($item['image_url']) ?>" alt="<?= h($item['name']) ?>">
            </figure>

            <div class="cart-item-info">
              <p class="cart-item-title"><?= h($item['name']) ?></p>
              <p class="product-category" style="margin-bottom: 15px;"><?= h($item['category']) ?></p>
              <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <div>
                  <p class="eyebrow" style="margin-bottom: 4px; font-size: 0.6rem;">Unit Price</p>
                  <p style="font-weight: 700; color: var(--brand);"><?= h(money((float) $item['price'])) ?></p>
                </div>
                <div>
                  <p class="eyebrow" style="margin-bottom: 4px; font-size: 0.6rem;">Total</p>
                  <p style="font-weight: 700; color: #5D100B;"><?= h(money((float) $item['line_total'])) ?></p>
                </div>
              </div>
            </div>

            <div class="cart-item-actions">
              <form method="post" action="cart.php" class="inline-form" style="display: flex; gap: 10px; align-items: center;">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="update">
                <input type="number" class="qty-input" min="0" max="<?= h((string) $item['stock']) ?>" name="qty[<?= h((string) $item['id']) ?>]" value="<?= h((string) $item['quantity']) ?>" aria-label="Quantity for <?= h($item['name']) ?>" style="width: 70px; border-radius: 8px;">
                <button type="submit" class="btn small-btn" style="padding: 12px 20px;">Update</button>
              </form>

              <form method="post" action="cart.php">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="product_id" value="<?= h((string) $item['id']) ?>">
                <button type="submit" class="btn btn-outline small-btn" style="padding: 12px 20px; width: 100%;">Remove</button>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <aside class="cart-summary fade">
    <h3 style="margin-bottom: 25px; font-family: 'Playfair Display', serif; font-size: 1.8rem;">Order Summary</h3>
    <div style="margin-bottom: 25px;">
      <div style="display: flex; justify-content: space-between; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid var(--line);">
        <span class="helper-text">Items (<?= h((string) cart_count()) ?>)</span>
        <span style="font-weight: 700; color: var(--brand);"><?= h(money((float) $subtotal)) ?></span>
      </div>
      <div style="display: flex; justify-content: space-between; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid var(--line);">
        <span class="helper-text">Shipping</span>
        <span style="font-weight: 700; color: #38a169;">FREE</span>
      </div>
      <div style="display: flex; justify-content: space-between; margin-top: 25px;">
        <span style="font-size: 1.2rem; font-weight: 800; color: var(--brand);">Subtotal</span>
        <span class="price" style="margin: 0; color: #5D100B; font-size: 1.8rem;"><?= h(money((float) $subtotal)) ?></span>
      </div>
    </div>

    <div style="display: flex; flex-direction: column; gap: 15px;">
      <a href="checkout.php" class="btn" style="width: 100%; padding: 20px;">Proceed to Checkout</a>
      <a href="products.php" class="btn btn-outline" style="width: 100%; padding: 20px;">Continue Shopping</a>
    </div>

    <?php if ($items !== []): ?>
      <form method="post" action="cart.php" style="margin-top: 30px; text-align: center;">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="clear">
        <button type="submit" style="background: none; border: none; color: #c53030; font-weight: 700; cursor: pointer; text-decoration: underline; font-size: 0.85rem; opacity: 0.7;">Clear Shopping Cart</button>
      </form>
    <?php endif; ?>
  </aside>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
