<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin($pdo);

$pageTitle = 'Manage Currencies | Admin';

// Handle Setting Default Active Currency with Today's Conversion Rate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'convert_and_set_default') {
    enforce_csrf();
    $targetId = (int)($_POST['target_currency_id'] ?? 0);
    $currentId = (int)($_POST['current_currency_id'] ?? 0);
    $exchangeRate = (float)($_POST['exchange_rate'] ?? 1.0);
    $rateDirection = trim((string)($_POST['rate_direction'] ?? '1_target_is_x_current'));
    $convertProducts = isset($_POST['convert_products']) && $_POST['convert_products'] === '1';

    $targetCurrStmt = $pdo->prepare('SELECT * FROM currencies WHERE id = ?');
    $targetCurrStmt->execute([$targetId]);
    $target = $targetCurrStmt->fetch();

    $currentCurrStmt = $pdo->prepare('SELECT * FROM currencies WHERE id = ?');
    $currentCurrStmt->execute([$currentId]);
    $current = $currentCurrStmt->fetch();

    if (!$target) {
        set_flash('error', 'Selected target currency was not found.');
        redirect('currencies.php');
    }

    if ($exchangeRate <= 0) {
        $exchangeRate = 1.0;
    }

    // Determine multiplier to convert catalog prices
    // If rateDirection == '1_target_is_x_current':
    // Example: Current=PKR, Target=USD. "1 USD = 280 PKR".
    // A 28,000 PKR product becomes 28,000 / 280 = 100 USD (multiplier = 1 / 280).
    // If Current=USD, Target=PKR. "1 USD = 280 PKR".
    // A 100 USD product becomes 100 * 280 = 28,000 PKR (multiplier = 280).
    $multiplier = 1.0;
    if ($rateDirection === '1_target_is_x_current') {
        $multiplier = 1.0 / $exchangeRate;
    } else {
        $multiplier = $exchangeRate;
    }

    $pdo->beginTransaction();

    $convertedCount = 0;
    if ($convertProducts && $multiplier > 0) {
        $targetDecimals = (int)($target['decimal_places'] ?? 2);
        // Round to target currency decimals (e.g. 0 for PKR/JPY or 2 for USD/EUR)
        $prodStmt = $pdo->prepare("UPDATE products SET price = ROUND(price * :multiplier, :decimals), updated_at = NOW()");
        $prodStmt->execute([
            ':multiplier' => $multiplier,
            ':decimals' => $targetDecimals
        ]);
        $convertedCount = $prodStmt->rowCount();
    }

    // Update active status
    $pdo->exec('UPDATE currencies SET is_default = 0');
    $updateStmt = $pdo->prepare('UPDATE currencies SET is_default = 1, active = 1, exchange_rate = :rate, updated_at = NOW() WHERE id = :id');
    $updateStmt->execute([
        ':rate' => $exchangeRate,
        ':id' => $targetId
    ]);

    $pdo->commit();

    $fromLabel = $current ? "{$current['name']} ({$current['code']})" : "previous currency";
    $toLabel = "{$target['name']} ({$target['code']})";

    $rateNote = $rateDirection === '1_target_is_x_current'
        ? "1 {$target['code']} = " . number_format($exchangeRate, 2) . " " . ($current['code'] ?? '')
        : "1 " . ($current['code'] ?? '') . " = " . number_format($exchangeRate, 4) . " {$target['code']}";

    $prodMsg = $convertProducts 
        ? " All $convertedCount products in your catalog were automatically converted using today's rate ($rateNote)."
        : " Currency symbol was updated without altering product price numbers.";

    set_flash('success', "Website currency successfully switched from $fromLabel to $toLabel!$prodMsg");
    redirect('currencies.php');
}

// Handle Delete
if (isset($_GET['delete'])) {
    enforce_csrf();
    $id = (int)$_GET['delete'];
    
    $check = $pdo->prepare('SELECT is_default, name FROM currencies WHERE id = ?');
    $check->execute([$id]);
    $curr = $check->fetch();
    if ($curr && (int)$curr['is_default'] === 1) {
        set_flash('error', "Cannot delete '{$curr['name']}' because it is currently the active website currency. Please switch to another currency first.");
    } elseif ($curr) {
        $pdo->prepare('DELETE FROM currencies WHERE id = ?')->execute([$id]);
        set_flash('success', "Currency '{$curr['name']}' deleted successfully.");
    }
    redirect('currencies.php');
}

