<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin($pdo);

$pageTitle = 'Order Details';

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) {
    set_flash('error', 'Invalid order ID.');
    redirect('orders.php');
}

$orderStmt = $pdo->prepare(
    'SELECT o.*, u.full_name AS customer_name, u.email AS customer_email, u.phone AS customer_phone
     FROM orders o
     LEFT JOIN users u ON u.id = o.user_id
     WHERE o.id = ?'
);
$orderStmt->execute([$orderId]);
$order = $orderStmt->fetch();

if (!$order) {
    set_flash('error', 'Order not found.');
    redirect('orders.php');
}

// Fetch order items with live product info
$itemsStmt = $pdo->prepare(
    'SELECT oi.*, p.id AS live_product_id, p.name AS current_name, p.image_url AS current_image, p.stock AS current_stock, p.active AS current_active, p.category AS current_category
     FROM order_items oi
     LEFT JOIN products p ON p.id = oi.product_id
     WHERE oi.order_id = ?
     ORDER BY oi.id ASC'
);
$itemsStmt->execute([$orderId]);
$orderItems = $itemsStmt->fetchAll();

// Fetch payment method details from database
$pmStmt = $pdo->prepare('SELECT * FROM payment_methods WHERE code = ? LIMIT 1');
$pmStmt->execute([(string)$order['payment_method']]);
$paymentMethodObj = $pmStmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $newStatus = trim((string)($_POST['new_status'] ?? 'Placed'));
        if (in_array($newStatus, ['Placed', 'Processing', 'Shipped', 'Delivered', 'Cancelled'], true)) {
            $stmt = $pdo->prepare('UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$newStatus, $orderId]);
            set_flash('success', 'Order status updated to: ' . $newStatus);
        }
    } elseif ($action === 'update_payment') {
        $newPaymentStatus = trim((string)($_POST['new_payment_status'] ?? 'pending'));
        if (in_array($newPaymentStatus, ['pending', 'paid', 'failed', 'refunded'], true)) {
            $stmt = $pdo->prepare('UPDATE orders SET payment_status = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$newPaymentStatus, $orderId]);
            set_flash('success', 'Payment status updated.');
        }
    } elseif ($action === 'update_transaction_id') {
        $newTxnId = trim((string)($_POST['transaction_id'] ?? ''));
        $stmt = $pdo->prepare('UPDATE orders SET transaction_id = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$newTxnId !== '' ? $newTxnId : null, $orderId]);
        set_flash('success', 'Transaction ID / Payment Reference updated.');
    }
    redirect('order-details.php?id=' . $orderId);
}

$flashSuccess = get_flash('success');
$flashError = get_flash('error');

function paymentBadgeClass(string $status): string {
    return match ($status) {
        'paid' => 'paid',
        'pending' => 'placed',
        'failed' => 'role-admin',
        'refunded' => 'placed',
        default => 'placed',
    };
}

function orderStatusBadgeClass(string $status): string {
    return match ($status) {
        'Delivered' => 'paid',
        'Cancelled' => 'role-admin',
        'Shipped' => 'placed',
        'Processing' => 'placed',
        default => 'placed',
    };
}

include __DIR__ . '/../includes/header.php';
?>
<div class="admin-layout">
  <aside class="admin-sidebar">
    <nav class="admin-nav">
      <a href="dashboard.php" class="admin-nav-item">Dashboard</a>
      <a href="orders.php" class="admin-nav-item active">Manage Orders</a>
      <a href="products.php" class="admin-nav-item">Manage Products</a>
      <a href="payment-methods.php" class="admin-nav-item">Payment Methods</a>
      <a href="currencies.php" class="admin-nav-item">Currencies</a>
      <a href="users.php" class="admin-nav-item">Manage Users</a>
      <a href="reviews.php" class="admin-nav-item">Manage Reviews</a>
      <a href="collections.php" class="admin-nav-item">Collections</a>
      <a href="messages.php" class="admin-nav-item">Messages</a>
      <a href="settings.php" class="admin-nav-item">Settings</a>
    </nav>
  </aside>

  <main class="admin-main">
    <header class="admin-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
      <div>
        <a href="orders.php" style="color: var(--muted); text-decoration: none; font-weight: 600; font-size: 0.9rem;">← Back to All Orders</a>
        <h1 style="margin-top: 10px;">Order #<?= (int)$order['id'] ?></h1>
      </div>
      <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <form method="post" action="order-details.php?id=<?= $orderId ?>" style="display: flex; gap: 8px; align-items: center;">
          <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="action" value="update_status">
          <select name="new_status" class="input" style="width: auto; padding: 8px 12px; font-size: 0.85rem;">
            <?php foreach (['Placed', 'Processing', 'Shipped', 'Delivered', 'Cancelled'] as $st): ?>
              <option value="<?= h($st) ?>" <?= $order['status'] === $st ? 'selected' : '' ?>><?= h($st) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn small-btn">Update Status</button>
        </form>
        <form method="post" action="order-details.php?id=<?= $orderId ?>" style="display: flex; gap: 8px; align-items: center;">
          <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="action" value="update_payment">
          <select name="new_payment_status" class="input" style="width: auto; padding: 8px 12px; font-size: 0.85rem;">
            <option value="pending" <?= $order['payment_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="paid" <?= $order['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
            <option value="failed" <?= $order['payment_status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
            <option value="refunded" <?= $order['payment_status'] === 'refunded' ? 'selected' : '' ?>>Refunded</option>
          </select>
          <button type="submit" class="btn small-btn">Update Payment</button>
        </form>
      </div>
    </header>

    <?php if ($flashSuccess): ?>
      <div class="flash success" style="margin-bottom: 25px; padding: 15px 20px; background: #D4EDDA; color: #155724; border-radius: 8px; font-weight: 600;"><?= h($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
      <div class="flash error" style="margin-bottom: 25px; padding: 15px 20px; background: #F8D7DA; color: #721C24; border-radius: 8px; font-weight: 600;"><?= h($flashError) ?></div>
    <?php endif; ?>

    <!-- Order Overview Cards Grid -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 25px;">
      
      <!-- Customer Information -->
      <div class="detail-card" style="background: var(--card); padding: 28px; border-radius: 16px; box-shadow: var(--shadow);">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 18px; padding-bottom: 14px; border-bottom: 1px solid var(--line);">
          <span style="font-size: 1.5rem;">👤</span>
          <h3 style="font-family: 'Playfair Display', serif; font-size: 1.35rem; color: var(--brand); margin: 0;">Customer Information</h3>
        </div>
        <div style="display: grid; gap: 12px;">
          <div style="display: flex; justify-content: space-between;">
            <span class="detail-label">Name:</span>
            <span class="detail-value"><?= h($order['customer_name'] ?? 'N/A') ?></span>
          </div>
          <div style="display: flex; justify-content: space-between;">
            <span class="detail-label">Email:</span>
            <span class="detail-value"><?= h($order['customer_email'] ?? 'N/A') ?></span>
          </div>
          <div style="display: flex; justify-content: space-between;">
            <span class="detail-label">Customer Phone:</span>
            <span class="detail-value"><?= h($order['customer_phone'] ?? 'N/A') ?></span>
          </div>
          <div style="display: flex; justify-content: space-between;">
            <span class="detail-label">User Account ID:</span>
            <span class="detail-value">#<?= (int)$order['user_id'] ?></span>
          </div>
        </div>
      </div>

      <!-- Order Status & Summary -->
      <div class="detail-card" style="background: var(--card); padding: 28px; border-radius: 16px; box-shadow: var(--shadow);">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 18px; padding-bottom: 14px; border-bottom: 1px solid var(--line);">
          <span style="font-size: 1.5rem;">📦</span>
          <h3 style="font-family: 'Playfair Display', serif; font-size: 1.35rem; color: var(--brand); margin: 0;">Order Status</h3>
        </div>
        <div style="display: grid; gap: 15px;">
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <span class="detail-label">Order Date:</span>
            <span class="detail-value"><?= date('M d, Y h:i A', strtotime((string)$order['created_at'])) ?></span>
          </div>
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <span class="detail-label">Status:</span>
            <span class="badge <?= orderStatusBadgeClass((string)$order['status']) ?>" style="font-size: 0.85rem;"><?= h($order['status']) ?></span>
          </div>
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <span class="detail-label">Payment Method:</span>
            <span class="detail-value">
              <?= h($paymentMethodObj['icon'] ?? '💳') ?> <?= h($paymentMethodObj['name'] ?? ucfirst((string)$order['payment_method'])) ?>
            </span>
          </div>
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <span class="detail-label">Payment Status:</span>
            <span class="badge <?= paymentBadgeClass((string)$order['payment_status']) ?>" style="font-size: 0.85rem;"><?= ucfirst(h($order['payment_status'])) ?></span>
          </div>
        </div>
      </div>

      <!-- Shipping Details -->
      <div class="detail-card" style="background: var(--card); padding: 28px; border-radius: 16px; box-shadow: var(--shadow);">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 18px; padding-bottom: 14px; border-bottom: 1px solid var(--line);">
          <span style="font-size: 1.5rem;">🚚</span>
          <h3 style="font-family: 'Playfair Display', serif; font-size: 1.35rem; color: var(--brand); margin: 0;">Shipping Details</h3>
        </div>
        <div style="display: grid; gap: 12px;">
          <div style="display: flex; justify-content: space-between;">
            <span class="detail-label">Receiver Name:</span>
            <span class="detail-value"><?= h($order['shipping_name']) ?></span>
          </div>
          <div style="display: flex; justify-content: space-between;">
            <span class="detail-label">Delivery Phone:</span>
            <span class="detail-value"><?= h($order['shipping_phone']) ?></span>
          </div>
          <div>
            <span class="detail-label" style="display: block; margin-bottom: 6px;">Delivery Address:</span>
            <div style="background: var(--bg-soft); padding: 12px 15px; border-radius: 10px; font-weight: 600; color: var(--brand); line-height: 1.5;"><?= nl2br(h($order['shipping_address'])) ?></div>
          </div>
          <?php if (trim((string)$order['notes']) !== ''): ?>
            <div>
              <span class="detail-label" style="display: block; margin-bottom: 6px;">Customer Notes:</span>
              <div style="background: #FFF8E1; padding: 12px 15px; border-radius: 10px; font-weight: 500; color: #795548; line-height: 1.5; border-left: 4px solid #FFB300;"><?= nl2br(h($order['notes'])) ?></div>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Payment & Transaction Details Card -->
      <div class="detail-card" style="background: linear-gradient(135deg, var(--brand) 0%, #7A150D 100%); padding: 28px; border-radius: 16px; box-shadow: var(--shadow); color: #fff;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; padding-bottom: 14px; border-bottom: 1px solid rgba(255,255,255,0.15);">
          <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 1.5rem;">💳</span>
            <h3 style="font-family: 'Playfair Display', serif; font-size: 1.35rem; color: var(--premium); margin: 0;">Payment Details</h3>
          </div>
          <span style="font-size: 1.5rem;"><?= h($paymentMethodObj['icon'] ?? '💰') ?></span>
        </div>

        <div style="display: grid; gap: 12px; font-size: 0.95rem;">
          <div style="display: flex; justify-content: space-between;">
            <span style="opacity: 0.85;">Method:</span>
            <span style="font-weight: 700; color: #fff;"><?= h($paymentMethodObj['name'] ?? ucfirst((string)$order['payment_method'])) ?></span>
          </div>

          <?php if ($paymentMethodObj && !empty($paymentMethodObj['account_title'])): ?>
            <div style="display: flex; justify-content: space-between;">
              <span style="opacity: 0.85;">Account Title:</span>
              <span style="font-weight: 700; color: var(--premium);"><?= h($paymentMethodObj['account_title']) ?></span>
            </div>
          <?php endif; ?>

          <?php if ($paymentMethodObj && !empty($paymentMethodObj['account_number'])): ?>
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <span style="opacity: 0.85;">IBAN / Number:</span>
              <span style="font-weight: 800; font-family: monospace; background: rgba(255,255,255,0.15); padding: 2px 8px; border-radius: 4px;"><?= h($paymentMethodObj['account_number']) ?></span>
            </div>
          <?php endif; ?>

          <?php if ($paymentMethodObj && !empty($paymentMethodObj['bank_name'])): ?>
            <div style="display: flex; justify-content: space-between;">
              <span style="opacity: 0.85;">Bank / Provider:</span>
              <span style="font-weight: 700;"><?= h($paymentMethodObj['bank_name']) ?></span>
            </div>
          <?php endif; ?>

          <div style="display: flex; justify-content: space-between; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.15);">
            <span style="opacity: 0.85;">Payment Status:</span>
            <span class="badge <?= paymentBadgeClass((string)$order['payment_status']) ?>"><?= ucfirst(h($order['payment_status'])) ?></span>
          </div>

          <!-- Transaction ID reference with quick edit -->
          <form method="post" action="order-details.php?id=<?= $orderId ?>" style="margin-top: 10px; background: rgba(0,0,0,0.2); padding: 12px 15px; border-radius: 10px;">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="update_transaction_id">
            <label style="display: block; font-size: 0.75rem; text-transform: uppercase; color: var(--premium); font-weight: 700; margin-bottom: 5px;">Transaction ID / Bank Reference</label>
            <div style="display: flex; gap: 8px;">
              <input type="text" name="transaction_id" value="<?= h($order['transaction_id'] ?? '') ?>" placeholder="Enter Ref # / TID" style="flex: 1; padding: 6px 10px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.3); background: #fff; color: #333; font-size: 0.85rem; font-family: monospace;">
              <button type="submit" class="btn small-btn" style="background: var(--premium); color: #333; padding: 6px 12px; font-size: 0.75rem; font-weight: 800;">Save Ref</button>
            </div>
          </form>

          <div style="display: flex; justify-content: space-between; padding-top: 15px; border-top: 2px solid var(--premium); margin-top: 5px;">
            <span style="font-size: 1.15rem; font-weight: 700;">GRAND TOTAL:</span>
            <span style="font-family: 'Playfair Display', serif; font-size: 1.9rem; color: var(--premium); font-weight: 800;"><?= h(money((float)$order['total_amount'])) ?></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Complete Order Products Table -->
    <div style="background: var(--card); padding: 28px; border-radius: 16px; box-shadow: var(--shadow);">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--line); flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; align-items: center; gap: 10px;">
          <span style="font-size: 1.5rem;">🛍️</span>
          <h3 style="font-family: 'Playfair Display', serif; font-size: 1.4rem; color: var(--brand); margin: 0;">
            Order Items (<?= count($orderItems) ?>)
          </h3>
        </div>
        <span style="font-size: 0.85rem; color: var(--muted);">All product details are permanently preserved even if deleted from catalog</span>
      </div>

      <div class="admin-table-wrap" style="box-shadow: none;">
        <table class="admin-table">
          <thead>
            <tr>
              <th style="width: 70px;">Image</th>
              <th>Product Name</th>
              <th>Catalog Status</th>
              <th>Unit Price</th>
              <th>Quantity</th>
              <th style="text-align: right;">Line Total</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orderItems as $item): ?>
              <?php
                $imgSrc = !empty($item['image_url']) ? $item['image_url'] : ($item['current_image'] ?? '');
                $isLive = !empty($item['live_product_id']);
              ?>
              <tr>
                <td>
                  <?php if (!empty($imgSrc)): ?>
                    <img src="<?= h(url($imgSrc)) ?>" alt="Product" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px; border: 1px solid var(--line);">
                  <?php else: ?>
                    <div style="width: 50px; height: 50px; background: #e5e7eb; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem;">📦</div>
                  <?php endif; ?>
                </td>

                <td>
                  <div style="font-weight: 800; color: var(--brand); font-size: 1.05rem;">
                    <?= h($item['product_name']) ?>
                  </div>
                  <div style="font-size: 0.8rem; color: var(--muted); margin-top: 3px;">
                    Order Item ID: #<?= (int)$item['id'] ?>
                    <?php if ($item['product_id']): ?>
                      • Original Product ID: #<?= (int)$item['product_id'] ?>
                    <?php endif; ?>
                  </div>
                </td>

                <td>
                  <?php if ($isLive): ?>
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                      <span class="badge paid" style="font-size: 0.72rem;">Active in Store</span>
                      <span style="font-size: 0.78rem; color: var(--muted);">Stock: <strong><?= (int)$item['current_stock'] ?></strong></span>
                      <a href="products.php?edit=<?= (int)$item['live_product_id'] ?>" target="_blank" style="font-size: 0.78rem; color: var(--brand); font-weight: 700; text-decoration: underline;">
                        Edit Product ↗
                      </a>
                    </div>
                  <?php else: ?>
                    <div style="background: #FFF1F2; border: 1px solid #FECDD3; padding: 6px 12px; border-radius: 8px; display: inline-block;">
                      <span style="color: #9F1239; font-weight: 800; font-size: 0.78rem; display: block;">
                        ⚠️ Removed from Catalog
                      </span>
                      <small style="color: #BE123C; font-size: 0.7rem;">Item record & pricing preserved in order history</small>
                    </div>
                  <?php endif; ?>
                </td>

                <td style="font-weight: 600;">
                  <?= h(money((float)$item['unit_price'])) ?>
                </td>

                <td style="font-weight: 800; font-size: 1.05rem;">
                  x <?= (int)$item['quantity'] ?>
                </td>

                <td style="font-weight: 900; color: #5D100B; font-size: 1.15rem; text-align: right;">
                  <?= h(money((float)$item['line_total'])) ?>
                </td>
              </tr>
            <?php endforeach; ?>

            <tr style="background: rgba(93, 16, 11, 0.03); border-top: 2px solid var(--brand);">
              <td colspan="5" style="font-weight: 800; font-size: 1.15rem; text-align: right; color: var(--brand);">
                GRAND TOTAL:
              </td>
              <td style="font-weight: 900; font-size: 1.45rem; color: #5D100B; text-align: right; font-family: 'Playfair Display', serif;">
                <?= h(money((float)$order['total_amount'])) ?>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<style>
.admin-layout { display: grid; grid-template-columns: 250px 1fr; min-height: calc(100vh - 90px); background: var(--bg); }
.admin-sidebar { background: var(--brand-strong); color: #fff; padding: 40px 0; }
.admin-nav { display: flex; flex-direction: column; }
.admin-nav-item { padding: 15px 30px; color: rgba(255, 255, 255, 0.7); text-decoration: none; font-weight: 600; transition: all 0.3s; border-left: 4px solid transparent; }
.admin-nav-item:hover, .admin-nav-item.active { color: var(--premium); background: rgba(255, 255, 255, 0.05); border-left-color: var(--premium); }
.admin-main { padding: 40px; }
.admin-header { margin-bottom: 30px; border-bottom: 1px solid var(--line); padding-bottom: 20px; }

.detail-label { font-weight: 700; color: var(--muted); font-size: 0.9rem; }
.detail-value { font-weight: 700; color: var(--brand); font-size: 0.95rem; }

.badge { padding: 5px 14px; border-radius: 30px; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; display: inline-block; }
.badge.placed { background: #E1D8C6; color: #5D100B; }
.badge.paid { background: #D4EDDA; color: #155724; border: 1px solid #C3E6CB; }
.badge.role-admin { background: #F8D7DA; color: #721C24; }

.btn.small-btn { padding: 8px 16px; font-size: 0.85rem; border-radius: 8px; }
.btn { display: inline-block; background: var(--brand); color: #fff; border: none; padding: 12px 28px; font-weight: 700; border-radius: 12px; cursor: pointer; text-decoration: none; transition: all 0.3s; }
.btn:hover { transform: translateY(-1px); box-shadow: 0 5px 15px rgba(93, 16, 11, 0.3); }

.input-label { display: block; font-weight: 700; color: var(--brand); margin-bottom: 8px; font-size: 0.9rem; }
.input { width: 100%; padding: 11px 15px; border: 2px solid var(--line); border-radius: 10px; font-size: 0.95rem; font-family: inherit; transition: all 0.3s; background: #fff; box-sizing: border-box; }
.input:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px rgba(93, 16, 11, 0.1); }

.admin-table-wrap { background: transparent; border-radius: 12px; overflow-x: auto; }
.admin-table { width: 100%; border-collapse: collapse; text-align: left; }
.admin-table th { background: rgba(0,0,0,0.02); padding: 15px 20px; font-size: 0.85rem; text-transform: uppercase; color: var(--muted); white-space: nowrap; }
.admin-table td { padding: 15px 20px; border-bottom: 1px solid var(--line); }
.admin-table tbody tr:hover { background: rgba(93, 16, 11, 0.02); }

@media (max-width: 920px) {
  .admin-layout { grid-template-columns: 1fr; }
  .admin-sidebar { padding: 20px; }
  .admin-nav { flex-direction: row; overflow-x: auto; padding-bottom: 10px; }
  .admin-nav-item { padding: 10px 20px; white-space: nowrap; border-left: none; border-bottom: 3px solid transparent; }
  .admin-nav-item:hover, .admin-nav-item.active { border-bottom-color: var(--premium); }
  .admin-main { padding: 20px; }
  .detail-card { min-width: 0; }
}
@media (max-width: 768px) {
  .admin-main > div:first-of-type { grid-template-columns: 1fr !important; }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
