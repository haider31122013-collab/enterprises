<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin($pdo);

// Enforce single admin policy: automatically remove any extra admin accounts
$currentAdminId = current_user_id();
if ($currentAdminId !== null) {
    $pdo->prepare("DELETE FROM users WHERE role = 'admin' AND id != ?")->execute([$currentAdminId]);
}

$pageTitle = 'Admin Dashboard';

// Fetch stats
$productCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$orderCount = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$reviewCount = $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn();
$paymentMethodCount = $pdo->query("SELECT COUNT(*) FROM payment_methods WHERE active = 1")->fetchColumn();

// Fetch recent orders with item count
$recentOrders = $pdo->query("SELECT o.id, o.shipping_name, o.total_amount, o.status, o.payment_method, o.payment_status, o.created_at, COUNT(oi.id) as item_count 
    FROM orders o 
    LEFT JOIN order_items oi ON oi.order_id = o.id 
    GROUP BY o.id 
    ORDER BY o.id DESC LIMIT 5")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="admin-layout">
  <aside class="admin-sidebar">
    <nav class="admin-nav">
      <a href="dashboard.php" class="admin-nav-item active">Dashboard</a>
      <a href="orders.php" class="admin-nav-item">Manage Orders</a>
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
    <header class="admin-header" style="display: flex; justify-content: space-between; align-items: center;">
      <h1>Admin Dashboard</h1>
      <a href="settings.php" class="btn small-btn" style="display: inline-flex; align-items: center; gap: 6px;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
        Settings
      </a>
    </header>

    <div class="admin-stats-grid">
      <div class="stat-card">
        <h3>Products</h3>
        <p class="stat-number"><?= $productCount ?></p>
        <a href="products.php" class="btn small-btn">Manage</a>
      </div>
      <div class="stat-card">
        <h3>Users</h3>
        <p class="stat-number"><?= $userCount ?></p>
        <a href="users.php" class="btn small-btn">Manage</a>
      </div>
      <div class="stat-card">
        <h3>Orders</h3>
        <p class="stat-number"><?= $orderCount ?></p>
        <a href="orders.php" class="btn small-btn">View All</a>
      </div>
      <div class="stat-card">
        <h3>Payment Methods</h3>
        <p class="stat-number"><?= $paymentMethodCount ?></p>
        <a href="payment-methods.php" class="btn small-btn">Configure</a>
      </div>
      <div class="stat-card">
        <h3>Active Currency</h3>
        <p class="stat-number" style="font-size: 2rem;"><?= h(active_currency_symbol()) ?> (<?= h(active_currency_code()) ?>)</p>
        <a href="currencies.php" class="btn small-btn">Change</a>
      </div>
      <div class="stat-card">
        <h3>Reviews</h3>
        <p class="stat-number"><?= $reviewCount ?></p>
        <a href="reviews.php" class="btn small-btn">Manage</a>
      </div>
    </div>

    <div style="margin-top: 40px; background: var(--card); padding: 25px; border-radius: 12px; box-shadow: var(--shadow);">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="font-family: 'Playfair Display', serif; font-size: 1.4rem; color: var(--brand); margin: 0;">Recent Orders</h2>
        <a href="orders.php" class="btn small-btn">View All Orders</a>
      </div>
      <?php if (empty($recentOrders)): ?>
        <p style="color: var(--muted); text-align: center; padding: 20px 0;">No orders yet.</p>
      <?php else: ?>
        <table class="admin-table">
          <thead>
            <tr>
              <th>Order ID</th>
              <th>Customer</th>
              <th>Items</th>
              <th>Total</th>
              <th>Payment</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentOrders as $ro): ?>
              <tr>
                <td style="font-weight: 800; color: var(--brand);">#<?= (int)$ro['id'] ?></td>
                <td><?= h($ro['shipping_name']) ?></td>
                <td><span class="badge" style="background: var(--bg-soft); color: var(--brand);"><?= (int)$ro['item_count'] ?> items</span></td>
                <td style="font-weight: 700; color: #5D100B;"><?= h(money((float)$ro['total_amount'])) ?></td>
                <td><span class="badge paid"><?= ucfirst(h($ro['payment_method'])) ?></span></td>
                <td><span class="badge placed"><?= h($ro['status']) ?></span></td>
                <td><a href="order-details.php?id=<?= (int)$ro['id'] ?>" class="btn small-btn" style="padding: 4px 10px; font-size: 0.75rem;">Details</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

  </main>
</div>

<style>
.admin-layout {
  display: grid;
  grid-template-columns: 250px 1fr;
  min-height: calc(100vh - 90px);
  background: var(--bg);
}

.admin-sidebar {
  background: var(--brand-strong);
  color: #fff;
  padding: 40px 0;
}

.admin-nav {
  display: flex;
  flex-direction: column;
}

.admin-nav-item {
  padding: 15px 30px;
  color: rgba(255, 255, 255, 0.7);
  text-decoration: none;
  font-weight: 600;
  transition: all 0.3s;
  border-left: 4px solid transparent;
}

.admin-nav-item:hover, .admin-nav-item.active {
  color: var(--premium);
  background: rgba(255, 255, 255, 0.05);
  border-left-color: var(--premium);
}

.admin-main {
  padding: 40px;
}

.admin-header {
  margin-bottom: 40px;
  border-bottom: 1px solid var(--line);
  padding-bottom: 20px;
}

.admin-stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 30px;
}

.stat-card {
  background: var(--card);
  padding: 30px;
  border-radius: 12px;
  box-shadow: var(--shadow);
  text-align: center;
}

.stat-card h3 {
  font-size: 0.9rem;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: var(--muted);
  margin-bottom: 15px;
}

.stat-number {
  font-size: 3rem;
  font-weight: 800;
  color: var(--brand);
  margin-bottom: 20px;
}

@media (max-width: 920px) {
  .admin-layout {
    grid-template-columns: 1fr;
  }
  .admin-sidebar {
    padding: 20px;
  }
  .admin-nav {
    flex-direction: row;
    overflow-x: auto;
    padding-bottom: 10px;
  }
  .admin-nav-item {
    padding: 10px 20px;
    white-space: nowrap;
    border-left: none;
    border-bottom: 3px solid transparent;
  }
  .admin-nav-item:hover, .admin-nav-item.active {
    border-bottom-color: var(--premium);
  }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>