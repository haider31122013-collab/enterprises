<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Contact Us | ' . APP_NAME;
$pageDescription = 'Reach Resources Enterprises for ecommerce support and bulk inquiries.';

$user = current_user($pdo);
$productNames = $pdo->query('SELECT name FROM products WHERE active = 1 ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);

$name = trim((string) ($user['full_name'] ?? ''));
$email = trim((string) ($user['email'] ?? ''));
$topic = trim((string) ($_GET['inquiry'] ?? 'General Inquiry'));
$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();

    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $topic = trim((string) ($_POST['topic'] ?? 'General Inquiry'));
    $message = trim((string) ($_POST['message'] ?? ''));

    if ($name === '') {
        $errors[] = 'Name is required.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    if ($message === '') {
        $errors[] = 'Message is required.';
    }

    if ($errors === []) {
        try {
            $insertStmt = $pdo->prepare(
                'INSERT INTO contact_messages (user_id, name, email, topic, message, created_at)
                 VALUES (:user_id, :name, :email, :topic, :message, :created_at)'
            );
            $insertStmt->execute([
                ':user_id' => current_user_id(),
                ':name' => $name,
                ':email' => $email,
                ':topic' => $topic,
                ':message' => $message,
                ':created_at' => date('Y-m-d H:i:s'),
            ]);

            set_flash('success', 'Thanks! Your inquiry has been received. Our team will contact you shortly.');
            redirect('contact.php');
        } catch (Throwable $exception) {
            $errors[] = 'We could not save your message right now. Please try again.';
        }
    }
}

include __DIR__ . '/includes/header.php';
?>
<section class="page-hero fade">
  <div class="section-inner">
    <p class="eyebrow">Support</p>
    <h2>Contact Resources Enterprises</h2>
    <p>For product guidance, order follow-up, and bulk inquiries, send us a message.</p>
  </div>
</section>

<section class="contact-grid">
  <article class="panel fade">
    <p class="eyebrow">Connect</p>
    <h2 style="font-family: 'Playfair Display', serif; font-size: 2.5rem; color: var(--brand); margin-bottom: 15px;">Get In Touch</h2>
    <p class="helper-text">Have a question or looking for a partnership? Reach out to us through any of these channels.</p>

    <div class="contact-list">
      <div class="contact-item">
        <p class="label">Our Office</p>
        <p>Wazirabad & Lahore, Pakistan</p>
      </div>
      <div class="contact-item">
        <p class="label">Email Address</p>
        <p>resourceent.operations@gmail.com</p>
      </div>
      <div class="contact-item">
        <p class="label">WhatsApp Support</p>
        <p><a href="https://wa.me/923164169611" target="_blank" rel="noopener noreferrer" style="color: #5D100B; font-weight: 800; text-decoration: underline;">+92 316 4169611</a></p>
      </div>
    </div>

    <div style="margin-top: 40px; padding: 30px; background: var(--bg-soft); border-radius: 20px; border: 1px dashed #D2ADA1;">
      <h4 style="color: #5D100B; margin-bottom: 10px; font-size: 1.1rem;">Business Hours</h4>
      <p class="helper-text" style="margin-bottom: 5px;">Monday — Saturday</p>
      <p style="font-weight: 700; color: var(--brand);">9:00 AM — 6:00 PM (PKT)</p>
    </div>
  </article>

  <article class="panel fade" style="background: #fff; box-shadow: 0 30px 60px rgba(0,0,0,0.05);">
    <p class="eyebrow">Inquiry</p>
    <h2 style="font-family: 'Playfair Display', serif; font-size: 2.5rem; color: var(--brand); margin-bottom: 15px;">Send a Message</h2>
    <p class="helper-text" style="margin-bottom: 35px;">Fill out the form below and our team will get back to you within 24 hours.</p>

    <?php if ($errors !== []): ?>
      <div class="flash error" style="margin-bottom: 25px;">
        <?= h(implode(' ', $errors)) ?>
      </div>
    <?php endif; ?>

    <form method="post" action="contact.php" class="contact-form">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div>
          <label class="input-label" for="name">Your Name</label>
          <input class="input" id="name" name="name" value="<?= h($name) ?>" placeholder="Enter full name" required>
        </div>
        <div>
          <label class="input-label" for="email">Email Address</label>
          <input class="input" id="email" name="email" type="email" value="<?= h($email) ?>" placeholder="Enter email address" required>
        </div>
      </div>

      <label class="input-label" for="topic">Inquiry Topic</label>
      <select class="select" id="topic" name="topic">
        <option value="General Inquiry" <?= $topic === 'General Inquiry' ? 'selected' : '' ?>>General Inquiry</option>
        <option value="Bulk/Export Order" <?= $topic === 'Bulk/Export Order' ? 'selected' : '' ?>>Bulk/Export Order</option>
        <option value="Custom Manufacturing" <?= $topic === 'Custom Manufacturing' ? 'selected' : '' ?>>Custom Manufacturing</option>
        <?php foreach ($productNames as $productName): ?>
          <option value="<?= h($productName) ?>" <?= $topic === $productName ? 'selected' : '' ?>>Product: <?= h($productName) ?></option>
        <?php endforeach; ?>
      </select>

      <label class="input-label" for="message">How can we help?</label>
      <textarea class="input" id="message" name="message" rows="5" placeholder="Describe your requirements in detail..." required><?= h($message) ?></textarea>

      <button type="submit" class="btn">Send Inquiry</button>
    </form>
  </article>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
