<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_login();

$user = current_user($pdo);

$pageTitle = 'Profile | ' . APP_NAME;
$pageDescription = 'Update your profile and account password.';

$fullName = (string) ($user['full_name'] ?? '');
$email = (string) ($user['email'] ?? '');
$phone = (string) ($user['phone'] ?? '');
$address = (string) ($user['address'] ?? '');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();

    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    if ($fullName === '') {
        $fullName = (string) ($user['full_name'] ?? '');
    }

    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    if ($email === '') {
        $email = (string) ($user['email'] ?? '');
    }

    $phone = trim((string) ($_POST['phone'] ?? ''));
    if ($phone === '') {
        $phone = (string) ($user['phone'] ?? '');
    }

    $address = trim((string) ($_POST['address'] ?? ''));
    if ($address === '') {
        $address = (string) ($user['address'] ?? '');
    }

    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    // Only validate email if it's changed
    if ($email !== (string) ($user['email'] ?? '')) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        } else {
            $emailStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1');
            $emailStmt->execute([
                ':email' => $email,
                ':id' => $user['id'],
            ]);

            if ($emailStmt->fetch()) {
                $errors[] = 'This email is already linked to another account.';
            }
        }
    }

    $updatePassword = false;
    $newPasswordHash = '';

    if ($currentPassword !== '' || $newPassword !== '' || $confirmPassword !== '') {
        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $errors[] = 'To change password, fill current password and new password fields.';
        } elseif (!password_verify($currentPassword, (string) $user['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($newPassword) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $errors[] = 'New password confirmation does not match.';
        } else {
            $updatePassword = true;
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        }
    }

    if ($errors === []) {
        if ($updatePassword) {
            $updateStmt = $pdo->prepare(
                'UPDATE users
                 SET full_name = :full_name, email = :email, phone = :phone, address = :address, password_hash = :password_hash
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
        } else {
            $updateStmt = $pdo->prepare(
                'UPDATE users
                 SET full_name = :full_name, email = :email, phone = :phone, address = :address
                 WHERE id = :id'
            );
            $updateStmt->execute([
                ':full_name' => $fullName,
                ':email' => $email,
                ':phone' => $phone,
                ':address' => $address,
                ':id' => $user['id'],
            ]);
        }

        set_flash('success', 'Profile updated successfully.');
        redirect('profile.php');
    }
}

include __DIR__ . '/includes/header.php';
?>
<section class="dashboard-wrap">
  <div class="section-inner" style="max-width: 800px;">
    <article class="panel fade">
      <p class="eyebrow">Account Settings</p>
      <h1 style="font-family: 'Playfair Display', serif; font-size: 3rem; color: var(--brand); margin-bottom: 10px;">My Profile</h1>
      <p class="helper-text" style="margin-bottom: 40px;">Manage personal details and keep your account information updated.</p>

      <?php if ($errors !== []): ?>
        <div class="flash error" style="margin-bottom: 25px;">
          <?= h(implode(' ', $errors)) ?>
        </div>
      <?php endif; ?>

      <?php if (is_admin($pdo)): ?>
        <div style="margin-bottom: 25px; padding: 16px 20px; background: var(--bg-soft); border-radius: 10px; border-left: 4px solid var(--premium); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
          <div>
            <strong style="color: var(--brand);">Administrator Account</strong>
            <p style="margin: 3px 0 0; font-size: 0.85rem; color: var(--muted);">You can also manage your credentials directly from the Admin Settings panel.</p>
          </div>
          <a href="<?= url('admin/settings.php') ?>" class="btn small-btn" style="white-space: nowrap;">Go to Admin Settings &rarr;</a>
        </div>
      <?php endif; ?>

      <form method="post" action="profile.php">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
          <div>
            <label class="input-label" for="full_name">Full Name</label>
            <input class="input" id="full_name" name="full_name" value="<?= h($fullName) ?>" placeholder="Your full name">
          </div>
          <div>
            <label class="input-label" for="email">Email Address</label>
            <input class="input" id="email" name="email" type="email" value="<?= h($email) ?>" placeholder="Your email">
          </div>
        </div>

        <label class="input-label" for="phone">Phone Number</label>
        <input class="input" id="phone" name="phone" value="<?= h($phone) ?>" placeholder="Your phone number">

        <label class="input-label" for="address">Shipping Address</label>
        <textarea class="input" id="address" name="address" rows="3" placeholder="Your complete shipping address"><?= h($address) ?></textarea>

        <div style="margin: 40px 0; padding: 30px; background: var(--bg-soft); border-radius: 16px; border: 1px solid var(--line);">
          <h3 style="font-family: 'Playfair Display', serif; font-size: 1.4rem; color: #5D100B; margin-bottom: 20px;">Security & Password</h3>
          <p class="helper-text" style="margin-bottom: 20px; font-size: 0.85rem;">Leave these blank if you don't want to change your password.</p>
          
          <label class="input-label" for="current_password">Current Password</label>
          <div class="password-input-group">
            <input class="input" id="current_password" name="current_password" type="password" placeholder="Enter current password">
            <button type="button" class="password-toggle-btn" aria-label="Toggle password visibility">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
              </svg>
            </button>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
            <div>
              <label class="input-label" for="new_password" style="margin-top: 0;">New Password</label>
              <div class="password-input-group">
                <input class="input" id="new_password" name="new_password" type="password" placeholder="Min 6 characters">
                <button type="button" class="password-toggle-btn" aria-label="Toggle password visibility">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                </button>
              </div>
            </div>
            <div>
              <label class="input-label" for="confirm_password" style="margin-top: 0;">Confirm New Password</label>
              <div class="password-input-group">
                <input class="input" id="confirm_password" name="confirm_password" type="password" placeholder="Repeat new password">
                <button type="button" class="password-toggle-btn" aria-label="Toggle password visibility">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn" style="padding: 18px 45px;">Save Changes</button>
          <a href="dashboard.php" class="btn btn-outline" style="padding: 18px 45px;">Back to Dashboard</a>
        </div>
      </form>
    </article>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
