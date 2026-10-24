<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_login();

$user = current_user($pdo);

$ordersStmt = $pdo->prepare(
    'SELECT o.id, o.status, o.total_amount, o.payment_method, o.payment_status, o.transaction_id, o.created_at, o.shipping_name, o.shipping_phone, o.shipping_address, o.notes, COUNT(oi.id) AS item_count
     FROM orders o
     LEFT JOIN order_items oi ON oi.order_id = o.id
     WHERE o.user_id = :user_id
     GROUP BY o.id
     ORDER BY o.id DESC'
);
$ordersStmt->execute([':user_id' => $user['id']]);
$orders = $ordersStmt->fetchAll();

// Fetch payment methods lookup
$pmStmt = $pdo->query('SELECT code, name, icon, bank_name, account_title, account_number, instructions, qr_image FROM payment_methods');
$paymentMethodsByCode = [];
foreach ($pmStmt->fetchAll() as $pmRow) {
    $paymentMethodsByCode[$pmRow['code']] = $pmRow;
}

$orderItemsByOrderId = [];
if ($orders !== []) {
    $orderIds = array_map(static fn(array $order): int => (int) $order['id'], $orders);
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $itemsStmt = $pdo->prepare("SELECT order_id, product_name, image_url, quantity, unit_price, line_total FROM order_items WHERE order_id IN ($placeholders) ORDER BY id ASC");
    $itemsStmt->execute($orderIds);

    foreach ($itemsStmt->fetchAll() as $item) {
        $orderId = (int) $item['order_id'];
        if (!isset($orderItemsByOrderId[$orderId])) {
            $orderItemsByOrderId[$orderId] = [];
        }
        $orderItemsByOrderId[$orderId][] = $item;
    }
}

$pageTitle = 'My Orders | ' . APP_NAME;
$pageDescription = 'View all orders you placed on Resources Enterprises ecommerce store.';

include __DIR__ . '/includes/header.php';
?>
<section class="page-hero fade">
  <div class="section-inner">
    <p class="eyebrow">Order History</p>
    <h2>My Orders</h2>
    <p>Track every order you placed and review shipping and payment details.</p>
  </div>
</section>

