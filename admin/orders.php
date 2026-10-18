<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin($pdo);

$pageTitle = 'Manage Orders';

// Fetch all payment methods for mapping and filter
$pmList = $pdo->query('SELECT * FROM payment_methods ORDER BY sort_order ASC, id ASC')->fetchAll();
$paymentMethodsMap = [];
foreach ($pmList as $pm) {
    $paymentMethodsMap[$pm['code']] = $pm;
}

$statusFilter = trim((string)($_GET['status'] ?? 'all'));
$paymentFilter = trim((string)($_GET['payment'] ?? 'all'));
$search = trim((string)($_GET['search'] ?? ''));

$validStatuses = ['all', 'Placed', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
$validPayments = array_merge(['all'], array_column($pmList, 'code'), ['cod', 'jazzcash', 'bank_transfer']);

if (!in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = 'all';
}
if (!in_array($paymentFilter, $validPayments, true)) {
    $paymentFilter = 'all';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();
    $action = $_POST['action'] ?? '';
    $orderId = (int)($_POST['order_id'] ?? 0);

    if ($orderId > 0) {
        if ($action === 'update_status') {
            $newStatus = trim((string)($_POST['new_status'] ?? 'Placed'));
            if (in_array($newStatus, ['Placed', 'Processing', 'Shipped', 'Delivered', 'Cancelled'], true)) {
                $stmt = $pdo->prepare('UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute([$newStatus, $orderId]);
                set_flash('success', 'Order #' . $orderId . ' status updated to: ' . $newStatus);
            }
        } elseif ($action === 'update_payment') {
            $newPaymentStatus = trim((string)($_POST['new_payment_status'] ?? 'pending'));
            if (in_array($newPaymentStatus, ['pending', 'paid', 'failed', 'refunded'], true)) {
                $stmt = $pdo->prepare('UPDATE orders SET payment_status = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute([$newPaymentStatus, $orderId]);
                set_flash('success', 'Order #' . $orderId . ' payment status updated.');
            }
        }
    }
    redirect('orders.php');
}

$sql = 'SELECT o.id, o.status, o.total_amount, o.payment_method, o.payment_status, o.transaction_id, o.shipping_name, o.shipping_phone, o.shipping_address, o.created_at, u.full_name AS customer_name, u.email AS customer_email
        FROM orders o
        LEFT JOIN users u ON u.id = o.user_id
        WHERE 1=1';
$params = [];

if ($statusFilter !== 'all') {
    $sql .= ' AND o.status = ?';
    $params[] = $statusFilter;
}
if ($paymentFilter !== 'all') {
    $sql .= ' AND o.payment_method = ?';
    $params[] = $paymentFilter;
}
if ($search !== '') {
    $sql .= ' AND (o.id = ? OR o.shipping_name LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR o.transaction_id LIKE ? OR EXISTS (SELECT 1 FROM order_items oi_sub WHERE oi_sub.order_id = o.id AND oi_sub.product_name LIKE ?))';
    $params[] = (int)$search;
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$sql .= ' ORDER BY o.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Load all items with product catalog status for displayed orders
$orderItemsByOrderId = [];
if (!empty($orders)) {
    $orderIds = array_column($orders, 'id');
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $itemStmt = $pdo->prepare(
        "SELECT oi.*, p.id AS live_product_id, p.active AS live_product_active 
         FROM order_items oi 
         LEFT JOIN products p ON p.id = oi.product_id 
         WHERE oi.order_id IN ($placeholders) 
         ORDER BY oi.id ASC"
    );
    $itemStmt->execute($orderIds);
    foreach ($itemStmt->fetchAll() as $row) {
        $orderItemsByOrderId[(int)$row['order_id']][] = $row;
    }
}

$statsSql = 'SELECT status, COUNT(*) as cnt FROM orders GROUP BY status';
$statusStats = $pdo->query($statsSql)->fetchAll(PDO::FETCH_KEY_PAIR);
$totalOrders = array_sum($statusStats);
$revenueStmt = $pdo->query('SELECT SUM(total_amount) FROM orders');
$totalRevenue = (float)$revenueStmt->fetchColumn();

include __DIR__ . '/../includes/header.php';

function paymentMethodDisplay(string $method, array $map): array {
    if (isset($map[$method])) {
        return [
            'name' => $map[$method]['name'],
            'icon' => $map[$method]['icon'] ?: '💳'
        ];
    }
    return match ($method) {
        'bank_transfer' => ['name' => 'Bank Transfer', 'icon' => '🏦'],
        'jazzcash' => ['name' => 'JazzCash / Raast', 'icon' => '📱'],
        'cod' => ['name' => 'Cash on Delivery', 'icon' => '💵'],
        default => ['name' => ucfirst($method), 'icon' => '💳'],
    };
}

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
    <header class="admin-header">
      <h1>Manage Orders</h1>
      <p style="color: var(--muted); margin-top: 5px; font-size: 0.95rem;">Review orders, products purchased, payment verification, and delivery statuses.</p>
    </header>

    <div class="admin-stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); margin-bottom: 30px;">
      <div class="stat-card">
        <h3>Total Orders</h3>
        <p class="stat-number"><?= $totalOrders ?></p>
      </div>
      <div class="stat-card">
        <h3>Revenue</h3>
        <p class="stat-number" style="font-size: 2rem;"><?= h(money($totalRevenue)) ?></p>
      </div>
      <div class="stat-card">
        <h3>Placed</h3>
        <p class="stat-number"><?= $statusStats['Placed'] ?? 0 ?></p>
      </div>
      <div class="stat-card">
        <h3>Processing</h3>
        <p class="stat-number"><?= $statusStats['Processing'] ?? 0 ?></p>
      </div>
      <div class="stat-card">
        <h3>Delivered</h3>
        <p class="stat-number"><?= $statusStats['Delivered'] ?? 0 ?></p>
      </div>
    </div>

    <?php
      $flashSuccess = get_flash('success');
      $flashError = get_flash('error');
    ?>
    <?php if ($flashSuccess): ?>
      <div class="flash success" style="margin-bottom: 20px; padding: 15px 20px; background: #D4EDDA; color: #155724; border-radius: 8px; font-weight: 600;"><?= h($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
      <div class="flash error" style="margin-bottom: 20px; padding: 15px 20px; background: #F8D7DA; color: #721C24; border-radius: 8px; font-weight: 600;"><?= h($flashError) ?></div>
    <?php endif; ?>

    <div class="admin-table-wrap" style="padding: 20px; margin-bottom: 25px; background: var(--card); border-radius: 12px; box-shadow: var(--shadow);">
      <form method="get" action="orders.php" style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 15px; align-items: end;">
        <div>
          <label class="input-label" style="margin-bottom: 8px; display: block;">Search Orders</label>
          <input class="input" type="text" name="search" value="<?= h($search) ?>" placeholder="Search Order ID, Customer, Phone, Product Name, Ref TID...">
        </div>
        <div>
          <label class="input-label" style="margin-bottom: 8px; display: block;">Order Status</label>
          <select class="input" name="status">
            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
            <option value="Placed" <?= $statusFilter === 'Placed' ? 'selected' : '' ?>>Placed</option>
            <option value="Processing" <?= $statusFilter === 'Processing' ? 'selected' : '' ?>>Processing</option>
            <option value="Shipped" <?= $statusFilter === 'Shipped' ? 'selected' : '' ?>>Shipped</option>
            <option value="Delivered" <?= $statusFilter === 'Delivered' ? 'selected' : '' ?>>Delivered</option>
            <option value="Cancelled" <?= $statusFilter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
          </select>
        </div>
        <div>
          <label class="input-label" style="margin-bottom: 8px; display: block;">Payment Method</label>
          <select class="input" name="payment">
            <option value="all" <?= $paymentFilter === 'all' ? 'selected' : '' ?>>All Payment Methods</option>
            <?php foreach ($pmList as $pm): ?>
              <option value="<?= h($pm['code']) ?>" <?= $paymentFilter === $pm['code'] ? 'selected' : '' ?>>
                <?= h($pm['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="display: flex; gap: 10px;">
          <button type="submit" class="btn small-btn">Filter</button>
          <a href="orders.php" class="btn small-btn" style="background: var(--muted); color: #fff;">Reset</a>
        </div>
      </form>
    </div>

    <?php if (empty($orders)): ?>
      <div class="empty-state" style="background: var(--card); border-radius: 12px; padding: 60px 30px; text-align: center; box-shadow: var(--shadow);">
        <div style="font-size: 4rem; margin-bottom: 20px; opacity: 0.3;">📦</div>
        <h3 style="font-family: 'Playfair Display', serif; font-size: 1.8rem; margin-bottom: 10px;">No Orders Found</h3>
        <p class="muted">Try adjusting your search or filters.</p>
      </div>
    <?php else: ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Order ID</th>
              <th>Date</th>
              <th>Customer</th>
              <th>Products Ordered</th>
              <th>Total</th>
              <th>Payment Info</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orders as $order): ?>
              <?php
                $orderId = (int)$order['id'];
                $orderItems = $orderItemsByOrderId[$orderId] ?? [];
                $pmMeta = paymentMethodDisplay((string)$order['payment_method'], $paymentMethodsMap);
              ?>
              <tr style="vertical-align: top;">
                <td style="font-weight: 800; color: var(--brand); font-size: 1.05rem;">
                  #<?= $orderId ?>
                </td>
                <td style="white-space: nowrap;">
                  <?= date('M d, Y', strtotime((string)$order['created_at'])) ?><br>
                  <span style="font-size: 0.75rem; color: var(--muted);"><?= date('h:i A', strtotime((string)$order['created_at'])) ?></span>
                </td>
                <td>
                  <div style="font-weight: 700; margin-bottom: 3px; color: var(--brand);"><?= h($order['shipping_name'] ?: ($order['customer_name'] ?? 'N/A')) ?></div>
                  <div style="font-size: 0.8rem; color: var(--muted);"><?= h($order['customer_email'] ?? '') ?></div>
                  <div style="font-size: 0.8rem; color: var(--muted);"><?= h($order['shipping_phone']) ?></div>
                </td>
                
                <!-- Complete Products Ordered List -->
                <td style="min-width: 250px;">
                  <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 8px;">
                    <span class="badge" style="background: var(--bg-soft); color: var(--brand); font-size: 0.72rem;">
                      <?= count($orderItems) ?> <?= count($orderItems) === 1 ? 'item' : 'items' ?>
                    </span>
                  </div>
                  <div style="display: flex; flex-direction: column; gap: 8px;">
                    <?php foreach ($orderItems as $item): ?>
                      <div style="display: flex; align-items: center; gap: 10px; padding: 6px 8px; background: #FAFAFA; border: 1px solid var(--line); border-radius: 8px;">
                        <?php if (!empty($item['image_url'])): ?>
                          <img src="<?= h(url($item['image_url'])) ?>" alt="Product" style="width: 36px; height: 36px; object-fit: cover; border-radius: 6px; border: 1px solid #e5e7eb;">
                        <?php else: ?>
                          <div style="width: 36px; height: 36px; background: #e5e7eb; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 1rem;">📦</div>
                        <?php endif; ?>
                        
                        <div style="flex: 1; font-size: 0.85rem; line-height: 1.3;">
                          <div style="font-weight: 700; color: var(--brand);">
                            <?= h($item['product_name']) ?>
                            <span style="color: #5D100B; font-weight: 800;">x<?= (int)$item['quantity'] ?></span>
                          </div>
                          <div style="display: flex; gap: 6px; align-items: center; margin-top: 2px;">
                            <span style="color: var(--muted); font-size: 0.75rem;"><?= h(money((float)$item['unit_price'])) ?> each</span>
                            <?php if (empty($item['live_product_id'])): ?>
                              <span style="background: #FEE2E2; color: #991B1B; font-size: 0.68rem; font-weight: 700; padding: 1px 6px; border-radius: 4px;" title="This product was removed from catalog, but order snapshot is preserved">⚠️ Deleted Product</span>
                            <?php else: ?>
                              <span style="background: #ECFDF5; color: #065F46; font-size: 0.68rem; font-weight: 600; padding: 1px 6px; border-radius: 4px;">Active</span>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </td>

                <td style="font-weight: 800; color: #5D100B; font-size: 1.15rem; white-space: nowrap;">
                  <?= h(money((float)$order['total_amount'])) ?>
                </td>

                <!-- Payment Info -->
                <td>
                  <div style="display: flex; align-items: center; gap: 6px; font-size: 0.9rem; font-weight: 700; margin-bottom: 6px; color: var(--brand);">
                    <span><?= h($pmMeta['icon']) ?></span>
                    <span><?= h($pmMeta['name']) ?></span>
                  </div>
                  <div style="margin-bottom: 6px;">
                    <span class="badge <?= paymentBadgeClass((string)$order['payment_status']) ?>">
                      <?= ucfirst(h($order['payment_status'])) ?>
                    </span>
                  </div>
                  <?php if (!empty($order['transaction_id'])): ?>
                    <div style="font-size: 0.75rem; font-family: monospace; background: #FFF9E6; border: 1px solid #FFE082; padding: 2px 6px; border-radius: 4px; display: inline-block; color: #795548;" title="Transaction Reference">
                      Ref: <?= h($order['transaction_id']) ?>
                    </div>
                  <?php endif; ?>
                </td>

                <!-- Status Update Forms -->
                <td>
                  <form method="post" action="orders.php" style="display: flex; flex-direction: column; gap: 6px;">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="order_id" value="<?= $orderId ?>">
                    <input type="hidden" name="action" value="update_status">
                    <span class="badge <?= orderStatusBadgeClass((string)$order['status']) ?>" style="align-self: flex-start;"><?= h($order['status']) ?></span>
                    <div style="display: flex; gap: 4px;">
                      <select name="new_status" style="padding: 4px 6px; font-size: 0.75rem; border: 1px solid var(--line); border-radius: 6px; background: #fff;">
                        <?php foreach (['Placed', 'Processing', 'Shipped', 'Delivered', 'Cancelled'] as $st): ?>
                          <option value="<?= h($st) ?>" <?= $order['status'] === $st ? 'selected' : '' ?>><?= h($st) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <button type="submit" class="btn small-btn" style="padding: 4px 8px; font-size: 0.7rem;">Save</button>
                    </div>
                  </form>

                  <form method="post" action="orders.php" style="margin-top: 8px; display: flex; gap: 4px;">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="order_id" value="<?= $orderId ?>">
                    <input type="hidden" name="action" value="update_payment">
                    <select name="new_payment_status" style="padding: 4px 6px; font-size: 0.75rem; border: 1px solid var(--line); border-radius: 6px; background: #fff;">
                      <option value="pending" <?= $order['payment_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                      <option value="paid" <?= $order['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                      <option value="failed" <?= $order['payment_status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
                      <option value="refunded" <?= $order['payment_status'] === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                    </select>
                    <button type="submit" class="btn small-btn" style="padding: 4px 8px; font-size: 0.7rem;">Pay</button>
                  </form>
                </td>

                <td style="white-space: nowrap;">
                  <a href="order-details.php?id=<?= $orderId ?>" class="btn small-btn" style="display: inline-block; padding: 6px 14px; text-decoration: none;">View Details</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
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

.admin-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
.stat-card { background: var(--card); padding: 25px; border-radius: 12px; box-shadow: var(--shadow); text-align: center; }
.stat-card h3 { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted); margin-bottom: 12px; }
.stat-number { font-size: 2.5rem; font-weight: 800; color: var(--brand); }

.admin-table-wrap { background: var(--card); border-radius: 12px; box-shadow: var(--shadow); overflow-x: auto; }
.admin-table { width: 100%; border-collapse: collapse; text-align: left; }
.admin-table th { background: rgba(0,0,0,0.02); padding: 15px 20px; font-size: 0.85rem; text-transform: uppercase; color: var(--muted); white-space: nowrap; }
.admin-table td { padding: 15px 20px; border-bottom: 1px solid var(--line); }
.admin-table tbody tr:hover { background: rgba(93, 16, 11, 0.02); }

.badge { padding: 5px 12px; border-radius: 30px; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; display: inline-block; }
.badge.placed { background: #E1D8C6; color: #5D100B; }
.badge.paid { background: #D4EDDA; color: #155724; border: 1px solid #C3E6CB; }
.badge.role-admin { background: #F8D7DA; color: #721C24; }

.btn.small-btn { padding: 8px 16px; font-size: 0.85rem; border-radius: 8px; }
.btn { display: inline-block; background: var(--brand); color: #fff; border: none; padding: 12px 28px; font-weight: 700; border-radius: 12px; cursor: pointer; text-decoration: none; transition: all 0.3s; }
.btn:hover { transform: translateY(-1px); box-shadow: 0 5px 15px rgba(93, 16, 11, 0.3); }

.input-label { display: block; font-weight: 700; color: var(--brand); margin-bottom: 8px; font-size: 0.9rem; }
.input { width: 100%; padding: 11px 15px; border: 2px solid var(--line); border-radius: 10px; font-size: 0.95rem; font-family: inherit; transition: all 0.3s; background: #fff; box-sizing: border-box; }
.input:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px rgba(93, 16, 11, 0.1); }

@media (max-width: 920px) {
  .admin-layout { grid-template-columns: 1fr; }
  .admin-sidebar { padding: 20px; }
  .admin-nav { flex-direction: row; overflow-x: auto; padding-bottom: 10px; }
  .admin-nav-item { padding: 10px 20px; white-space: nowrap; border-left: none; border-bottom: 3px solid transparent; }
  .admin-nav-item:hover, .admin-nav-item.active { border-bottom-color: var(--premium); }
  .admin-main { padding: 20px; }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