// Handle Toggle Active
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $check = $pdo->prepare('SELECT is_default FROM currencies WHERE id = ?');
    $check->execute([$id]);
    $isDef = (int)$check->fetchColumn();

    if ($isDef === 1) {
        set_flash('error', 'Cannot disable the active website currency. Change the active currency first.');
    } else {
        $pdo->prepare('UPDATE currencies SET active = IF(active = 1, 0, 1), updated_at = NOW() WHERE id = ?')->execute([$id]);
        set_flash('success', 'Currency status updated.');
    }
    redirect('currencies.php');
}

// Handle Add / Edit Custom Currency
$editCurrency = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare('SELECT * FROM currencies WHERE id = ?');
    $stmt->execute([$id]);
    $editCurrency = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['add', 'update'], true)) {
    enforce_csrf();
    $action = $_POST['action'];

    $code = strtoupper(trim((string)($_POST['code'] ?? '')));
    $name = trim((string)($_POST['name'] ?? ''));
    $symbol = trim((string)($_POST['symbol'] ?? ''));
    $symbol_position = in_array($_POST['symbol_position'] ?? '', ['before', 'after'], true) ? $_POST['symbol_position'] : 'before';
    $decimal_places = max(0, min(4, (int)($_POST['decimal_places'] ?? 2)));
    $thousands_sep = (string)($_POST['thousands_sep'] ?? ',');
    $decimal_sep = (string)($_POST['decimal_sep'] ?? '.');
    $exchange_rate = (float)($_POST['exchange_rate'] ?? 1.0);
    $active = isset($_POST['active']) ? 1 : 0;
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $is_default = isset($_POST['is_default']) ? 1 : 0;

    if ($code === '' || $name === '' || $symbol === '') {
        set_flash('error', 'Currency code, name, and symbol are required.');
        redirect('currencies.php');
    }

    if ($action === 'add') {
        $stmtCheck = $pdo->prepare('SELECT id FROM currencies WHERE code = ?');
        $stmtCheck->execute([$code]);
        if ($stmtCheck->fetch()) {
            set_flash('error', "Currency code '$code' already exists.");
            redirect('currencies.php');
        }

        if ($is_default === 1) {
            $pdo->exec('UPDATE currencies SET is_default = 0');
        }

        $stmt = $pdo->prepare('INSERT INTO currencies 
            (code, name, symbol, symbol_position, decimal_places, thousands_sep, decimal_sep, exchange_rate, is_default, active, sort_order, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([
            $code, $name, $symbol, $symbol_position, $decimal_places, $thousands_sep, $decimal_sep, $exchange_rate, $is_default, $active, $sort_order
        ]);
        set_flash('success', "Currency '$name ($code)' added successfully.");
        redirect('currencies.php');
    } elseif ($action === 'update' && isset($_POST['id'])) {
        $currencyId = (int)$_POST['id'];

        if ($is_default === 1) {
            $pdo->exec('UPDATE currencies SET is_default = 0');
            $active = 1;
        }

        $stmt = $pdo->prepare('UPDATE currencies SET 
            code = ?, name = ?, symbol = ?, symbol_position = ?, decimal_places = ?, thousands_sep = ?, decimal_sep = ?, exchange_rate = ?, is_default = ?, active = ?, sort_order = ?, updated_at = NOW()
            WHERE id = ?');
        $stmt->execute([
            $code, $name, $symbol, $symbol_position, $decimal_places, $thousands_sep, $decimal_sep, $exchange_rate, $is_default, $active, $sort_order, $currencyId
        ]);
        set_flash('success', "Currency '$name ($code)' updated successfully.");
        redirect('currencies.php');
    }
}

// Fetch all currencies
$currencies = $pdo->query('SELECT * FROM currencies ORDER BY is_default DESC, sort_order ASC, name ASC')->fetchAll();

// Currently active default currency
$activeCurrency = null;
foreach ($currencies as $c) {
    if ((int)$c['is_default'] === 1) {
        $activeCurrency = $c;
        break;
    }
}
if (!$activeCurrency && !empty($currencies)) {
    $activeCurrency = $currencies[0];
}

// Get sample product for live preview
$sampleProd = $pdo->query('SELECT name, price FROM products LIMIT 1')->fetch();
$sampleProdName = $sampleProd['name'] ?? 'Sample Product';
$sampleProdPrice = (float)($sampleProd['price'] ?? 100.00);

include __DIR__ . '/../includes/header.php';
?>
<div class="admin-layout">
  <aside class="admin-sidebar">
    <nav class="admin-nav">
      <a href="dashboard.php" class="admin-nav-item">Dashboard</a>
      <a href="orders.php" class="admin-nav-item">Manage Orders</a>
      <a href="products.php" class="admin-nav-item">Manage Products</a>
      <a href="payment-methods.php" class="admin-nav-item">Payment Methods</a>
      <a href="currencies.php" class="admin-nav-item active">Currencies</a>
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
        <h1>Website Currencies</h1>
        <p style="color: var(--muted); margin-top: 5px; font-size: 0.95rem;">Switch store currency with real-time exchange rates, or customize currency symbols and formatting.</p>
      </div>
      <button onclick="toggleForm()" class="btn" id="toggleFormBtn">
        <?= $editCurrency ? 'Edit Currency' : '+ Add Custom Currency' ?>
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

    <!-- Active Currency Banner (Shows Current Currency) -->
    <div style="background: linear-gradient(135deg, var(--brand) 0%, #7A150D 100%); color: #fff; padding: 30px; border-radius: 16px; margin-bottom: 30px; box-shadow: var(--shadow); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
      <div>
        <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.15); padding: 5px 14px; border-radius: 20px; margin-bottom: 10px;">
          <span style="font-size: 1rem;">📍</span>
          <span style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--premium); font-weight: 800;">
            Currently Active Store Currency
          </span>
        </div>
        <h2 style="font-family: 'Playfair Display', serif; font-size: 2.3rem; margin: 0 0 10px; color: #fff;">
          <?= h($activeCurrency['name']) ?> (<?= h($activeCurrency['code']) ?>)
        </h2>
        <div style="display: flex; align-items: center; gap: 15px; font-size: 1rem; flex-wrap: wrap;">
          <span>Active Symbol: <strong style="color: var(--premium); font-size: 1.35rem;"><?= h($activeCurrency['symbol']) ?></strong></span>
          <span>•</span>
          <span>Sample Store Price: <strong style="color: var(--premium); font-family: monospace; font-size: 1.25rem;"><?= h(money($sampleProdPrice)) ?></strong></span>
        </div>
      </div>

      <!-- Quick Switch Selector -->
      <div style="background: rgba(255,255,255,0.12); padding: 20px 24px; border-radius: 14px; border: 1px solid rgba(255,255,255,0.25);">
        <label style="display: block; font-size: 0.8rem; text-transform: uppercase; color: var(--premium); font-weight: 800; margin-bottom: 8px;">
          🔄 Change Store Currency:
        </label>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
          <select id="quickCurrencySelect" style="padding: 11px 16px; border-radius: 8px; border: 1px solid #fff; font-size: 0.95rem; font-weight: 700; background: #fff; color: var(--brand); cursor: pointer; min-width: 220px;">
            <?php foreach ($currencies as $c): ?>
              <?php if ((int)$c['is_default'] !== 1): ?>
                <option value="<?= (int)$c['id'] ?>" data-code="<?= h($c['code']) ?>" data-name="<?= h($c['name']) ?>" data-symbol="<?= h($c['symbol']) ?>" data-decimals="<?= (int)$c['decimal_places'] ?>">
                  <?= h($c['name']) ?> (<?= h($c['code']) ?> - <?= h($c['symbol']) ?>)
                </option>
              <?php endif; ?>
            <?php endforeach; ?>
          </select>
          <button type="button" onclick="openConversionModalFromSelect()" class="btn" style="background: var(--premium); color: #333; padding: 11px 22px; font-weight: 800; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
            Switch Currency →
          </button>
        </div>
      </div>
    </div>

    <!-- Conversion & Exchange Rate Modal -->
    <div id="conversionModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 9999; justify-content: center; align-items: center; padding: 20px; backdrop-filter: blur(4px);">
      <div style="background: #fff; width: 100%; max-width: 620px; border-radius: 20px; box-shadow: 0 25px 60px rgba(0,0,0,0.3); overflow: hidden; animation: popIn 0.3s ease-out; border: 2px solid var(--brand);">
        
        <div style="background: linear-gradient(135deg, var(--brand) 0%, #7A150D 100%); color: #fff; padding: 22px 28px; display: flex; justify-content: space-between; align-items: center;">
          <div>
            <h3 style="margin: 0; font-family: 'Playfair Display', serif; font-size: 1.5rem; color: var(--premium);">Currency Conversion & Switch</h3>
            <p style="margin: 4px 0 0; font-size: 0.85rem; opacity: 0.9;">Set today's rate to convert store prices</p>
          </div>
          <button type="button" onclick="closeConversionModal()" style="background: none; border: none; font-size: 1.8rem; color: #fff; cursor: pointer;">&times;</button>
        </div>

        <form method="post" action="currencies.php" style="padding: 28px;">
          <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="action" value="convert_and_set_default">
          <input type="hidden" name="current_currency_id" value="<?= (int)$activeCurrency['id'] ?>">
          <input type="hidden" name="target_currency_id" id="modalTargetId" value="">

          <!-- From -> To Visual Flow -->
          <div style="display: grid; grid-template-columns: 1fr auto 1fr; gap: 15px; align-items: center; margin-bottom: 25px; background: var(--bg-soft); padding: 18px 22px; border-radius: 14px; border: 1px solid var(--line);">
            <div>
              <span style="font-size: 0.72rem; text-transform: uppercase; font-weight: 800; color: var(--muted); display: block; margin-bottom: 4px;">Current Currency</span>
              <div style="font-size: 1.15rem; font-weight: 800; color: var(--brand);">
                <?= h($activeCurrency['code']) ?> (<?= h($activeCurrency['symbol']) ?>)
              </div>
              <small style="color: var(--muted);"><?= h($activeCurrency['name']) ?></small>
            </div>

            <div style="font-size: 1.8rem; color: var(--brand);">➔</div>

            <div>
              <span style="font-size: 0.72rem; text-transform: uppercase; font-weight: 800; color: var(--muted); display: block; margin-bottom: 4px;">New Target Currency</span>
              <div id="modalTargetName" style="font-size: 1.15rem; font-weight: 800; color: #5D100B;">
                USD ($)
              </div>
              <small id="modalTargetSub" style="color: var(--muted);">US Dollar</small>
            </div>
          </div>

          <!-- Exchange Rate Input Box -->
          <div style="background: #FFF9E6; border: 2px solid #FFE082; padding: 20px; border-radius: 14px; margin-bottom: 22px;">
            <label class="input-label" style="color: #795548; font-size: 0.95rem; margin-bottom: 10px;">
              📊 Enter Today's Conversion Rate:
            </label>

            <!-- Direction Choice -->
            <div style="display: flex; gap: 15px; margin-bottom: 12px; font-size: 0.9rem;">
              <label style="cursor: pointer; display: flex; align-items: center; gap: 6px; font-weight: 700; color: #5D100B;">
                <input type="radio" name="rate_direction" value="1_target_is_x_current" checked onchange="updateLivePreview()" style="accent-color: var(--brand);">
                <span id="labelTargetToCurrent">1 USD = X PKR</span>
              </label>
              <label style="cursor: pointer; display: flex; align-items: center; gap: 6px; font-weight: 600; color: #795548;">
                <input type="radio" name="rate_direction" value="1_current_is_x_target" onchange="updateLivePreview()" style="accent-color: var(--brand);">
                <span id="labelCurrentToTarget">1 PKR = X USD</span>
              </label>
            </div>

            <div style="display: flex; align-items: center; gap: 10px;">
              <span id="ratePrefixLabel" style="font-weight: 800; font-size: 1.1rem; color: #5D100B;">1 USD =</span>
              <input type="number" step="any" name="exchange_rate" id="modalExchangeRate" value="280.00" oninput="updateLivePreview()" class="input" style="font-size: 1.25rem; font-weight: 800; font-family: monospace; border: 2px solid #FFB300;" required>
              <span id="rateSuffixLabel" style="font-weight: 800; font-size: 1.1rem; color: #5D100B;">PKR</span>
            </div>
          </div>

          <!-- Live Conversion Preview -->
          <div style="background: #F3F4F6; padding: 16px 20px; border-radius: 12px; margin-bottom: 22px; border-left: 4px solid var(--brand);">
            <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 800; color: var(--muted); display: block; margin-bottom: 4px;">
              Live Price Conversion Preview
            </span>
            <div id="livePreviewCalculation" style="font-size: 1rem; color: var(--brand); font-weight: 700; line-height: 1.5;">
              A product currently priced at <strong>Rs. <?= number_format($sampleProdPrice, 0) ?></strong> will become <strong style="color: #059669;">$<?= number_format($sampleProdPrice / 280, 2) ?></strong>
            </div>
          </div>

          <!-- Checkbox to convert catalog prices -->
          <div style="margin-bottom: 25px;">
            <label style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer;">
              <input type="checkbox" name="convert_products" value="1" checked style="margin-top: 3px; transform: scale(1.3); accent-color: var(--brand);">
              <div>
                <strong style="color: var(--brand); font-size: 0.95rem; display: block;">Convert all product prices in catalog automatically</strong>
                <span style="color: var(--muted); font-size: 0.8rem; line-height: 1.4; display: block; margin-top: 2px;">
                  All product prices in the database will be multiplied by this conversion rate. (Uncheck if product prices were already entered in the target currency).
                </span>
              </div>
            </label>
          </div>

          <!-- Buttons -->
          <div style="display: flex; gap: 12px; justify-content: flex-end;">
            <button type="button" onclick="closeConversionModal()" class="btn btn-outline" style="background: transparent; color: var(--brand); border: 2px solid var(--brand);">
              Cancel
            </button>
            <button type="submit" class="btn" style="background: #059669; color: #fff; font-weight: 800; padding: 12px 24px;">
              Confirm & Convert Currency
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Add / Edit Currency Form Container -->
    <div id="currencyFormContainer" style="display: <?= $editCurrency ? 'block' : 'none' ?>; background: var(--card); padding: 30px; border-radius: 16px; box-shadow: var(--shadow); margin-bottom: 35px; border: 2px solid var(--line);">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 1px solid var(--line); padding-bottom: 15px;">
        <h2 style="font-family: 'Playfair Display', serif; font-size: 1.6rem; color: var(--brand); margin: 0;">
          <?= $editCurrency ? 'Edit Currency: ' . h($editCurrency['name']) : 'Add New Currency' ?>
        </h2>
        <button type="button" onclick="toggleForm(false)" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--muted);">&times;</button>
      </div>

      <form method="post" action="currencies.php">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="<?= $editCurrency ? 'update' : 'add' ?>">
        <?php if ($editCurrency): ?>
          <input type="hidden" name="id" value="<?= (int)$editCurrency['id'] ?>">
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 2fr 1fr; gap: 20px; margin-bottom: 20px;">
          <div>
            <label class="input-label" for="cur_code">ISO Code *</label>
            <input class="input" type="text" id="cur_code" name="code" value="<?= h($editCurrency['code'] ?? '') ?>" placeholder="e.g. PKR, USD, EUR, AED" maxlength="10" required>
            <small style="color: var(--muted); font-size: 0.75rem;">3-letter currency code</small>
          </div>
          <div>
            <label class="input-label" for="cur_name">Currency Name *</label>
            <input class="input" type="text" id="cur_name" name="name" value="<?= h($editCurrency['name'] ?? '') ?>" placeholder="e.g. Pakistani Rupee, US Dollar, Euro" required>
          </div>
          <div>
            <label class="input-label" for="cur_symbol">Symbol *</label>
            <input class="input" type="text" id="cur_symbol" name="symbol" value="<?= h($editCurrency['symbol'] ?? 'Rs. ') ?>" placeholder="e.g. Rs., $, €, £, AED" required>
          </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 20px; margin-bottom: 25px;">
          <div>
            <label class="input-label" for="cur_pos">Symbol Position</label>
            <select class="input" id="cur_pos" name="symbol_position">
              <option value="before" <?= ($editCurrency['symbol_position'] ?? 'before') === 'before' ? 'selected' : '' ?>>Before amount (e.g. Rs. 100)</option>
              <option value="after" <?= ($editCurrency['symbol_position'] ?? '') === 'after' ? 'selected' : '' ?>>After amount (e.g. 100 Rs.)</option>
            </select>
          </div>
          <div>
            <label class="input-label" for="cur_dec">Decimal Places</label>
            <select class="input" id="cur_dec" name="decimal_places">
              <option value="0" <?= (isset($editCurrency) && (int)$editCurrency['decimal_places'] === 0) ? 'selected' : '' ?>>0 (e.g. Rs. 1,500)</option>
              <option value="2" <?= (!isset($editCurrency) || (int)($editCurrency['decimal_places'] ?? 2) === 2) ? 'selected' : '' ?>>2 (e.g. Rs. 1,500.00)</option>
              <option value="3" <?= (isset($editCurrency) && (int)$editCurrency['decimal_places'] === 3) ? 'selected' : '' ?>>3 (e.g. KWD 1.500)</option>
            </select>
          </div>
          <div>
            <label class="input-label" for="cur_thou">Thousands Separator</label>
            <input class="input" type="text" id="cur_thou" name="thousands_sep" value="<?= h($editCurrency['thousands_sep'] ?? ',') ?>" maxlength="5">
          </div>
          <div>
            <label class="input-label" for="cur_dec_sep">Decimal Separator</label>
            <input class="input" type="text" id="cur_dec_sep" name="decimal_sep" value="<?= h($editCurrency['decimal_sep'] ?? '.') ?>" maxlength="5">
          </div>
        </div>

        <div style="display: flex; gap: 30px; margin-bottom: 25px; align-items: center; background: var(--bg-soft); padding: 16px 20px; border-radius: 10px;">
          <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 700; color: var(--brand);">
            <input type="checkbox" name="is_default" value="1" <?= (!empty($editCurrency['is_default'])) ? 'checked' : '' ?> style="transform: scale(1.3); accent-color: var(--brand);">
            <span>Set as Active Website Currency Immediately</span>
          </label>
          <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 600; color: #555;">
            <input type="checkbox" name="active" value="1" <?= (!isset($editCurrency) || ($editCurrency['active'] ?? 1) == 1) ? 'checked' : '' ?> style="transform: scale(1.3); accent-color: var(--brand);">
            <span>Enabled</span>
          </label>
        </div>

        <div style="display: flex; gap: 15px;">
          <button type="submit" class="btn"><?= $editCurrency ? 'Save Currency' : 'Create Currency' ?></button>
          <?php if ($editCurrency): ?>
            <a href="currencies.php" class="btn btn-outline" style="background: transparent; color: var(--brand); border: 2px solid var(--brand);">Cancel Edit</a>
          <?php else: ?>
            <button type="button" onclick="toggleForm(false)" class="btn btn-outline" style="background: transparent; color: var(--brand); border: 2px solid var(--brand);">Cancel</button>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- All Currencies Table -->
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Status</th>
            <th>Code</th>
            <th>Currency Name</th>
            <th>Symbol</th>
            <th>Sample Price</th>
            <th>Decimals</th>
            <th>Position</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($currencies as $c): ?>
            <?php
              $isDefault = (int)$c['is_default'] === 1;
              $isActive = (int)$c['active'] === 1;
              $dec = (int)$c['decimal_places'];
              $previewAmount = number_format($sampleProdPrice, $dec, $c['decimal_sep'] ?: '.', $c['thousands_sep'] ?: ',');
              $sampleFormatted = $c['symbol_position'] === 'after' ? ($previewAmount . ' ' . $c['symbol']) : ($c['symbol'] . $previewAmount);
            ?>
            <tr style="<?= $isDefault ? 'background: rgba(255, 212, 0, 0.08); font-weight: 600;' : '' ?>">
              <td>
                <?php if ($isDefault): ?>
                  <span class="badge paid" style="background: #2E7D32; color: #fff; padding: 6px 14px; font-weight: 800; font-size: 0.75rem;">
                    ★ Active on Store
                  </span>
                <?php elseif ($isActive): ?>
                  <span class="badge" style="background: #E8F5E9; color: #2E7D32; font-size: 0.72rem;">Available</span>
                <?php else: ?>
                  <span class="badge" style="background: #eee; color: #888; font-size: 0.72rem;">Disabled</span>
                <?php endif; ?>
              </td>
              <td>
                <code style="font-weight: 800; font-size: 0.95rem; color: #5D100B; background: #fff; padding: 4px 8px; border-radius: 6px; border: 1px solid var(--line);">
                  <?= h($c['code']) ?>
                </code>
              </td>
              <td>
                <strong style="color: var(--brand); font-size: 1.05rem;"><?= h($c['name']) ?></strong>
              </td>
              <td style="font-size: 1.3rem; font-weight: 800; color: #5D100B;">
                <?= h($c['symbol']) ?>
              </td>
              <td>
                <span style="font-family: monospace; font-size: 1.05rem; background: #FFF9E6; border: 1px solid #FFE082; padding: 3px 10px; border-radius: 6px; color: #333; font-weight: 700;">
                  <?= h($sampleFormatted) ?>
                </span>
              </td>
              <td style="color: var(--muted);"><?= (int)$c['decimal_places'] ?> decimals</td>
              <td style="font-size: 0.85rem; color: var(--muted); text-transform: capitalize;"><?= h($c['symbol_position']) ?> amount</td>
              <td>
                <div style="display: flex; gap: 8px; align-items: center;">
                  <?php if (!$isDefault): ?>
                    <button type="button" onclick="openConversionModal(<?= (int)$c['id'] ?>, '<?= h(addslashes($c['code'])) ?>', '<?= h(addslashes($c['name'])) ?>', '<?= h(addslashes($c['symbol'])) ?>', <?= (int)$c['decimal_places'] ?>)" class="btn small-btn" style="background: #2E7D32; color: #fff; padding: 6px 12px; font-size: 0.78rem; font-weight: 700;">
                      Set as Active
                    </button>
                  <?php endif; ?>

                  <a href="currencies.php?edit=<?= (int)$c['id'] ?>" class="btn small-btn" style="padding: 6px 12px; font-size: 0.78rem; text-decoration: none;">
                    Edit
                  </a>

                  <?php if (!$isDefault): ?>
                    <form method="get" action="currencies.php" onsubmit="return confirm('Delete currency <?= h(addslashes($c['name'])) ?>?');" style="display: inline;">
                      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                      <input type="hidden" name="delete" value="<?= (int)$c['id'] ?>">
                      <button type="submit" class="btn small-btn" style="background: #DC2626; color: #fff; padding: 6px 10px; font-size: 0.78rem;">
                        Delete
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Explanation Box -->
    <div style="margin-top: 35px; background: var(--card); padding: 25px; border-radius: 16px; box-shadow: var(--shadow);">
      <h3 style="font-family: 'Playfair Display', serif; font-size: 1.3rem; color: var(--brand); margin-bottom: 12px;">
        💡 How Currency Conversion Works
      </h3>
      <p style="color: var(--muted); line-height: 1.6; margin: 0 0 10px;">
        1. <strong>Current Currency Shown:</strong> The system always displays your currently active store currency (e.g. <strong><?= h($activeCurrency['name']) ?> - <?= h($activeCurrency['code']) ?></strong>).
      </p>
      <p style="color: var(--muted); line-height: 1.6; margin: 0 0 10px;">
        2. <strong>Today's Exchange Rate:</strong> When switching currencies (e.g. from PKR to USD or vice versa), the system prompts you to enter today's conversion rate (e.g. <code>1 USD = 280 PKR</code>).
      </p>
      <p style="color: var(--muted); line-height: 1.6; margin: 0;">
        3. <strong>Catalog Price Conversion:</strong> If you leave the conversion checkbox checked, all existing product prices in your catalog are converted using this rate. Historical placed orders remain untouched so transaction records remain accurate.
      </p>
    </div>
  </main>
