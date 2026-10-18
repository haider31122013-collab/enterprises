<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_login();

$user = current_user($pdo);

$statsStmt = $pdo->prepare(
    'SELECT
        COUNT(*) AS total_orders,
        COALESCE(SUM(total_amount), 0) AS total_spent,
        COALESCE(SUM(CASE WHEN status = "Placed" THEN 1 ELSE 0 END), 0) AS active_orders,
        MAX(created_at) AS last_order_at
     FROM orders
     WHERE user_id = :user_id'
);
$statsStmt->execute([':user_id' => $user['id']]);
$stats = $statsStmt->fetch() ?: [
    'total_orders' => 0,
    'total_spent' => 0,
    'active_orders' => 0,
    'last_order_at' => null,
];

$recentOrdersStmt = $pdo->prepare(
    'SELECT o.id, o.status, o.total_amount, o.created_at, COUNT(oi.id) AS item_count
     FROM orders o
     LEFT JOIN order_items oi ON oi.order_id = o.id
     WHERE o.user_id = :user_id
     GROUP BY o.id
     ORDER BY o.id DESC
     LIMIT 5'
);
$recentOrdersStmt->execute([':user_id' => $user['id']]);
$recentOrders = $recentOrdersStmt->fetchAll();

$pageTitle = 'Dashboard | ' . APP_NAME;
$pageDescription = 'User dashboard for profile stats and recent orders.';

include __DIR__ . '/includes/header.php';
?>
<section class="dashboard-wrap">
  <div class="container fade">
    <p class="eyebrow">User Dashboard</p>
    <h2 class="dashboard-title">Welcome, <?= h($user['full_name']) ?></h2>
    <p class="helper-text">Track your account, orders, and purchase history from one place.</p>

    <?php if (is_admin($pdo)): ?>
      <div class="admin-notice" style="background: #E1D8C6; border: 1px solid #D2ADA1; padding: 30px; border-radius: 20px; margin-bottom: 40px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
        <div>
          <h3 style="color: #5D100B; margin-bottom: 8px; font-family: 'Playfair Display', serif; font-size: 1.5rem;">Admin Account</h3>
          <p class="helper-text" style="margin-bottom: 0;">You have full access to the management panel.</p>
        </div>
        <a href="admin/dashboard.php" class="btn">Admin Panel</a>
      </div>
    <?php endif; ?>

    <div class="dashboard-grid">
      <article class="stat-card">
        <h3>Total Orders</h3>
        <p class="stat-value"><?= h((string) $stats['total_orders']) ?></p>
      </article>
      <article class="stat-card">
        <h3>Total Spent</h3>
        <p class="stat-value"><?= h(money((float) $stats['total_spent'])) ?></p>
      </article>
      <article class="stat-card">
        <h3>Placed Orders</h3>
        <p class="stat-value"><?= h((string) $stats['active_orders']) ?></p>
      </article>
      <article class="stat-card">
        <h3>Last Order</h3>
        <p class="stat-value" style="font-size: 1.4rem; margin-top: 10px;">
          <?= $stats['last_order_at'] ? h(date('d M Y', strtotime((string) $stats['last_order_at']))) : 'None yet' ?>
        </p>
      </article>
    </div>

    <div class="dashboard-panels">
      <article class="panel">
        <h2>Recent Orders</h2>
        <?php if ($recentOrders === []): ?>
          <p class="muted" style="margin-bottom: 25px;">No orders placed yet.</p>
          <a href="products.php" class="btn">Start Shopping</a>
        <?php else: ?>
          <div class="order-list">
            <?php foreach ($recentOrders as $order): ?>
              <div class="order-card">
                <div class="order-head">
                  <div>
                    <strong style="font-size: 1.1rem; color: var(--brand);">Order #<?= h((string) $order['id']) ?></strong>
                    <p class="helper-text" style="margin-top: 4px;">Placed on <?= h(date('d M Y', strtotime((string) $order['created_at']))) ?></p>
                  </div>
                  <span class="badge placed"><?= h($order['status']) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                  <p class="helper-text">Items: <?= h((string) $order['item_count']) ?></p>
                  <p class="price" style="margin: 0; color: #5D100B;"><?= h(money((float) $order['total_amount'])) ?></p>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <a href="orders.php" class="btn btn-outline">View All Orders</a>
        <?php endif; ?>
      </article>

      <aside class="panel">
        <h2>Account</h2>
        <div style="margin-bottom: 30px;">
          <div style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--line);">
            <p class="eyebrow" style="margin-bottom: 5px; font-size: 0.6rem;">Full Name</p>
            <p style="font-weight: 700; color: var(--brand);"><?= h($user['full_name']) ?></p>
          </div>
          <div style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--line);">
            <p class="eyebrow" style="margin-bottom: 5px; font-size: 0.6rem;">Email Address</p>
            <p style="font-weight: 700; color: var(--brand);"><?= h($user['email']) ?></p>
          </div>
          <div style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--line);">
            <p class="eyebrow" style="margin-bottom: 5px; font-size: 0.6rem;">Phone Number</p>
            <p style="font-weight: 700; color: var(--brand);"><?= h($user['phone'] !== '' ? $user['phone'] : 'Not added') ?></p>
          </div>
          <div>
            <p class="eyebrow" style="margin-bottom: 5px; font-size: 0.6rem;">Shipping Address</p>
            <p style="font-weight: 700; color: var(--brand);"><?= h($user['address'] !== '' ? $user['address'] : 'Not added') ?></p>
          </div>
        </div>

        <div class="form-actions" style="flex-direction: column;">
          <a href="profile.php" class="btn">Update Profile</a>
          <a href="products.php" class="btn btn-outline">Shop More</a>
        </div>
      </aside>
    </div>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
