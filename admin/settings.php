<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_admin($pdo);

$user = current_user($pdo);
if (!$user) {
    redirect(url('login.php'));
}

$pageTitle = 'Admin Settings | ' . APP_NAME;

$errors = [];
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();
    $action = (string)($_POST['action'] ?? 'update_credentials');

    if ($action === 'update_credentials') {
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        // Basic full name validation
        if ($fullName === '') {
            $errors[] = 'Admin full name cannot be empty.';
        }

        // Email validation
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please provide a valid admin login email address.';
        }

        // Check if email changed
        $emailChanged = ($email !== strtolower((string)$user['email']));
        if ($emailChanged) {
            $emailCheckStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1');
            $emailCheckStmt->execute([
                ':email' => $email,
                ':id' => $user['id']
            ]);
            if ($emailCheckStmt->fetch()) {
                $errors[] = "The email '{$email}' is already in use by another user account.";
            }

            // Require current password when changing sensitive email credential
            if ($currentPassword === '') {
                $errors[] = 'Current password is required to change your admin login email.';
            }
        }

        // Password change validation
        $passwordChanged = false;
        $newPasswordHash = '';

        if ($newPassword !== '' || $confirmPassword !== '') {
            if ($currentPassword === '') {
                $errors[] = 'Current password is required to set a new password.';
            }

            if (strlen($newPassword) < 6) {
                $errors[] = 'New password must be at least 6 characters long.';
            }

            if ($newPassword !== $confirmPassword) {
                $errors[] = 'New password and confirmation password do not match.';
            }

            $passwordChanged = true;
        }

        // Verify current password if provided or required
        if ($currentPassword !== '') {
            if (!password_verify($currentPassword, (string)$user['password_hash'])) {
                $errors[] = 'Current password entered is incorrect.';
            } else {
                if ($passwordChanged) {
                    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                }
            }
        }

        // If no errors, update user record in database
        if ($errors === []) {
            if ($passwordChanged && $newPasswordHash !== '') {
                $updateStmt = $pdo->prepare(
                    'UPDATE users 
                     SET full_name = :full_name, 
                         email = :email, 
                         phone = :phone, 
                         address = :address, 
                         password_hash = :password_hash, 
                         updated_at = NOW() 
                     WHERE id = :id'
                );
                $updateStmt->execute([
                    ':full_name' => $fullName,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':address' => $address,
                    ':password_hash' => $newPasswordHash,
                    ':id' => $user['id'],
                ]);
                set_flash('success', 'Admin credentials (including new password) updated successfully! Please note your new credentials.');
            } else {
                $updateStmt = $pdo->prepare(
                    'UPDATE users 
                     SET full_name = :full_name, 
                         email = :email, 
                         phone = :phone, 
                         address = :address, 
                         updated_at = NOW() 
                     WHERE id = :id'
                );
                $updateStmt->execute([
                    ':full_name' => $fullName,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':address' => $address,
                    ':id' => $user['id'],
                ]);
                set_flash('success', 'Admin profile and login credentials updated successfully.');
            }

            redirect('settings.php');
        }
    }
}