</div>

<script>
const currentCurrencyCode = <?= json_encode($activeCurrency['code']) ?>;
const currentCurrencySymbol = <?= json_encode($activeCurrency['symbol']) ?>;
const samplePrice = <?= json_encode($sampleProdPrice) ?>;

let targetData = {
  id: 0,
  code: 'USD',
  name: 'US Dollar',
  symbol: '$',
  decimals: 2
};

function openConversionModal(targetId, targetCode, targetName, targetSymbol, targetDecimals) {
  targetData = {
    id: targetId,
    code: targetCode,
    name: targetName,
    symbol: targetSymbol,
    decimals: targetDecimals
  };

  document.getElementById('modalTargetId').value = targetId;
  document.getElementById('modalTargetName').textContent = targetCode + ' (' + targetSymbol + ')';
  document.getElementById('modalTargetSub').textContent = targetName;

  document.getElementById('labelTargetToCurrent').textContent = '1 ' + targetCode + ' = X ' + currentCurrencyCode;
  document.getElementById('labelCurrentToTarget').textContent = '1 ' + currentCurrencyCode + ' = X ' + targetCode;

  // Set default typical exchange rate suggestion and best direction
  let defaultRate = 1.0;
  let defaultDir = '1_target_is_x_current';

  if (currentCurrencyCode === 'PKR' && targetCode === 'USD') {
    defaultRate = 280.00;
    defaultDir = '1_target_is_x_current'; // 1 USD = 280 PKR
  } else if (currentCurrencyCode === 'USD' && targetCode === 'PKR') {
    defaultRate = 280.00;
    defaultDir = '1_current_is_x_target'; // 1 USD = 280 PKR
  } else if (currentCurrencyCode === 'PKR' && targetCode === 'AED') {
    defaultRate = 76.00;
    defaultDir = '1_target_is_x_current'; // 1 AED = 76 PKR
  } else if (currentCurrencyCode === 'AED' && targetCode === 'PKR') {
    defaultRate = 76.00;
    defaultDir = '1_current_is_x_target';
  } else if (currentCurrencyCode === 'PKR' && targetCode === 'SAR') {
    defaultRate = 74.00;
    defaultDir = '1_target_is_x_current';
  } else if (currentCurrencyCode === 'SAR' && targetCode === 'PKR') {
    defaultRate = 74.00;
    defaultDir = '1_current_is_x_target';
  } else if (currentCurrencyCode === 'PKR' && targetCode === 'EUR') {
    defaultRate = 300.00;
    defaultDir = '1_target_is_x_current';
  } else if (currentCurrencyCode === 'EUR' && targetCode === 'PKR') {
    defaultRate = 300.00;
    defaultDir = '1_current_is_x_target';
  } else if (currentCurrencyCode === 'PKR' && targetCode === 'GBP') {
    defaultRate = 360.00;
    defaultDir = '1_target_is_x_current';
  } else if (currentCurrencyCode === 'GBP' && targetCode === 'PKR') {
    defaultRate = 360.00;
    defaultDir = '1_current_is_x_target';
  } else if (currentCurrencyCode === 'USD' && targetCode === 'EUR') {
    defaultRate = 0.92;
    defaultDir = '1_current_is_x_target';
  }

  const radio = document.querySelector('input[name="rate_direction"][value="' + defaultDir + '"]');
  if (radio) radio.checked = true;

  document.getElementById('modalExchangeRate').value = defaultRate;

  updateLivePreview();

  const modal = document.getElementById('conversionModal');
  modal.style.display = 'flex';
}

