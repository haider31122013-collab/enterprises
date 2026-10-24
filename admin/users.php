<?php
declare(strict_types=1);
// Resources Enterprises - User Management & Deletion Engine v1.1

require_once __DIR__ . '/../includes/bootstrap.php';
require_admin($pdo);

$currentAdmin = current_user($pdo);

// Handle Delete User Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    enforce_csrf();
    $userIdToDelete = (int)($_POST['user_id'] ?? 0);

    if ($userIdToDelete <= 0) {
        set_flash('error', 'Invalid user selected.');
        redirect('users.php');
    }

    // 1. Cannot delete your own account
    if ($currentAdmin && (int)$currentAdmin['id'] === $userIdToDelete) {
        set_flash('error', 'You cannot delete your own active administrator account.');
        redirect('users.php');
    }

    // 2. Fetch the target user details
    $targetStmt = $pdo->prepare('SELECT id, full_name, email, role FROM users WHERE id = ?');
    $targetStmt->execute([$userIdToDelete]);
    $targetUser = $targetStmt->fetch();

    if (!$targetUser) {
        set_flash('error', 'User not found or already deleted.');
        redirect('users.php');
    }

    // 3. If target user is an admin, ensure not the last remaining admin
    if ($targetUser['role'] === 'admin') {
        $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        if ($adminCount <= 1) {
            set_flash('error', 'Cannot delete the only administrator account in the system.');
            redirect('users.php');
        }
    }

    // 4. Delete the user
    try {
        $delStmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $delStmt->execute([$userIdToDelete]);
        set_flash('success', "User '{$targetUser['full_name']}' ({$targetUser['email']}) was deleted successfully.");
    } catch (PDOException $e) {
        set_flash('error', 'Failed to delete user: ' . $e->getMessage());
    }

    redirect('users.php');
}

$pageTitle = 'Manage Users | Admin';

// Search and filtering
$search = trim((string)($_GET['search'] ?? ''));
$roleFilter = trim((string)($_GET['role'] ?? 'all'));

// Base query with order count
$sql = "SELECT u.*, COUNT(o.id) as order_count 
        FROM users u 
        LEFT JOIN orders o ON o.user_id = u.id 
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.id = ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = is_numeric($search) ? (int)$search : 0;
}

if ($roleFilter !== 'all') {
    $sql .= " AND u.role = ?";
    $params[] = $roleFilter;
}