// Reload current user data
$user = current_user($pdo);

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
      <a href="reviews.php" class="admin-nav-item">Manage Reviews</a>
      <a href="collections.php" class="admin-nav-item">Collections</a>
      <a href="messages.php" class="admin-nav-item">Messages</a>
      <a href="settings.php" class="admin-nav-item active">Settings</a>
    </nav>
  </aside>

  <main class="admin-main">
    <header class="admin-header">
      <div>
        <h1>Admin Settings</h1>
        <p style="color: var(--muted); margin-top: 5px; font-size: 0.95rem;">
          Update your administrator credentials, email address, password, and account security.
        </p>
      </div>
      <div style="display: flex; gap: 10px; align-items: center;">
        <span class="role-badge role-admin">
          <?= strtoupper(h($user['role'])) ?>
        </span>
        <a href="<?= url('logout.php') ?>" class="btn small-btn" style="background: rgba(185, 28, 28, 0.1); color: #b91c1c; border: 1px solid rgba(185, 28, 28, 0.2);">Logout</a>
      </div>
    </header>

    <?php if ($errors !== []): ?>
      <div class="admin-alert-box error" style="margin-bottom: 25px;">
        <div style="display: flex; align-items: flex-start; gap: 12px;">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0; color: #b91c1c; margin-top: 2px;">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          <div>
            <strong>Please resolve the following issues:</strong>
            <ul style="margin: 8px 0 0 18px; padding: 0;">
              <?php foreach ($errors as $error): ?>
                <li><?= h($error) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- Admin Overview Card -->
    <div class="settings-overview-card">
      <div class="admin-avatar-col">
        <div class="admin-avatar-circle">
          <?= strtoupper(substr((string)($user['full_name'] ?: 'A'), 0, 1)) ?>
        </div>
      </div>
      <div class="admin-info-col">
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
          <h2 style="margin: 0; font-size: 1.35rem; color: var(--brand); font-family: 'Playfair Display', serif;">
            <?= h($user['full_name']) ?>
          </h2>
          <span class="role-badge role-admin">Administrator</span>
        </div>
        <p style="margin: 6px 0 0 0; color: var(--muted); font-size: 0.95rem;">
          Login Email: <strong style="color: var(--brand);"><?= h($user['email']) ?></strong> &bull; 
          Account ID: <strong>#<?= (int)$user['id'] ?></strong> &bull; 
          Joined: <strong><?= date('M d, Y', strtotime($user['created_at'])) ?></strong>
          <?php if (!empty($user['updated_at'])): ?>
            &bull; Last Updated: <strong><?= date('M d, Y h:i A', strtotime($user['updated_at'])) ?></strong>
          <?php endif; ?>
        </p>
      </div>
    </div>

    <!-- Settings Grid -->
    <div class="settings-grid">
      <!-- Main Credentials Form Column -->
      <div class="settings-form-column">
        <form method="post" action="settings.php" class="settings-card">
          <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="action" value="update_credentials">

          <!-- Section 1: Profile & Login Email -->
          <div class="form-section">
            <div class="section-title-wrap">
              <span class="section-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                  <circle cx="12" cy="7" r="4"></circle>
                </svg>
              </span>
              <div>
                <h3 class="section-heading">Administrator Profile & Login Email</h3>
                <p class="section-subheading">Update your display name and login email address.</p>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="full_name">Admin Full Name <span class="required-star">*</span></label>
              <input type="text" id="full_name" name="full_name" class="form-input" 
                     value="<?= h($_POST['full_name'] ?? $user['full_name']) ?>" required>
              <span class="input-hint">Displayed in admin dashboard, order logs, and header.</span>
            </div>

            <div class="form-group">
              <label class="form-label" for="email">Admin Login Email <span class="required-star">*</span></label>
              <div style="position: relative;">
                <input type="email" id="email" name="email" class="form-input" 
                       value="<?= h($_POST['email'] ?? $user['email']) ?>" required>
              </div>
              <span class="input-hint">
                <strong>Important:</strong> This email is used to log into the admin panel (<code><?= h($user['email']) ?></code>).
              </span>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
              <div class="form-group">
                <label class="form-label" for="phone">Phone Number</label>
                <input type="text" id="phone" name="phone" class="form-input" 
                       placeholder="+92 300 1234567"
                       value="<?= h($_POST['phone'] ?? $user['phone'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label class="form-label" for="address">Office / Store Address</label>
                <input type="text" id="address" name="address" class="form-input" 
                       placeholder="Lahore, Pakistan"
                       value="<?= h($_POST['address'] ?? $user['address'] ?? '') ?>">
              </div>
            </div>
          </div>

          <hr class="section-divider">

          <!-- Section 2: Security & Password Update -->
          <div class="form-section">
            <div class="section-title-wrap">
              <span class="section-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                  <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
              </span>
              <div>
                <h3 class="section-heading">Change Password & Security</h3>
                <p class="section-subheading">Update your login password or verify changes to your email address.</p>
              </div>
            </div>

            <div class="security-info-box">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="16" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12.01" y2="8"></line>
              </svg>
              <span>If you only want to update your name or phone, you can leave password fields blank. If you change your <strong>Login Email</strong> or <strong>Password</strong>, enter your current password below to authorize.</span>
            </div>

            <div class="form-group">
              <label class="form-label" for="current_password">
                Current Password <span style="font-weight: normal; color: var(--muted);">(Required if changing email or password)</span>
              </label>
              <div class="password-input-group">
                <input type="password" id="current_password" name="current_password" class="form-input" 
                       placeholder="Enter current password to authorize changes">
                <button type="button" class="password-toggle-btn" aria-label="Toggle password visibility">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                </button>
              </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
              <div class="form-group">
                <label class="form-label" for="new_password">New Password</label>
                <div class="password-input-group">
                  <input type="password" id="new_password" name="new_password" class="form-input" 
                         placeholder="Minimum 6 characters" minlength="6">
                  <button type="button" class="password-toggle-btn" aria-label="Toggle password visibility">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                      <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                  </button>
                </div>
                <span class="input-hint">Leave blank if keeping current password.</span>
              </div>

              <div class="form-group">
                <label class="form-label" for="confirm_password">Confirm New Password</label>
                <div class="password-input-group">
                  <input type="password" id="confirm_password" name="confirm_password" class="form-input" 
                         placeholder="Repeat new password" minlength="6">
                  <button type="button" class="password-toggle-btn" aria-label="Toggle password visibility">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                      <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                  </button>
                </div>
                <span class="input-hint">Must match new password exactly.</span>
              </div>
            </div>
          </div>

          <div style="margin-top: 35px; display: flex; justify-content: flex-end; gap: 12px;">
            <a href="dashboard.php" class="btn" style="background: rgba(0,0,0,0.05); color: var(--text);">Cancel</a>
            <button type="submit" class="btn" style="padding: 12px 30px; font-weight: 700;">
              Save & Update Credentials
            </button>
          </div>
        </form>
      </div>

      <!-- Right Column: Quick Info & Shortcuts -->
      <div class="settings-sidebar-column">
        <!-- Security Tips Card -->
        <div class="settings-info-card">
          <h4 style="margin: 0 0 12px 0; font-size: 1.05rem; color: var(--brand); display: flex; align-items: center; gap: 8px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--premium);">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
            </svg>
            Security Best Practices
          </h4>
          <ul class="tips-list">
            <li><strong>Keep Credentials Secret:</strong> Never share your admin password or login email on unencrypted channels.</li>
            <li><strong>Strong Password:</strong> Use a combination of uppercase letters, numbers, and symbols.</li>
            <li><strong>Login Immediate Effect:</strong> Once updated, your new email and password become active instantly.</li>
          </ul>
        </div>

        <!-- Quick Admin Navigation Card -->
        <div class="settings-info-card" style="margin-top: 20px;">
          <h4 style="margin: 0 0 12px 0; font-size: 1.05rem; color: var(--brand); display: flex; align-items: center; gap: 8px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--premium);">
              <circle cx="12" cy="12" r="3"></circle>
              <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
            </svg>
            Store Settings Shortcuts
          </h4>
          <div style="display: flex; flex-direction: column; gap: 8px;">
            <a href="payment-methods.php" class="shortcut-link">
              <span>Payment Methods</span>
              <span style="color: var(--muted);">&rarr;</span>
            </a>
            <a href="currencies.php" class="shortcut-link">
              <span>Currencies & Rates</span>
              <span style="color: var(--muted);">&rarr;</span>
            </a>
            <a href="users.php" class="shortcut-link">
              <span>Manage User Accounts</span>
              <span style="color: var(--muted);">&rarr;</span>
            </a>
          </div>
        </div>
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
.admin-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 1px solid var(--line); padding-bottom: 20px; }