function openConversionModalFromSelect() {
  const sel = document.getElementById('quickCurrencySelect');
  const opt = sel.options[sel.selectedIndex];
  if (!opt) return;

  const id = parseInt(opt.value);
  const code = opt.getAttribute('data-code');
  const name = opt.getAttribute('data-name');
  const symbol = opt.getAttribute('data-symbol');
  const decimals = parseInt(opt.getAttribute('data-decimals') || '2');

  openConversionModal(id, code, name, symbol, decimals);
}

function closeConversionModal() {
  document.getElementById('conversionModal').style.display = 'none';
}

function updateLivePreview() {
  const direction = document.querySelector('input[name="rate_direction"]:checked').value;
  const rate = parseFloat(document.getElementById('modalExchangeRate').value) || 1.0;

  if (direction === '1_target_is_x_current') {
    document.getElementById('ratePrefixLabel').textContent = '1 ' + targetData.code + ' =';
    document.getElementById('rateSuffixLabel').textContent = currentCurrencyCode;
  } else {
    document.getElementById('ratePrefixLabel').textContent = '1 ' + currentCurrencyCode + ' =';
    document.getElementById('rateSuffixLabel').textContent = targetData.code;
  }

  // Calculate new sample price
  let newPrice = 0;
  if (direction === '1_target_is_x_current') {
    newPrice = samplePrice / (rate > 0 ? rate : 1);
  } else {
    newPrice = samplePrice * rate;
  }

  const dec = targetData.decimals;
  const formattedNew = newPrice.toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec });
  const formattedOld = samplePrice.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });

  document.getElementById('livePreviewCalculation').innerHTML =
    `A product currently priced at <strong>${currentCurrencySymbol} ${formattedOld}</strong> will become <strong style="color: #059669; font-size: 1.15rem;">${targetData.symbol} ${formattedNew}</strong>`;
}

function toggleForm(show = true) {
  const container = document.getElementById('currencyFormContainer');
  if (show === false || container.style.display === 'block') {
    container.style.display = 'none';
  } else {
    container.style.display = 'block';
    container.scrollIntoView({ behavior: 'smooth' });
  }
}
</script>

<style>
@keyframes popIn {
  from { opacity: 0; transform: scale(0.92); }
  to { opacity: 1; transform: scale(1); }
}

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
