<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$pageTitle = 'Login | ' . APP_NAME;
$pageDescription = 'Login to your ecommerce account and manage your orders.';

$redirectTo = validate_redirect_target((string) ($_GET['redirect'] ?? 'dashboard.php'));
$email = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();

    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $redirectTo = validate_redirect_target((string) ($_POST['redirect'] ?? 'dashboard.php'));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $errors[] = 'Enter a valid email and password.';
    } else {
        $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = (int) $user['id'];
            set_flash('success', 'Welcome back.');

            // Check if admin and redirect to admin dashboard if no specific redirect is set
            if (is_admin($pdo) && $redirectTo === 'dashboard.php') {
                redirect('admin/dashboard.php');
            }

            redirect($redirectTo);
        }

        $errors[] = 'Invalid credentials.';
    }
}

include __DIR__ . '/includes/header.php';
?>
<section class="auth-wrap fade">
  <article class="auth-card">
    <p class="eyebrow">Account Access</p>
    <h1>Login</h1>
    <p class="helper-text">Use your account to place orders and track all purchases.</p>

    <?php if ($errors !== []): ?>
      <div class="flash error">
        <?= h(implode(' ', $errors)) ?>
      </div>
    <?php endif; ?>

    <form method="post" action="login.php">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="redirect" value="<?= h($redirectTo) ?>">

      <label class="input-label" for="email">Email Address</label>
      <input class="input" id="email" name="email" type="email" value="<?= h($email) ?>" placeholder="Enter your email" required>

      <label class="input-label" for="password">Password</label>
      <div class="password-input-group">
        <input class="input" id="password" name="password" type="password" placeholder="Enter your password" required>
        <button type="button" class="password-toggle-btn" aria-label="Toggle password visibility">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
            <circle cx="12" cy="12" r="3"></circle>
          </svg>
        </button>
      </div>

      <button type="submit" class="btn">Login to Account</button>
    </form>

    <p class="helper-text" style="margin-top: 30px;">New user? <a href="signup.php" style="color: #D2ADA1; font-weight: 700;">Create account</a></p>
  </article>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