.role-badge { padding: 5px 12px; border-radius: 6px; font-size: 0.75rem; font-weight: 800; letter-spacing: 0.5px; background: #eee; }
.role-admin { background: var(--premium); color: #fff; }

.settings-overview-card {
  background: var(--card);
  border-radius: 12px;
  box-shadow: var(--shadow);
  padding: 24px;
  display: flex;
  align-items: center;
  gap: 20px;
  margin-bottom: 30px;
  border-left: 5px solid var(--premium);
}

.admin-avatar-circle {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--brand-strong), var(--brand));
  color: var(--premium);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.8rem;
  font-weight: 700;
  font-family: 'Playfair Display', serif;
  box-shadow: 0 4px 12px rgba(45, 26, 34, 0.15);
}

.settings-grid {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 30px;
}

@media (max-width: 992px) {
  .settings-grid {
    grid-template-columns: 1fr;
  }
}

.settings-card {
  background: var(--card);
  border-radius: 12px;
  box-shadow: var(--shadow);
  padding: 30px;
}

.form-section {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.section-title-wrap {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 10px;
}

.section-icon {
  width: 38px;
  height: 38px;
  border-radius: 8px;
  background: var(--bg-soft);
  color: var(--brand);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.section-heading {
  margin: 0;
  font-size: 1.15rem;
  color: var(--brand);
  font-weight: 700;
}

.section-subheading {
  margin: 2px 0 0 0;
  font-size: 0.85rem;
  color: var(--muted);
}

.section-divider {
  border: 0;
  border-top: 1px solid var(--line);
  margin: 30px 0;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 0.88rem;
  font-weight: 700;
  color: var(--brand);
}

.required-star {
  color: #b91c1c;
}

.form-input {
  width: 100%;
  padding: 12px 14px;
  border: 1px solid var(--line);
  border-radius: 8px;
  font-size: 0.95rem;
  font-family: inherit;
  color: var(--text);
  background: #fff;
  transition: all 0.2s;
  box-sizing: border-box;
}

.form-input:focus {
  outline: none;
  border-color: var(--brand);
  box-shadow: 0 0 0 3px rgba(45, 26, 34, 0.08);
}

.input-hint {
  font-size: 0.8rem;
  color: var(--muted);
  line-height: 1.4;
}

.security-info-box {
  background: #fff8e6;
  border: 1px solid #fed7aa;
  border-radius: 8px;
  padding: 12px 16px;
  color: #9a3412;
  font-size: 0.85rem;
  line-height: 1.5;
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-bottom: 8px;
}

.password-input-group {
  position: relative;
  display: flex;
  align-items: center;
}

.password-input-group .form-input {
  padding-right: 44px;
}

.password-toggle-btn {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: var(--muted);
  cursor: pointer;
  padding: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 4px;
}

.password-toggle-btn:hover {
  color: var(--brand);
}

.password-toggle-btn svg {
  width: 18px;
  height: 18px;
}

.admin-alert-box {
  padding: 16px 20px;
  border-radius: 8px;
  font-size: 0.9rem;
  line-height: 1.5;
}

.admin-alert-box.error {
  background: #fee2e2;
  border: 1px solid #fecaca;
  color: #991b1b;
}

.settings-info-card {
  background: var(--card);
  border-radius: 12px;
  box-shadow: var(--shadow);
  padding: 24px;
}

.tips-list {
  margin: 0;
  padding-left: 20px;
  font-size: 0.85rem;
  color: var(--muted);
  line-height: 1.6;
}

.tips-list li {
  margin-bottom: 10px;
}

.tips-list li:last-child {
  margin-bottom: 0;
}

.shortcut-link {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: var(--bg-soft);
  border-radius: 8px;
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--brand);
  transition: all 0.2s;
}

.shortcut-link:hover {
  background: rgba(45, 26, 34, 0.08);
  padding-left: 20px;
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
