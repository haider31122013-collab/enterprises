<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$pageTitle = 'Signup | ' . APP_NAME;
$pageDescription = 'Create your ecommerce account to place and track orders.';

$fullName = '';
$email = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();

    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($fullName === '') {
        $errors[] = 'Full name is required.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Password confirmation does not match.';
    }

    if ($errors === []) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users (full_name, email, password_hash, created_at)
                 VALUES (:full_name, :email, :password_hash, :created_at)'
            );
            $stmt->execute([
                ':full_name' => $fullName,
                ':email' => $email,
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ':created_at' => date('Y-m-d H:i:s'),
            ]);

            $_SESSION['user_id'] = (int) $pdo->lastInsertId();
            set_flash('success', 'Welcome! Your account has been created.');
            redirect('dashboard.php');
        } catch (Throwable $exception) {
            $errors[] = 'This email is already registered.';
        }
    }
}

include __DIR__ . '/includes/header.php';
?>
<section class="auth-wrap fade">
  <article class="auth-card">
    <p class="eyebrow">Create Account</p>
    <h1>Signup</h1>
    <p class="helper-text">Register once to place orders and track your purchase history.</p>

    <?php if ($errors !== []): ?>
      <div class="flash error">
        <?= h(implode(' ', $errors)) ?>
      </div>
    <?php endif; ?>

    <form method="post" action="signup.php">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

      <label class="input-label" for="full_name">Full Name</label>
      <input class="input" id="full_name" name="full_name" value="<?= h($fullName) ?>" placeholder="Enter your full name" required>

      <label class="input-label" for="email">Email Address</label>
      <input class="input" id="email" name="email" type="email" value="<?= h($email) ?>" placeholder="Enter your email" required>

      <label class="input-label" for="password">Password</label>
      <div class="password-input-group">
        <input class="input" id="password" name="password" type="password" placeholder="Create a password" required>
        <button type="button" class="password-toggle-btn" aria-label="Toggle password visibility">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
            <circle cx="12" cy="12" r="3"></circle>
          </svg>
        </button>
      </div>

      <label class="input-label" for="confirm_password">Confirm Password</label>
      <div class="password-input-group">
        <input class="input" id="confirm_password" name="confirm_password" type="password" placeholder="Confirm your password" required>
        <button type="button" class="password-toggle-btn" aria-label="Toggle password visibility">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
            <circle cx="12" cy="12" r="3"></circle>
          </svg>
        </button>
      </div>

      <button type="submit" class="btn">Create Account</button>
    </form>

    <p class="helper-text" style="margin-top: 30px;">Already have an account? <a href="login.php" style="color: #D2ADA1; font-weight: 700;">Login</a></p>
  </article>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
