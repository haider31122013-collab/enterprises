<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin($pdo);

$pageTitle = 'Manage Reviews';

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM reviews WHERE id = ?")->execute([$id]);
    set_flash('success', 'Review deleted successfully.');
    redirect('reviews.php');
}

$reviews = $pdo->query("SELECT r.*, u.full_name, p.name as product_name FROM reviews r JOIN users u ON r.user_id = u.id JOIN products p ON r.product_id = p.id ORDER BY r.created_at DESC")->fetchAll();

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
      <a href="users.php" class="admin-nav-item">Manage Users</a>
      <a href="reviews.php" class="admin-nav-item active">Manage Reviews</a>
      <a href="collections.php" class="admin-nav-item">Collections</a>
      <a href="messages.php" class="admin-nav-item">Messages</a>
      <a href="settings.php" class="admin-nav-item">Settings</a>
    </nav>
  </aside>

  <main class="admin-main">
    <header class="admin-header">
      <h1>Manage Reviews</h1>
    </header>

    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Product</th>
            <th>User</th>
            <th>Rating</th>
            <th>Comment</th>
            <th>Date</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($reviews as $r): ?>
            <tr>
              <td><?= $r['id'] ?></td>
              <td><?= h($r['product_name']) ?></td>
              <td><?= h($r['full_name']) ?></td>
              <td>⭐ <?= $r['rating'] ?>/5</td>
              <td><small><?= h($r['comment']) ?></small></td>
              <td><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
              <td>
                <a href="?delete=<?= $r['id'] ?>" class="icon-btn delete-btn" onclick="return confirm('Are you sure?')" title="Delete">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>

<style>
/* Reusing layout from users */
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

.icon-btn { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 8px; transition: all 0.2s; }
.delete-btn { background: #fee2e2; color: #b91c1c; }
.delete-btn:hover { background: #fecaca; }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>