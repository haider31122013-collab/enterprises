<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin($pdo);

$pageTitle = 'Payment Methods | Admin';

// Handle Delete
if (isset($_GET['delete'])) {
    enforce_csrf();
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare('DELETE FROM payment_methods WHERE id = ?');
    $stmt->execute([$id]);
    set_flash('success', 'Payment method deleted successfully.');
    redirect('payment-methods.php');
}

// Handle Toggle Active
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $pdo->prepare('UPDATE payment_methods SET active = IF(active = 1, 0, 1), updated_at = NOW() WHERE id = ?');
    $stmt->execute([$id]);
    set_flash('success', 'Payment method status updated.');
    redirect('payment-methods.php');
}

// Handle Add / Edit
$editMethod = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare('SELECT * FROM payment_methods WHERE id = ?');
    $stmt->execute([$id]);
    $editMethod = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    enforce_csrf();
    $action = $_POST['action'];

    $name = trim((string)($_POST['name'] ?? ''));
    $code = trim((string)($_POST['code'] ?? ''));
    $account_title = trim((string)($_POST['account_title'] ?? ''));
    $account_number = trim((string)($_POST['account_number'] ?? ''));
    $bank_name = trim((string)($_POST['bank_name'] ?? ''));
    $branch_code = trim((string)($_POST['branch_code'] ?? ''));
    $instructions = trim((string)($_POST['instructions'] ?? ''));
    $icon = trim((string)($_POST['icon'] ?? '💳'));
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $active = isset($_POST['active']) ? 1 : 0;

    if ($icon === '') {
        $icon = '💳';
    }

    if ($name === '') {
        set_flash('error', 'Payment method name is required.');
        redirect('payment-methods.php');
    }

    if ($code === '') {
        $baseCode = strtolower(preg_replace('/[^a-z0-9]+/', '_', $name));
        $code = trim($baseCode, '_');
    } else {
        $code = strtolower(preg_replace('/[^a-z0-9_]+/', '_', $code));
    }

    // Handle QR / Image
    $qr_image = '';
    if (isset($_POST['qr_source']) && $_POST['qr_source'] === 'upload') {
        if (isset($_FILES['qr_file']) && $_FILES['qr_file']['error'] === UPLOAD_ERR_OK) {
            $uploaded = upload_image($_FILES['qr_file']);
            if ($uploaded) {
                $qr_image = $uploaded;
            }
        }
    } elseif (isset($_POST['qr_image_url'])) {
        $qr_image = trim((string)$_POST['qr_image_url']);
    }

    if ($action === 'add') {
        // Check for duplicate code
        $stmtCheck = $pdo->prepare('SELECT id FROM payment_methods WHERE code = ?');
        $stmtCheck->execute([$code]);
        if ($stmtCheck->fetch()) {
            $code .= '_' . rand(10, 99);
        }

        $stmt = $pdo->prepare('INSERT INTO payment_methods 
            (name, code, account_title, account_number, bank_name, branch_code, instructions, qr_image, icon, active, sort_order, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([
            $name, $code, $account_title, $account_number, $bank_name, $branch_code, $instructions, $qr_image, $icon, $active, $sort_order
        ]);
        set_flash('success', "Payment method '$name' added successfully.");
        redirect('payment-methods.php');
    } elseif ($action === 'update' && isset($_POST['id'])) {
        $methodId = (int)$_POST['id'];

        // If no new image uploaded, retain existing image
        if ($qr_image === '') {
            $existingStmt = $pdo->prepare('SELECT qr_image FROM payment_methods WHERE id = ?');
            $existingStmt->execute([$methodId]);
            $qr_image = (string)$existingStmt->fetchColumn();
        }

        // Check duplicate code excluding current
        $stmtCheck = $pdo->prepare('SELECT id FROM payment_methods WHERE code = ? AND id != ?');
        $stmtCheck->execute([$code, $methodId]);
        if ($stmtCheck->fetch()) {
            $code .= '_' . rand(10, 99);
        }

        $stmt = $pdo->prepare('UPDATE payment_methods SET 
            name = ?, code = ?, account_title = ?, account_number = ?, bank_name = ?, branch_code = ?, instructions = ?, qr_image = ?, icon = ?, active = ?, sort_order = ?, updated_at = NOW()
            WHERE id = ?');
        $stmt->execute([
            $name, $code, $account_title, $account_number, $bank_name, $branch_code, $instructions, $qr_image, $icon, $active, $sort_order, $methodId
        ]);
        set_flash('success', "Payment method '$name' updated successfully.");
        redirect('payment-methods.php');
    }
}

// Fetch all payment methods
$paymentMethods = $pdo->query('SELECT * FROM payment_methods ORDER BY sort_order ASC, id ASC')->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="admin-layout">
  <aside class="admin-sidebar">
    <nav class="admin-nav">
      <a href="dashboard.php" class="admin-nav-item">Dashboard</a>
      <a href="orders.php" class="admin-nav-item">Manage Orders</a>
      <a href="products.php" class="admin-nav-item">Manage Products</a>
      <a href="payment-methods.php" class="admin-nav-item active">Payment Methods</a>
      <a href="currencies.php" class="admin-nav-item">Currencies</a>
      <a href="users.php" class="admin-nav-item">Manage Users</a>
      <a href="reviews.php" class="admin-nav-item">Manage Reviews</a>
      <a href="collections.php" class="admin-nav-item">Collections</a>
      <a href="messages.php" class="admin-nav-item">Messages</a>
      <a href="settings.php" class="admin-nav-item">Settings</a>
    </nav>
  </aside>

  <main class="admin-main">
    <header class="admin-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
      <div>
        <h1>Payment Methods</h1>
        <p style="color: var(--muted); margin-top: 5px; font-size: 0.95rem;">Manage active payment gateways, bank accounts, IBANs, and checkout instructions.</p>
      </div>
      <button onclick="toggleForm()" class="btn" id="toggleFormBtn">
        <?= $editMethod ? 'Edit Payment Method' : '+ Add Payment Method' ?>
      </button>
    </header>

    <?php
      $flashSuccess = get_flash('success');
      $flashError = get_flash('error');
    ?>
    <?php if ($flashSuccess): ?>
      <div class="flash success" style="margin-bottom: 25px; padding: 15px 20px; background: #D4EDDA; color: #155724; border-radius: 8px; font-weight: 600;"><?= h($flashSuccess) ?></div>
    <?php endif; ?>
    <?php if ($flashError): ?>
      <div class="flash error" style="margin-bottom: 25px; padding: 15px 20px; background: #F8D7DA; color: #721C24; border-radius: 8px; font-weight: 600;"><?= h($flashError) ?></div>
    <?php endif; ?>

    <!-- Add / Edit Form Modal / Panel -->
    <div id="methodFormContainer" style="display: <?= $editMethod ? 'block' : 'none' ?>; background: var(--card); padding: 30px; border-radius: 16px; box-shadow: var(--shadow); margin-bottom: 35px; border: 2px solid var(--line);">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 1px solid var(--line); padding-bottom: 15px;">
        <h2 style="font-family: 'Playfair Display', serif; font-size: 1.6rem; color: var(--brand); margin: 0;">
          <?= $editMethod ? 'Edit Payment Method: ' . h($editMethod['name']) : 'Add New Payment Method' ?>
        </h2>
        <button type="button" onclick="toggleForm(false)" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--muted);">&times;</button>
      </div>

      <form method="post" action="payment-methods.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="<?= $editMethod ? 'update' : 'add' ?>">
        <?php if ($editMethod): ?>
          <input type="hidden" name="id" value="<?= (int)$editMethod['id'] ?>">
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 20px; margin-bottom: 20px;">
          <div>
            <label class="input-label" for="pm_name">Method Name *</label>
            <input class="input" type="text" id="pm_name" name="name" value="<?= h($editMethod['name'] ?? '') ?>" placeholder="e.g. Direct Bank Transfer, Meezan Bank, EasyPaisa" required>
          </div>
          <div>
            <label class="input-label" for="pm_code">Identifier / Code *</label>
            <input class="input" type="text" id="pm_code" name="code" value="<?= h($editMethod['code'] ?? '') ?>" placeholder="e.g. bank_transfer, cod, jazzcash" required>
            <small style="color: var(--muted); font-size: 0.75rem;">Unique key used internally</small>
          </div>
          <div>
            <label class="input-label" for="pm_icon">Icon / Emoji</label>
            <input class="input" type="text" id="pm_icon" name="icon" value="<?= h($editMethod['icon'] ?? '🏦') ?>" placeholder="e.g. 🏦, 💵, 📱, 💳">
          </div>
        </div>

        <div style="background: var(--bg-soft); padding: 22px; border-radius: 12px; margin-bottom: 20px; border: 1px dashed #D2ADA1;">
          <h4 style="margin: 0 0 15px; color: var(--brand); font-size: 1.05rem;">🏦 Bank & Account Details (For Customer Payment)</h4>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">
            <div>
              <label class="input-label" for="pm_account_title">Account Title</label>
              <input class="input" type="text" id="pm_account_title" name="account_title" value="<?= h($editMethod['account_title'] ?? '') ?>" placeholder="e.g. Resources Enterprises">
              <small style="color: var(--muted); font-size: 0.75rem;">Account or Merchant Title</small>
            </div>
            <div>
              <label class="input-label" for="pm_account_number">Account Number / IBAN</label>
              <input class="input" type="text" id="pm_account_number" name="account_number" value="<?= h($editMethod['account_number'] ?? '') ?>" placeholder="e.g. PK08ASCM0003790900002243">
              <small style="color: var(--muted); font-size: 0.75rem;">Bank IBAN or Mobile Account Number / TILL ID</small>
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
              <label class="input-label" for="pm_bank_name">Bank Name / Provider</label>
              <input class="input" type="text" id="pm_bank_name" name="bank_name" value="<?= h($editMethod['bank_name'] ?? '') ?>" placeholder="e.g. Askari Bank, Meezan Bank, JazzCash">
            </div>
            <div>
              <label class="input-label" for="pm_branch_code">Branch Code / Swift (Optional)</label>
              <input class="input" type="text" id="pm_branch_code" name="branch_code" value="<?= h($editMethod['branch_code'] ?? '') ?>" placeholder="e.g. 0379">
            </div>
          </div>
        </div>

        <div style="margin-bottom: 20px;">
          <label class="input-label" for="pm_instructions">Payment Instructions for Customer</label>
          <textarea class="input" id="pm_instructions" name="instructions" rows="3" placeholder="Enter instructions shown to customer at checkout (e.g. Dial *786# or Transfer to IBAN and share receipt)..."><?= h($editMethod['instructions'] ?? '') ?></textarea>
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 20px; margin-bottom: 25px; align-items: start;">
          <div>
            <label class="input-label">QR Code / Logo (Optional)</label>
            <div style="display: flex; gap: 15px; margin-bottom: 10px;">
              <label style="cursor: pointer;"><input type="radio" name="qr_source" value="url" <?= empty($editMethod['qr_image']) || str_starts_with($editMethod['qr_image'] ?? '', 'http') ? 'checked' : '' ?> onclick="toggleQrInput('url')"> Image URL</label>
              <label style="cursor: pointer;"><input type="radio" name="qr_source" value="upload" <?= !empty($editMethod['qr_image']) && !str_starts_with($editMethod['qr_image'] ?? '', 'http') ? 'checked' : '' ?> onclick="toggleQrInput('upload')"> Upload File</label>
            </div>
            <div id="qr_url_field">
              <input class="input" type="text" name="qr_image_url" value="<?= h($editMethod['qr_image'] ?? '') ?>" placeholder="e.g. assets/jazzcash_qr.jpg or https://...">
            </div>
            <div id="qr_upload_field" style="display: none;">
              <input class="input" type="file" name="qr_file" accept="image/*">
            </div>
            <?php if (!empty($editMethod['qr_image'])): ?>
              <div style="margin-top: 10px;">
                <span style="font-size: 0.8rem; color: var(--muted);">Current Image:</span><br>
                <img src="<?= h(url($editMethod['qr_image'])) ?>" alt="QR" style="max-height: 80px; border-radius: 8px; border: 1px solid var(--line); margin-top: 5px;">
              </div>
            <?php endif; ?>
          </div>

          <div>
            <label class="input-label" for="pm_sort_order">Sort Order</label>
            <input class="input" type="number" id="pm_sort_order" name="sort_order" value="<?= (int)($editMethod['sort_order'] ?? 1) ?>" min="0">
            <small style="color: var(--muted); font-size: 0.75rem;">Lower appears first on checkout</small>
          </div>

          <div style="padding-top: 25px;">
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 700; color: var(--brand);">
              <input type="checkbox" name="active" value="1" <?= (!isset($editMethod) || ($editMethod['active'] ?? 1) == 1) ? 'checked' : '' ?> style="transform: scale(1.4); accent-color: var(--brand);">
              <span>Enable (Active on Checkout)</span>
            </label>
          </div>
        </div>

        <div style="display: flex; gap: 15px; border-top: 1px solid var(--line); padding-top: 20px;">
          <button type="submit" class="btn"><?= $editMethod ? 'Save Changes' : 'Create Payment Method' ?></button>
          <?php if ($editMethod): ?>
            <a href="payment-methods.php" class="btn btn-outline" style="background: transparent; color: var(--brand); border: 2px solid var(--brand);">Cancel Edit</a>
          <?php else: ?>
            <button type="button" onclick="toggleForm(false)" class="btn btn-outline" style="background: transparent; color: var(--brand); border: 2px solid var(--brand);">Cancel</button>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- Payment Methods Cards / Table -->
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Order</th>
            <th>Method</th>
            <th>Code</th>
            <th>Account Title</th>
            <th>IBAN / Account Number</th>
            <th>Bank / Provider</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($paymentMethods)): ?>
            <tr>
              <td colspan="8" style="text-align: center; padding: 40px; color: var(--muted);">No payment methods configured yet. Click "+ Add Payment Method" above.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($paymentMethods as $pm): ?>
              <tr>
                <td style="font-weight: 700; color: var(--muted);"><?= (int)$pm['sort_order'] ?></td>
                <td>
                  <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="font-size: 1.8rem;"><?= h($pm['icon'] ?: '💳') ?></span>
                    <div>
                      <div style="font-weight: 800; color: var(--brand); font-size: 1.05rem;"><?= h($pm['name']) ?></div>
                      <?php if (!empty($pm['instructions'])): ?>
                        <div style="font-size: 0.78rem; color: var(--muted); max-width: 280px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= h($pm['instructions']) ?>">
                          <?= h($pm['instructions']) ?>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                </td>
                <td>
                  <code style="background: var(--bg-soft); padding: 4px 8px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; color: #5D100B;"><?= h($pm['code']) ?></code>
                </td>
                <td>
                  <strong style="color: var(--brand);"><?= h($pm['account_title'] ?: '—') ?></strong>
                </td>
                <td>
                  <?php if (!empty($pm['account_number'])): ?>
                    <div style="font-family: monospace; font-size: 0.95rem; font-weight: 700; background: #FFF9E6; padding: 4px 10px; border-radius: 6px; border: 1px solid #FFE082; display: inline-block;">
                      <?= h($pm['account_number']) ?>
                    </div>
                  <?php else: ?>
                    <span style="color: var(--muted);">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?= h($pm['bank_name'] ?: '—') ?>
                  <?php if (!empty($pm['branch_code'])): ?>
                    <span style="font-size: 0.75rem; color: var(--muted);">(<?= h($pm['branch_code']) ?>)</span>
                  <?php endif; ?>
                </td>
                <td>
                  <a href="payment-methods.php?toggle=<?= (int)$pm['id'] ?>" title="Click to toggle status" style="text-decoration: none;">
                    <?php if ((int)$pm['active'] === 1): ?>
                      <span class="badge paid" style="cursor: pointer;">Active</span>
                    <?php else: ?>
                      <span class="badge role-admin" style="cursor: pointer; background: #e5e7eb; color: #4b5563;">Disabled</span>
                    <?php endif; ?>
                  </a>
                </td>
                <td>
                  <div style="display: flex; gap: 8px;">
                    <a href="payment-methods.php?edit=<?= (int)$pm['id'] ?>" class="btn small-btn" style="padding: 6px 12px; font-size: 0.8rem; text-decoration: none;">Edit</a>
                    <form method="get" action="payment-methods.php" onsubmit="return confirm('Are you sure you want to delete payment method \'<?= h(addslashes($pm['name'])) ?>\'?');" style="display: inline;">
                      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                      <input type="hidden" name="delete" value="<?= (int)$pm['id'] ?>">
                      <button type="submit" class="btn small-btn" style="padding: 6px 12px; font-size: 0.8rem; background: #DC2626; color: #fff;">Delete</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Quick Preview Section for Admin -->
    <div style="margin-top: 35px; background: var(--card); padding: 25px; border-radius: 16px; box-shadow: var(--shadow);">
      <h3 style="font-family: 'Playfair Display', serif; font-size: 1.3rem; color: var(--brand); margin-bottom: 15px;">
        💡 How Payment Methods Work in Checkout
      </h3>
      <p style="color: var(--muted); line-height: 1.6; margin-bottom: 12px;">
        All active payment methods listed above are automatically loaded on the customer checkout page. When a customer selects a method (e.g., <strong>Direct Bank Transfer</strong>), the system will display the corresponding Account Title, Bank IBAN, and payment instructions.
      </p>
      <div style="background: #EBF8FF; border-left: 4px solid #3182CE; padding: 15px 20px; border-radius: 8px; color: #2B6CB0; font-size: 0.95rem;">
        <strong>Configured Account Details:</strong><br>
        Title: <code>Resources Enterprises</code> | IBAN: <code>PK08ASCM0003790900002243</code> | Bank: <code>Askari Bank</code><br>
        You can edit or change these details at any time using the <strong>Edit</strong> button above.
      </div>
    </div>
  </main>