$sql .= " GROUP BY u.id ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Counts for quick stats
$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalCustomers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$totalAdmins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();

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
      <div>
        <h1>Manage Users</h1>
        <p style="color: var(--muted); margin-top: 5px; font-size: 0.95rem;">
          View customer accounts, roles, orders placed, and delete inactive or spam accounts.
        </p>
      </div>
      <a href="settings.php" class="btn small-btn" style="white-space: nowrap;">Admin Settings &rarr;</a>
    </header>

    <!-- Stats Cards -->
    <div class="user-stats-grid">
      <div class="user-stat-card">
        <span class="user-stat-label">Total Accounts</span>
        <span class="user-stat-num"><?= $totalUsers ?></span>
      </div>
      <div class="user-stat-card">
        <span class="user-stat-label">Customer Users</span>
        <span class="user-stat-num" style="color: #0284c7;"><?= $totalCustomers ?></span>
      </div>
      <div class="user-stat-card">
        <span class="user-stat-label">Administrators</span>
        <span class="user-stat-num" style="color: var(--brand);"><?= $totalAdmins ?></span>
      </div>
    </div>

    <!-- Search & Filter Bar -->
    <div class="user-filter-bar">
      <form method="get" action="users.php" style="display: flex; gap: 12px; flex-wrap: wrap; width: 100%;">
        <div style="flex: 1; min-width: 240px;">
          <input type="text" name="search" class="filter-input" 
                 placeholder="Search by name, email, phone, or ID..." 
                 value="<?= h($search) ?>">
        </div>
        <div style="width: 180px;">
          <select name="role" class="filter-input" onchange="this.form.submit()">
            <option value="all" <?= $roleFilter === 'all' ? 'selected' : '' ?>>All Roles</option>
            <option value="user" <?= $roleFilter === 'user' ? 'selected' : '' ?>>Customers Only</option>
            <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admins Only</option>
          </select>
        </div>
        <button type="submit" class="btn small-btn">Filter</button>
        <?php if ($search !== '' || $roleFilter !== 'all'): ?>
          <a href="users.php" class="btn small-btn" style="background: rgba(0,0,0,0.06); color: var(--text);">Reset</a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Users Table -->
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th style="width: 60px;">ID</th>
            <th>Customer / User</th>
            <th>Contact</th>
            <th>Role</th>
            <th>Orders</th>
            <th>Joined Date</th>
            <th style="text-align: right; width: 130px;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($users)): ?>
            <tr>
              <td colspan="7" style="text-align: center; padding: 40px 20px; color: var(--muted);">
                No users found matching your search.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($users as $u): ?>
              <?php 
                $isCurrentLoggedIn = ($currentAdmin && (int)$currentAdmin['id'] === (int)$u['id']);
                $isAdminRole = ($u['role'] === 'admin');
              ?>
              <tr>
                <td style="font-weight: 800; color: var(--brand);">#<?= (int)$u['id'] ?></td>
                <td>
                  <div style="font-weight: 700; color: var(--brand); font-size: 0.95rem;">
                    <?= h($u['full_name']) ?>
                    <?php if ($isCurrentLoggedIn): ?>
                      <span class="current-user-tag">You</span>
                    <?php endif; ?>
                  </div>
                  <div style="font-size: 0.82rem; color: var(--muted); margin-top: 2px;">
                    <?= h($u['email']) ?>
                  </div>
                </td>
                <td>
                  <div style="font-size: 0.88rem; color: var(--text);">
                    <?= !empty($u['phone']) ? h($u['phone']) : '<span style="color:var(--muted); font-size:0.8rem;">No phone</span>' ?>
                  </div>
                  <?php if (!empty($u['address'])): ?>
                    <div style="font-size: 0.75rem; color: var(--muted); margin-top: 2px; max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= h($u['address']) ?>">
                      📍 <?= h($u['address']) ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="role-badge <?= $isAdminRole ? 'role-admin' : 'role-user' ?>">
                    <?= strtoupper(h($u['role'])) ?>
                  </span>
                </td>
                <td>
                  <?php if ((int)$u['order_count'] > 0): ?>
                    <a href="orders.php?search=<?= urlencode((string)$u['email']) ?>" class="order-count-badge" title="View customer orders">
                      <?= (int)$u['order_count'] ?> <?= (int)$u['order_count'] === 1 ? 'order' : 'orders' ?> &rarr;
                    </a>
                  <?php else: ?>
                    <span style="color: var(--muted); font-size: 0.82rem;">0 orders</span>
                  <?php endif; ?>
                </td>
                <td style="white-space: nowrap; font-size: 0.85rem; color: var(--muted);">
                  <?= date('M d, Y', strtotime((string)$u['created_at'])) ?>
                </td>
                <td style="text-align: right; white-space: nowrap;">
                  <?php if ($isCurrentLoggedIn): ?>
                    <span class="self-admin-badge" title="This is your active logged-in admin account">
                      Active Account
                    </span>
                  <?php else: ?>
                    <form method="post" action="users.php" style="display: inline-block;"
                          onsubmit="return confirm('⚠️ Are you sure you want to permanently delete user \'<?= addslashes(h($u['full_name'])) ?>\' (<?= addslashes(h($u['email'])) ?>)?\n\nThis will remove their account permanently.');">
                      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                      <input type="hidden" name="action" value="delete_user">
                      <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                      <button type="submit" class="delete-user-btn" title="Delete User #<?= (int)$u['id'] ?>">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                          <polyline points="3 6 5 6 21 6"></polyline>
                          <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                          <line x1="10" y1="11" x2="10" y2="17"></line>
                          <line x1="14" y1="11" x2="14" y2="17"></line>
                        </svg>
                        <span>Delete</span>
                      </button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
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
.admin-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 1px solid var(--line); padding-bottom: 20px; }

