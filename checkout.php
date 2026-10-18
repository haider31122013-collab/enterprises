<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_login();

$items = load_cart_items($pdo);
if ($items === []) {
    set_flash('error', 'Your cart is empty. Add products before checkout.');
    redirect('products.php');
}

$user = current_user($pdo);
$subtotal = cart_subtotal($items);

$shippingName = trim((string) ($user['full_name'] ?? ''));
$shippingPhone = trim((string) ($user['phone'] ?? ''));
$shippingAddress = trim((string) ($user['address'] ?? ''));
$notes = '';
$errors = [];

// Fetch active payment methods from database
$activeMethods = $pdo->query('SELECT * FROM payment_methods WHERE active = 1 ORDER BY sort_order ASC, id ASC')->fetchAll();
if (empty($activeMethods)) {
    $activeMethods = [
        [
            'id' => 1,
            'name' => 'Cash on Delivery',
            'code' => 'cod',
            'icon' => '💵',
            'instructions' => 'Pay cash when you receive your order at your doorstep.',
            'account_title' => '',
            'account_number' => '',
            'bank_name' => '',
            'branch_code' => '',
            'qr_image' => '',
        ]
    ];
}

$validCodes = array_column($activeMethods, 'code');
$defaultMethod = in_array('bank_transfer', $validCodes, true) ? 'bank_transfer' : ($validCodes[0] ?? 'cod');
$paymentMethod = $defaultMethod;
$transactionId = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforce_csrf();

    $shippingName = trim((string) ($_POST['shipping_name'] ?? ''));
    $shippingPhone = trim((string) ($_POST['shipping_phone'] ?? ''));
    $shippingAddress = trim((string) ($_POST['shipping_address'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));
    $paymentMethod = trim((string) ($_POST['payment_method'] ?? 'cod'));
    $transactionId = trim((string) ($_POST['transaction_id'] ?? ''));

    if (!in_array($paymentMethod, $validCodes, true)) {
        $paymentMethod = $validCodes[0] ?? 'cod';
    }

    if ($shippingName === '') {
        $errors[] = 'Shipping name is required.';
    }

    if ($shippingPhone === '') {
        $errors[] = 'Phone number is required.';
    }

    if ($shippingAddress === '') {
        $errors[] = 'Shipping address is required.';
    }

    if ($errors === []) {
        try {
            $pdo->beginTransaction();

            $paymentStatus = 'pending';

            $orderStmt = $pdo->prepare(
                'INSERT INTO orders (user_id, status, total_amount, payment_method, payment_status, transaction_id, shipping_name, shipping_phone, shipping_address, notes, created_at)
                 VALUES (:user_id, :status, :total_amount, :payment_method, :payment_status, :transaction_id, :shipping_name, :shipping_phone, :shipping_address, :notes, :created_at)'
            );

            $orderStmt->execute([
                ':user_id' => $user['id'],
                ':status' => 'Placed',
                ':total_amount' => $subtotal,
                ':payment_method' => $paymentMethod,
                ':payment_status' => $paymentStatus,
                ':transaction_id' => $transactionId !== '' ? $transactionId : null,
                ':shipping_name' => $shippingName,
                ':shipping_phone' => $shippingPhone,
                ':shipping_address' => $shippingAddress,
                ':notes' => $notes,
                ':created_at' => date('Y-m-d H:i:s'),
            ]);

            $orderId = (int) $pdo->lastInsertId();

            // Lock the product rows so two checkouts cannot oversell the same stock.
            $stockStmt = $pdo->prepare('SELECT id, name, stock FROM products WHERE id = ? FOR UPDATE');
            $stockUpdateStmt = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');

            foreach ($items as $item) {
                $stockStmt->execute([$item['id']]);
                $productRow = $stockStmt->fetch();
                $available = $productRow ? (int) $productRow['stock'] : 0;

                if (!$productRow || $available < $item['quantity']) {
                    throw new RuntimeException(
                        'Insufficient stock for ' . ($productRow['name'] ?? $item['name']) .
                        ' (' . $available . ' left).'
                    );
                }

                $stockUpdateStmt->execute([$item['quantity'], $item['id']]);
            }

            // Save order items WITH image_url snapshot
            $itemStmt = $pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, product_name, image_url, unit_price, quantity, line_total)
                 VALUES (:order_id, :product_id, :product_name, :image_url, :unit_price, :quantity, :line_total)'
            );

            foreach ($items as $item) {
                $itemStmt->execute([
                    ':order_id' => $orderId,
                    ':product_id' => $item['id'],
                    ':product_name' => $item['name'],
                    ':image_url' => $item['image_url'] ?? '',
                    ':unit_price' => $item['price'],
                    ':quantity' => $item['quantity'],
                    ':line_total' => $item['line_total'],
                ]);
            }

            $pdo->commit();

            clear_cart();

            // Find selected payment method object for custom message
            $selectedMethodObj = null;
            foreach ($activeMethods as $m) {
                if ($m['code'] === $paymentMethod) {
                    $selectedMethodObj = $m;
                    break;
                }
            }

            if ($paymentMethod === 'bank_transfer' && !empty($selectedMethodObj['account_number'])) {
                set_flash('success', 'Order #' . $orderId . ' placed successfully! Please transfer the payment to ' . ($selectedMethodObj['account_title'] ?: 'Resources Enterprises') . ' (IBAN: ' . $selectedMethodObj['account_number'] . ').');
            } elseif ($paymentMethod === 'jazzcash') {
                set_flash('success', 'Order #' . $orderId . ' placed successfully. Please complete your JazzCash/Raast payment using TILL ID or QR code.');
            } elseif ($paymentMethod === 'cod') {
                set_flash('success', 'Order #' . $orderId . ' placed successfully. Payment will be collected on delivery.');
            } else {
                set_flash('success', 'Order #' . $orderId . ' placed successfully via ' . ($selectedMethodObj['name'] ?? 'selected method') . '.');
            }
            redirect('orders.php');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $message = $exception->getMessage();
            if ($exception instanceof RuntimeException && str_starts_with($message, 'Insufficient stock')) {
                $errors[] = $message . ' Please adjust your cart quantity.';
            } else {
                $errors[] = 'Unable to place your order right now: ' . $message;
            }
        }
    }
}

