<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin($pdo);

$pageTitle = 'Manage Users';

$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="admin-layout">
  <aside class="admin-sidebar">
    <nav class="admin-nav">
      <a href="dashboard.php" class="admin-nav-item">Dashboard</a>
      <a href="orders.php" class="admin-nav-item">Manage Orders</a>
      <a href="products.php" class="admin-nav-item">Manage Products</a>
      <a href="payment-methods.php" class="admin-nav-item">Payment Methods</a>
      <a href="currencies.php" class="admin-nav-item">Currencies</a>
      <a href="users.php" class="admin-nav-item active">Manage Users</a>
      <a href="reviews.php" class="admin-nav-item">Manage Reviews</a>
      <a href="collections.php" class="admin-nav-item">Collections</a>
      <a href="messages.php" class="admin-nav-item">Messages</a>
      <a href="settings.php" class="admin-nav-item">Settings</a>
    </nav>
  </aside>

  <main class="admin-main">
    <header class="admin-header">
      <h1>Manage Users</h1>
    </header>

    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Joined Date</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td><?= $u['id'] ?></td>
              <td><?= h($u['full_name']) ?></td>
              <td><?= h($u['email']) ?></td>
              <td>
                <span class="role-badge <?= $u['role'] === 'admin' ? 'role-admin' : '' ?>">
                  <?= strtoupper($u['role']) ?>
                </span>
              </td>
              <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>

<style>
/* Reusing layout from products */
.admin-layout { display: grid; grid-template-columns: 250px 1fr; min-height: calc(100vh - 90px); background: var(--bg); }
.admin-sidebar { background: var(--brand-strong); color: #fff; padding: 40px 0; }
.admin-nav { display: flex; flex-direction: column; }
.admin-nav-item { padding: 15px 30px; color: rgba(255, 255, 255, 0.7); text-decoration: none; font-weight: 600; transition: all 0.3s; border-left: 4px solid transparent; }
.admin-nav-item:hover, .admin-nav-item.active { color: var(--premium); background: rgba(255, 255, 255, 0.05); border-left-color: var(--premium); }
.admin-main { padding: 40px; }
.admin-header { margin-bottom: 40px; border-bottom: 1px solid var(--line); padding-bottom: 20px; }

.admin-table-wrap { background: var(--card); border-radius: 12px; box-shadow: var(--shadow); overflow-x: auto; }
.admin-table { width: 100%; border-collapse: collapse; text-align: left; }
.admin-table th { background: rgba(0,0,0,0.02); padding: 15px 20px; font-size: 0.85rem; text-transform: uppercase; color: var(--muted); }
.admin-table td { padding: 15px 20px; border-bottom: 1px solid var(--line); }

.role-badge { padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 800; background: #eee; }
.role-admin { background: var(--premium); color: #fff; }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>