/* Stats grid */
.user-stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 16px;
  margin-bottom: 25px;
}
.user-stat-card {
  background: var(--card);
  padding: 18px 22px;
  border-radius: 10px;
  box-shadow: var(--shadow);
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.user-stat-label {
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--muted);
  font-weight: 700;
}
.user-stat-num {
  font-size: 1.8rem;
  font-weight: 800;
  color: var(--brand);
}

/* Filter bar */
.user-filter-bar {
  background: var(--card);
  padding: 16px 20px;
  border-radius: 10px;
  box-shadow: var(--shadow);
  margin-bottom: 20px;
}
.filter-input {
  width: 100%;
  padding: 10px 14px;
  border: 1px solid var(--line);
  border-radius: 6px;
  font-size: 0.9rem;
  font-family: inherit;
  color: var(--text);
  background: #fff;
  outline: none;
}
.filter-input:focus {
  border-color: var(--brand);
}

/* Table */
.admin-table-wrap { background: var(--card); border-radius: 12px; box-shadow: var(--shadow); overflow-x: auto; }
.admin-table { width: 100%; border-collapse: collapse; text-align: left; }
.admin-table th { background: rgba(0,0,0,0.02); padding: 15px 20px; font-size: 0.85rem; text-transform: uppercase; color: var(--muted); }
.admin-table td { padding: 15px 20px; border-bottom: 1px solid var(--line); vertical-align: middle; }
.admin-table tbody tr:hover { background: rgba(45, 26, 34, 0.02); }

/* Badges */
.role-badge { padding: 4px 10px; border-radius: 4px; font-size: 0.72rem; font-weight: 800; letter-spacing: 0.5px; }
.role-admin { background: var(--premium); color: #fff; }
.role-user { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

.current-user-tag {
  display: inline-block;
  background: #dbeafe;
  color: #1e40af;
  font-size: 0.7rem;
  font-weight: 800;
  padding: 2px 7px;
  border-radius: 4px;
  margin-left: 6px;
  vertical-align: middle;
}

.order-count-badge {
  display: inline-block;
  background: var(--bg-soft);
  color: var(--brand);
  padding: 4px 9px;
  border-radius: 6px;
  font-size: 0.8rem;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.2s;
}
.order-count-badge:hover {
  background: var(--brand);
  color: #fff;
}

.self-admin-badge {
  display: inline-block;
  background: #f1f5f9;
  color: #64748b;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 6px 12px;
  border-radius: 6px;
  border: 1px solid #e2e8f0;
}

/* Delete button */
.delete-user-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background: #fee2e2;
  color: #b91c1c;
  border: 1px solid #fecaca;
  border-radius: 6px;
  font-size: 0.82rem;
  font-weight: 700;
  font-family: inherit;
  cursor: pointer;
  transition: all 0.2s;
}
.delete-user-btn:hover {
  background: #dc2626;
  color: #fff;
  border-color: #dc2626;
}
.delete-user-btn svg {
  flex-shrink: 0;
}

@media (max-width: 900px) {
  .admin-layout { grid-template-columns: 1fr; }
  .admin-sidebar { padding: 15px 0; }
  .admin-nav { flex-direction: row; overflow-x: auto; }
  .admin-nav-item { padding: 10px 15px; white-space: nowrap; border-left: none; border-bottom: 3px solid transparent; }
  .admin-nav-item:hover, .admin-nav-item.active { border-bottom-color: var(--premium); }
  .admin-main { padding: 20px; }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
