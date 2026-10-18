<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin($pdo);

$pageTitle = 'Contact Messages';

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare('DELETE FROM contact_messages WHERE id = ?')->execute([$id]);
    set_flash('success', 'Message deleted.');
    redirect('messages.php');
}

if (isset($_GET['read']) && is_numeric($_GET['read'])) {
    $id = (int)$_GET['read'];
    $pdo->prepare('UPDATE contact_messages SET is_read = 1 WHERE id = ?')->execute([$id]);
    redirect('messages.php');
}

$messages = $pdo->query('SELECT * FROM contact_messages ORDER BY created_at DESC')->fetchAll();
$unreadCount = (int) $pdo->query('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0')->fetchColumn();

include __DIR__ . '/../includes/header.php';

$flashSuccess = get_flash('success');
$flashError = get_flash('error');
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
      <a href="reviews.php" class="admin-nav-item">Manage Reviews</a>
      <a href="collections.php" class="admin-nav-item">Collections</a>
      <a href="messages.php" class="admin-nav-item active">Messages (<?= $unreadCount ?>)</a>
      <a href="settings.php" class="admin-nav-item">Settings</a>
    </nav>
  </aside>

  <main class="admin-main">
    <header class="admin-header">
      <h1>Contact Messages</h1>
      <p class="helper-text" style="margin-top: 8px;">Inquiries submitted from the contact page (<?= count($messages) ?> total, <?= $unreadCount ?> unread).</p>
    </header>

    <?php if ($flashSuccess): ?>
      <div class="flash success" style="margin-bottom: 20px;"><?= h($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
      <div class="flash error" style="margin-bottom: 20px;"><?= h($flashError) ?></div>
    <?php endif; ?>

    <?php if ($messages === []): ?>
      <article class="empty-state">
        <p class="muted">No contact messages yet.</p>
      </article>
    <?php else: ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>From</th>
              <th>Topic</th>
              <th>Message</th>
              <th>Received</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($messages as $m): ?>
              <tr style="<?= (int) $m['is_read'] === 0 ? 'background: rgba(255, 179, 0, 0.06);' : '' ?>">
                <td><?= (int) $m['id'] ?><?= (int) $m['is_read'] === 0 ? ' <span style="color:#b7791f;font-weight:700;">NEW</span>' : '' ?></td>
                <td>
                  <strong><?= h($m['name']) ?></strong><br>
                  <small><?= h($m['email']) ?></small>
                  <?php if ((int) ($m['user_id'] ?? 0) > 0): ?>
                    <br><small>Account #<?= (int) $m['user_id'] ?></small>
                  <?php endif; ?>
                </td>
                <td><small><?= h($m['topic']) ?></small></td>
                <td style="max-width: 380px; white-space: pre-wrap;"><?= h($m['message']) ?></td>
                <td><?= h(date('M d, Y h:i A', strtotime((string) $m['created_at']))) ?></td>
                <td style="white-space: nowrap;">
                  <?php if ((int) $m['is_read'] === 0): ?>
                    <a href="?read=<?= (int) $m['id'] ?>" class="icon-btn" title="Mark as read" style="background:#e6f4ea;color:#137333;">✓</a>
                  <?php endif; ?>
                  <a href="?delete=<?= (int) $m['id'] ?>" class="icon-btn delete-btn" title="Delete" onclick="return confirm('Delete this message?')">🗑</a>
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
.admin-header { margin-bottom: 40px; border-bottom: 1px solid var(--line); padding-bottom: 20px; }

.admin-table-wrap { background: var(--card); border-radius: 12px; box-shadow: var(--shadow); overflow-x: auto; }
.admin-table { width: 100%; border-collapse: collapse; text-align: left; }
.admin-table th { background: rgba(0,0,0,0.02); padding: 15px 20px; font-size: 0.85rem; text-transform: uppercase; color: var(--muted); }
.admin-table td { padding: 15px 20px; border-bottom: 1px solid var(--line); vertical-align: top; }

.icon-btn { display: inline-flex; align-items: center; justify-content: center; min-width: 34px; height: 34px; border-radius: 8px; transition: all 0.2s; margin-right: 4px; text-decoration: none; }
.delete-btn { background: #fee2e2; color: #b91c1c; }
.delete-btn:hover { background: #fecaca; }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