<section class="dashboard-wrap">
  <div class="section-inner">
    <?php if ($orders === []): ?>
      <article class="empty-state fade" style="text-align: center; padding: 60px 20px;">
        <div style="font-size: 4rem; margin-bottom: 20px; opacity: 0.2;">📦</div>
        <h3 style="margin-bottom: 10px; font-family: 'Playfair Display', serif; font-size: 2rem;">No Orders Yet</h3>
        <p class="muted" style="margin-bottom: 30px;">Your order history will appear here once you checkout.</p>
        <a href="products.php" class="btn">Shop Products</a>
      </article>
    <?php else: ?>
      <div class="order-list">
        <?php foreach ($orders as $order): ?>
          <article class="order-card fade" style="padding: 35px; border-radius: 24px; margin-bottom: 30px; background: #fff; box-shadow: 0 15px 40px rgba(0,0,0,0.03);">
            <div class="order-head" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--line); padding-bottom: 20px; flex-wrap: wrap; gap: 15px;">
              <div>
                <strong style="font-family: 'Playfair Display', serif; font-size: 1.6rem; color: var(--brand);">Order #<?= h((string) $order['id']) ?></strong>
                <p class="helper-text" style="margin-top: 5px; font-weight: 600;">Placed on <?= h(date('d M Y, h:i A', strtotime((string) $order['created_at']))) ?></p>
              </div>
              <span class="badge placed" style="font-size: 0.85rem; padding: 8px 20px;"><?= h($order['status']) ?></span>
            </div>

            <?php
              $methodCode = (string)($order['payment_method'] ?? 'cod');
              $pmInfo = $paymentMethodsByCode[$methodCode] ?? null;

              $paymentMethodLabel = $pmInfo['name'] ?? ucfirst($methodCode);
              $paymentMethodIcon = $pmInfo['icon'] ?? '💳';

              $paymentStatusLabel = $order['payment_status'] ?? 'pending';
              $paymentStatusBadgeClass = 'badge placed';
              if ($paymentStatusLabel === 'paid') {
                $paymentStatusBadgeClass = 'badge paid';
                $paymentStatusLabel = 'Paid';
              } elseif ($paymentStatusLabel === 'pending') {
                $paymentStatusBadgeClass = 'badge placed';
                $paymentStatusLabel = 'Pending';
              } else {
                $paymentStatusBadgeClass = 'badge placed';
              }
            ?>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin: 30px 0; padding: 25px; background: var(--bg-soft); border-radius: 16px;">
              <div>
                <p class="eyebrow" style="margin-bottom: 8px; font-size: 0.65rem;">Shipping Details</p>
                <p style="font-weight: 700; color: var(--brand); margin-bottom: 5px;"><?= h($order['shipping_name']) ?></p>
                <p class="helper-text" style="margin-bottom: 5px;"><?= h($order['shipping_phone']) ?></p>
                <p class="helper-text"><?= h($order['shipping_address']) ?></p>
              </div>
              <div>
                <p class="eyebrow" style="margin-bottom: 8px; font-size: 0.65rem;">Payment Information</p>
                <p style="font-weight: 700; color: var(--brand); margin-bottom: 10px; font-size: 1.05rem;">
                  <span style="margin-right: 8px;"><?= h($paymentMethodIcon) ?></span>
                  <?= h($paymentMethodLabel) ?>
                </p>
                <span class="<?= h($paymentStatusBadgeClass) ?>" style="font-size: 0.8rem; padding: 6px 16px;">Payment: <?= h($paymentStatusLabel) ?></span>
                
                <?php if (!empty($order['transaction_id'])): ?>
                  <p class="helper-text" style="margin-top: 10px; font-weight: 600;">
                    Ref / TID: <code style="background: #fff; padding: 2px 6px; border-radius: 4px;"><?= h($order['transaction_id']) ?></code>
                  </p>
                <?php endif; ?>

                <?php if (trim((string) $order['notes']) !== ''): ?>
                  <div style="margin-top: 15px;">
                    <p class="eyebrow" style="margin-bottom: 6px; font-size: 0.65rem;">Order Notes</p>
                    <p class="helper-text"><?= h($order['notes']) ?></p>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <?php if ($methodCode === 'bank_transfer' && $pmInfo && !empty($pmInfo['account_number'])): ?>
              <!-- Bank Transfer instructions card for customer -->
              <div style="margin-bottom: 25px; padding: 20px 25px; background: #FFF9E6; border-radius: 14px; border: 2px solid #FFE082;">
                <h4 style="margin: 0 0 10px; color: var(--brand); font-size: 1.05rem;">🏦 Bank Transfer Details</h4>
                <div style="display: flex; gap: 25px; flex-wrap: wrap; margin-bottom: 10px; font-size: 0.95rem;">
                  <?php if (!empty($pmInfo['account_title'])): ?>
                    <div><strong>Title:</strong> <?= h($pmInfo['account_title']) ?></div>
                  <?php endif; ?>
                  <div><strong>IBAN:</strong> <code style="font-weight: 800; color: #5D100B;"><?= h($pmInfo['account_number']) ?></code></div>
                  <?php if (!empty($pmInfo['bank_name'])): ?>
                    <div><strong>Bank:</strong> <?= h($pmInfo['bank_name']) ?></div>
                  <?php endif; ?>
                </div>
                <?php if (!empty($pmInfo['instructions'])): ?>
                  <p style="margin: 0; font-size: 0.85rem; color: #666;"><?= h($pmInfo['instructions']) ?></p>
                <?php endif; ?>
              </div>
            <?php elseif ($methodCode === 'jazzcash' && $pmInfo): ?>
              <!-- JazzCash instructions card -->
              <div style="margin-bottom: 25px; padding: 20px 25px; background: linear-gradient(135deg, #FFD400 0%, #FFC107 100%); border-radius: 14px; border: 2px solid #E5A800;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                  <div>
                    <p style="margin: 0; font-weight: 800; color: #333; font-size: 1rem;">JazzCash TILL ID: <span style="background: #fff; padding: 4px 12px; border-radius: 8px; color: #D21F2A; margin-left: 8px;"><?= h($pmInfo['account_number'] ?: '984641936') ?></span></p>
                    <p style="margin: 6px 0 0; font-size: 0.85rem; color: #555; font-weight: 600;">Dial *786*10# to pay • <?= h($pmInfo['instructions'] ?: 'Fast and secure') ?></p>
                  </div>
                  <div style="background: #fff; padding: 10px 16px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <span style="font-weight: 800; font-size: 0.9rem; color: #D21F2A;">JazzCash + Raast</span>
                  </div>
                </div>
              </div>
            <?php endif; ?>

            <p class="eyebrow" style="margin-bottom: 15px; font-size: 0.65rem;">Items Purchased</p>
            <ul class="order-items" style="list-style: none; padding: 0; margin-bottom: 25px;">
              <?php foreach ($orderItemsByOrderId[(int) $order['id']] ?? [] as $item): ?>
                <li class="order-item" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--line);">
                  <div style="display: flex; align-items: center; gap: 12px;">
                    <?php if (!empty($item['image_url'])): ?>
                      <img src="<?= h(url($item['image_url'])) ?>" alt="<?= h($item['product_name']) ?>" style="width: 42px; height: 42px; object-fit: cover; border-radius: 8px; border: 1px solid var(--line);">
                    <?php endif; ?>
                    <span style="font-weight: 600; color: var(--brand);">
                      <?= h($item['product_name']) ?> <span style="color: var(--muted); font-weight: 500; margin-left: 10px;">x <?= h((string) $item['quantity']) ?></span>
                    </span>
                  </div>
                  <span style="font-weight: 700; color: var(--brand);"><?= h(money((float) $item['line_total'])) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>

            <div class="order-total" style="padding: 25px 0 0; border-top: 2px solid var(--brand); display: flex; justify-content: space-between; align-items: center;">
              <span style="color: var(--muted); font-weight: 600; font-size: 1.1rem;">Total Amount</span>
              <span style="font-family: 'Playfair Display', serif; font-size: 2rem; color: #5D100B; font-weight: 800;"><?= h(money((float) $order['total_amount'])) ?></span>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