</div>

<script>
function toggleForm(show = true) {
  const container = document.getElementById('methodFormContainer');
  if (show === false || container.style.display === 'block') {
    container.style.display = 'none';
  } else {
    container.style.display = 'block';
    container.scrollIntoView({ behavior: 'smooth' });
  }
}

function toggleQrInput(type) {
  document.getElementById('qr_url_field').style.display = type === 'url' ? 'block' : 'none';
  document.getElementById('qr_upload_field').style.display = type === 'upload' ? 'block' : 'none';
}
</script>

<style>
.admin-layout { display: grid; grid-template-columns: 250px 1fr; min-height: calc(100vh - 90px); background: var(--bg); }
.admin-sidebar { background: var(--brand-strong); color: #fff; padding: 40px 0; }
.admin-nav { display: flex; flex-direction: column; }
.admin-nav-item { padding: 15px 30px; color: rgba(255, 255, 255, 0.7); text-decoration: none; font-weight: 600; transition: all 0.3s; border-left: 4px solid transparent; }
.admin-nav-item:hover, .admin-nav-item.active { color: var(--premium); background: rgba(255, 255, 255, 0.05); border-left-color: var(--premium); }
.admin-main { padding: 40px; }
.admin-header { margin-bottom: 30px; border-bottom: 1px solid var(--line); padding-bottom: 20px; }

.admin-table-wrap { background: var(--card); border-radius: 12px; box-shadow: var(--shadow); overflow-x: auto; }
.admin-table { width: 100%; border-collapse: collapse; text-align: left; }
.admin-table th { background: rgba(0,0,0,0.02); padding: 15px 20px; font-size: 0.85rem; text-transform: uppercase; color: var(--muted); white-space: nowrap; }
.admin-table td { padding: 15px 20px; border-bottom: 1px solid var(--line); }
.admin-table tbody tr:hover { background: rgba(93, 16, 11, 0.02); }

.badge { padding: 5px 12px; border-radius: 30px; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; display: inline-block; }
.badge.paid { background: #D4EDDA; color: #155724; border: 1px solid #C3E6CB; }
.badge.role-admin { background: #F8D7DA; color: #721C24; }

.btn.small-btn { padding: 8px 16px; font-size: 0.85rem; border-radius: 8px; }
.btn { display: inline-block; background: var(--brand); color: #fff; border: none; padding: 12px 28px; font-weight: 700; border-radius: 12px; cursor: pointer; text-decoration: none; transition: all 0.3s; }
.btn:hover { transform: translateY(-1px); box-shadow: 0 5px 15px rgba(93, 16, 11, 0.3); }

.input-label { display: block; font-weight: 700; color: var(--brand); margin-bottom: 8px; font-size: 0.9rem; }
.input { width: 100%; padding: 11px 15px; border: 2px solid var(--line); border-radius: 10px; font-size: 0.95rem; font-family: inherit; transition: all 0.3s; background: #fff; box-sizing: border-box; }
.input:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px rgba(93, 16, 11, 0.1); }

@media (max-width: 920px) {
  .admin-layout { grid-template-columns: 1fr; }
  .admin-sidebar { padding: 20px; }
  .admin-nav { flex-direction: row; overflow-x: auto; padding-bottom: 10px; }
  .admin-nav-item { padding: 10px 20px; white-space: nowrap; border-left: none; border-bottom: 3px solid transparent; }
  .admin-nav-item:hover, .admin-nav-item.active { border-bottom-color: var(--premium); }
  .admin-main { padding: 20px; }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