$pageTitle = 'Checkout | ' . APP_NAME;
$pageDescription = 'Complete your order and submit shipping details.';

include __DIR__ . '/includes/header.php';
?>
<section class="page-hero fade">
  <div class="section-inner">
    <p class="eyebrow">Final Step</p>
    <h2>Checkout</h2>
    <p>Submit your shipping details and select payment method to complete your order.</p>
  </div>
</section>

<section class="cart-grid" style="padding-top: 40px;">
  <article class="panel fade">
    <h2 style="font-family: 'Playfair Display', serif; font-size: 2rem; margin-bottom: 25px;">Shipping & Payment</h2>

    <?php if ($errors !== []): ?>
      <div class="flash error" style="margin-bottom: 25px; padding: 15px 20px; background: #F8D7DA; color: #721C24; border-radius: 8px;">
        <?= h(implode(' ', $errors)) ?>
      </div>
    <?php endif; ?>

    <form method="post" action="checkout.php" id="checkoutForm">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <div>
          <label class="input-label" for="shipping_name">Full Name</label>
          <input class="input" id="shipping_name" name="shipping_name" type="text" value="<?= h($shippingName) ?>" placeholder="Receiver's name" required>
        </div>
        <div>
          <label class="input-label" for="shipping_phone">Phone Number</label>
          <input class="input" id="shipping_phone" name="shipping_phone" type="text" value="<?= h($shippingPhone) ?>" placeholder="Receiver's phone" required>
        </div>
      </div>

      <label class="input-label" for="shipping_address">Complete Shipping Address</label>
      <textarea class="input" id="shipping_address" name="shipping_address" rows="3" placeholder="Street, City, Postal Code" required><?= h($shippingAddress) ?></textarea>

      <label class="input-label" for="notes">Order Notes (optional)</label>
      <textarea class="input" id="notes" name="notes" rows="2" placeholder="Any special delivery instructions..."><?= h($notes) ?></textarea>

      <h3 style="font-family: 'Playfair Display', serif; font-size: 1.5rem; margin: 35px 0 20px; color: var(--brand);">Payment Method</h3>

      <!-- Dynamic Payment Options Grid -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 25px;">
        <?php foreach ($activeMethods as $m): ?>
          <label class="payment-option" style="cursor: pointer; display: block; padding: 20px; border: 2px solid var(--line); border-radius: 16px; transition: all 0.3s; background: var(--card);">
            <input type="radio" name="payment_method" value="<?= h($m['code']) ?>" <?= $paymentMethod === $m['code'] ? 'checked' : '' ?> style="margin-bottom: 12px; accent-color: var(--brand); transform: scale(1.2);">
            <div style="font-size: 2rem; margin-bottom: 8px;"><?= h($m['icon'] ?: '💳') ?></div>
            <div style="font-weight: 700; color: var(--brand); font-size: 1.05rem; margin-bottom: 5px;"><?= h($m['name']) ?></div>
            <div style="font-size: 0.82rem; color: var(--muted); font-weight: 500; line-height: 1.4;">
              <?php if (!empty($m['bank_name'])): ?>
                <?= h($m['bank_name']) ?>
              <?php elseif (!empty($m['account_title'])): ?>
                <?= h($m['account_title']) ?>
              <?php else: ?>
                <?= h($m['instructions'] ?: 'Fast & secure payment') ?>
              <?php endif; ?>
            </div>
          </label>
        <?php endforeach; ?>
      </div>

      <!-- Payment Details Container for each method -->
      <?php foreach ($activeMethods as $m): ?>
        <div id="details_<?= h($m['code']) ?>" class="method-details-panel" style="display: none; margin-bottom: 25px; border-radius: 16px; transition: all 0.3s;">
          
          <?php if (!empty($m['account_number']) || !empty($m['account_title'])): ?>
            <!-- Bank Transfer / Account Details Card -->
            <div style="padding: 28px; background: linear-gradient(135deg, #FFF9E6 0%, #FFF3CD 100%); border-radius: 16px; border: 2px solid #FFE082; box-shadow: 0 8px 24px rgba(255, 193, 7, 0.15);">
              <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid rgba(0,0,0,0.08);">
                <div style="display: flex; align-items: center; gap: 12px;">
                  <span style="font-size: 2.2rem;"><?= h($m['icon'] ?: '🏦') ?></span>
                  <div>
                    <h4 style="margin: 0; font-family: 'Playfair Display', serif; font-size: 1.35rem; color: var(--brand);"><?= h($m['name']) ?></h4>
                    <span style="font-size: 0.85rem; color: #795548; font-weight: 600;"><?= h($m['bank_name'] ?: 'Official Payment Account') ?></span>
                  </div>
                </div>
                <div style="background: var(--brand); color: #fff; padding: 6px 16px; border-radius: 30px; font-size: 0.8rem; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase;">
                  Verified Account
                </div>
              </div>

              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 20px;">
                <?php if (!empty($m['account_title'])): ?>
                  <div style="background: #fff; padding: 16px 20px; border-radius: 12px; border: 1px solid #FFE082;">
                    <div style="font-size: 0.8rem; text-transform: uppercase; color: var(--muted); font-weight: 700; letter-spacing: 0.05em; margin-bottom: 5px;">Account Title</div>
                    <div style="font-size: 1.15rem; font-weight: 800; color: var(--brand);"><?= h($m['account_title']) ?></div>
                  </div>
                <?php endif; ?>

                <?php if (!empty($m['account_number'])): ?>
                  <div style="background: #fff; padding: 16px 20px; border-radius: 12px; border: 1px solid #FFE082;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                      <span style="font-size: 0.8rem; text-transform: uppercase; color: var(--muted); font-weight: 700; letter-spacing: 0.05em;">IBAN / Account Number</span>
                      <button type="button" onclick="copyToClipboard('<?= h($m['account_number']) ?>', this)" style="background: #E8F5E9; border: 1px solid #A5D6A7; color: #2E7D32; padding: 3px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; cursor: pointer;">📋 Copy</button>
                    </div>
                    <div style="font-size: 1.15rem; font-weight: 800; color: #5D100B; font-family: monospace; letter-spacing: 1px; word-break: break-all;"><?= h($m['account_number']) ?></div>
                  </div>
                <?php endif; ?>
              </div>

              <?php if (!empty($m['bank_name']) || !empty($m['branch_code'])): ?>
                <div style="background: #fff; padding: 14px 20px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #FFE082; display: flex; gap: 30px; flex-wrap: wrap;">
                  <?php if (!empty($m['bank_name'])): ?>
                    <div>
                      <span style="font-size: 0.75rem; color: var(--muted); font-weight: 700; text-transform: uppercase;">Bank: </span>
                      <strong style="color: var(--brand);"><?= h($m['bank_name']) ?></strong>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($m['branch_code'])): ?>
                    <div>
                      <span style="font-size: 0.75rem; color: var(--muted); font-weight: 700; text-transform: uppercase;">Branch Code: </span>
                      <strong style="color: var(--brand);"><?= h($m['branch_code']) ?></strong>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endif; ?>

              <?php if (!empty($m['qr_image'])): ?>
                <div style="text-align: center; margin: 20px 0;">
                  <img src="<?= h(url($m['qr_image'])) ?>" alt="<?= h($m['name']) ?> QR" style="max-width: 220px; max-height: 220px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 4px solid #fff;">
                </div>
              <?php endif; ?>

              <?php if (!empty($m['instructions'])): ?>
                <div style="background: rgba(255,255,255,0.9); padding: 16px 20px; border-radius: 10px; border-left: 4px solid var(--brand); margin-bottom: 20px;">
                  <strong style="display: block; font-size: 0.85rem; color: var(--brand); margin-bottom: 4px; text-transform: uppercase;">Instructions:</strong>
                  <p style="margin: 0; font-size: 0.95rem; color: #444; line-height: 1.6;"><?= nl2br(h($m['instructions'])) ?></p>
                </div>
              <?php endif; ?>

              <!-- Reference field for customer -->
              <div style="background: #fff; padding: 18px 20px; border-radius: 12px; border: 1px solid #FFE082;">
                <label class="input-label" for="ref_<?= h($m['code']) ?>" style="margin-bottom: 6px;">
                  Transaction Reference / Sender Name / IBAN (Optional)
                </label>
                <input class="input transaction-id-input" type="text" id="ref_<?= h($m['code']) ?>" placeholder="e.g. TID12345678, Askari Bank transfer slip # or Sender Title" value="<?= h($transactionId) ?>">
                <small style="color: var(--muted); font-size: 0.78rem;">You can enter your transaction ID, slip number or bank transfer reference now or share it with support later.</small>
              </div>
            </div>

          <?php elseif ($m['code'] === 'cod'): ?>
            <!-- Cash on Delivery Notice -->
            <div style="padding: 24px; background: var(--bg-soft); border-radius: 16px; border: 2px dashed #D2ADA1; text-align: center;">
              <span style="font-size: 2.5rem; display: block; margin-bottom: 10px;">💵</span>
              <h4 style="font-family: 'Playfair Display', serif; font-size: 1.3rem; color: var(--brand); margin: 0 0 10px;">Cash on Delivery</h4>
              <p style="margin: 0 auto; max-width: 500px; color: var(--muted); font-size: 0.95rem; line-height: 1.6;">
                <?= h($m['instructions'] ?: 'Pay in cash when your parcel is delivered to your provided shipping address.') ?>
              </p>
            </div>

          <?php else: ?>
            <!-- Custom Payment Method -->
            <div style="padding: 24px; background: var(--card); border-radius: 16px; border: 2px solid var(--line);">
              <h4 style="font-family: 'Playfair Display', serif; font-size: 1.3rem; color: var(--brand); margin: 0 0 10px;"><?= h($m['name']) ?></h4>
              <?php if (!empty($m['instructions'])): ?>
                <p style="color: var(--muted); font-size: 0.95rem; line-height: 1.6; margin-bottom: 15px;"><?= nl2br(h($m['instructions'])) ?></p>
              <?php endif; ?>
              <?php if (!empty($m['qr_image'])): ?>
                <div style="text-align: center; margin: 15px 0;">
                  <img src="<?= h(url($m['qr_image'])) ?>" alt="QR" style="max-height: 200px; border-radius: 8px;">
                </div>
              <?php endif; ?>
              <div>
                <label class="input-label" style="margin-bottom: 6px;">Payment Reference / Transaction ID (Optional)</label>
                <input class="input transaction-id-input" type="text" placeholder="Enter Transaction ID or Reference Number" value="<?= h($transactionId) ?>">
              </div>
            </div>
          <?php endif; ?>

        </div>
      <?php endforeach; ?>

      <!-- Hidden actual transaction_id input that syncs from the visible one -->
      <input type="hidden" name="transaction_id" id="finalTransactionId" value="<?= h($transactionId) ?>">

      <div class="form-actions" style="margin-top: 40px; display: flex; gap: 20px;">
        <button type="submit" class="btn" style="flex: 1; padding: 18px; font-size: 1.05rem;">Place Order Now</button>
        <a href="cart.php" class="btn btn-outline" style="flex: 1; padding: 18px; text-align: center; font-size: 1.05rem; background: transparent; color: var(--brand); border: 2px solid var(--brand);">Back to Cart</a>
      </div>
    </form>
  </article>

  <aside class="cart-summary fade">
    <h2 style="font-family: 'Playfair Display', serif; font-size: 1.8rem; margin-bottom: 25px; border-bottom: 2px solid #D2ADA1; padding-bottom: 10px;">Order Summary</h2>
    <ul class="order-items" style="list-style: none; padding: 0; margin-bottom: 30px;">
      <?php foreach ($items as $item): ?>
        <li class="order-item" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--line);">
          <div style="display: flex; align-items: center; gap: 12px;">
            <?php if (!empty($item['image_url'])): ?>
              <img src="<?= h(url($item['image_url'])) ?>" alt="<?= h($item['name']) ?>" style="width: 44px; height: 44px; object-fit: cover; border-radius: 8px; border: 1px solid var(--line);">
            <?php endif; ?>
            <span style="font-weight: 600; color: var(--brand);">
              <?= h($item['name']) ?> <span style="color: var(--muted); font-weight: 500; margin-left: 6px;">x<?= h((string) $item['quantity']) ?></span>
            </span>
          </div>
          <span style="font-weight: 700; color: var(--brand);"><?= h(money((float) $item['line_total'])) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
    
    <div id="paymentSummary" style="padding: 15px 0; border-top: 1px dashed var(--line); margin-bottom: 15px;">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
        <span style="font-weight: 600; color: var(--muted);">Payment Method:</span>
        <span id="paymentMethodText" style="font-weight: 700; color: var(--brand);">Select Method</span>
      </div>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px; padding-top: 20px; border-top: 2px solid #5D100B;">
      <span style="font-size: 1.2rem; font-weight: 800; color: var(--brand);">Total</span>
      <span style="font-family: 'Playfair Display', serif; font-size: 2rem; color: #5D100B; font-weight: 800;"><?= h(money((float) $subtotal)) ?></span>
    </div>

    <div style="margin-top: 30px; padding: 20px; background: var(--bg-soft); border-radius: 12px; border: 1px dashed #D2ADA1; text-align: center;">
      <p style="font-size: 0.85rem; color: var(--muted); font-weight: 600;">🔒 Secure Checkout Guaranteed</p>
    </div>
  </aside>
</section>

<script>
(function() {
  const methodRadios = document.querySelectorAll('input[name="payment_method"]');
  const paymentMethodText = document.getElementById('paymentMethodText');
  const paymentOptions = document.querySelectorAll('.payment-option');
  const detailsPanels = document.querySelectorAll('.method-details-panel');
  const finalTransactionId = document.getElementById('finalTransactionId');
  const form = document.getElementById('checkoutForm');

  function updatePaymentDisplay() {
    const checkedRadio = document.querySelector('input[name="payment_method"]:checked');
    if (!checkedRadio) return;
    const selected = checkedRadio.value;

    paymentOptions.forEach(opt => {
      const radio = opt.querySelector('input[type="radio"]');
      if (radio.checked) {
        opt.style.borderColor = 'var(--brand)';
        opt.style.boxShadow = '0 0 0 3px rgba(93, 16, 11, 0.15)';
        opt.style.background = 'linear-gradient(135deg, var(--card) 0%, var(--bg-soft) 100%)';
        const labelText = opt.querySelector('div:nth-of-type(2)')?.textContent || selected;
        paymentMethodText.textContent = labelText;
      } else {
        opt.style.borderColor = 'var(--line)';
        opt.style.boxShadow = 'none';
        opt.style.background = 'var(--card)';
      }
    });

    detailsPanels.forEach(panel => {
      if (panel.id === 'details_' + selected) {
        panel.style.display = 'block';
        panel.style.animation = 'fadeIn 0.35s ease-out';
      } else {
        panel.style.display = 'none';
      }
    });
  }

  methodRadios.forEach(radio => {
    radio.addEventListener('change', updatePaymentDisplay);
  });

  // Sync visible transaction ID input to hidden field before submit
  form.addEventListener('submit', function() {
    const checkedRadio = document.querySelector('input[name="payment_method"]:checked');
    if (!checkedRadio) return;
    const activePanel = document.getElementById('details_' + checkedRadio.value);
    if (activePanel) {
      const input = activePanel.querySelector('.transaction-id-input');
      if (input) {
        finalTransactionId.value = input.value.trim();
      }
    }
  });

  updatePaymentDisplay();
})();

function copyToClipboard(text, btn) {
  navigator.clipboard.writeText(text).then(() => {
    const originalText = btn.innerHTML;
    btn.innerHTML = '✅ Copied!';
    btn.style.background = '#C8E6C9';
    setTimeout(() => {
      btn.innerHTML = originalText;
      btn.style.background = '#E8F5E9';
    }, 2000);
  }).catch(() => {
    alert('Copy failed, please copy manually: ' + text);
  });
}
</script>

<style>
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-8px); }
  to { opacity: 1; transform: translateY(0); }
}

.payment-option:hover {
  border-color: #D2ADA1 !important;
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(0,0,0,0.08) !important;
}

.payment-option {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

@media (max-width: 768px) {
  .cart-grid { grid-template-columns: 1fr; }
  .cart-grid > article > div:first-of-type { grid-template-columns: 1fr !important; }
}
</style>
<?php include __DIR__ . '/includes/footer.php'; ?>
