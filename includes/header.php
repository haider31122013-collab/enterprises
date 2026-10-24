<?php
if (!isset($pageTitle) || $pageTitle === '') {
    $pageTitle = APP_NAME;
}

if (!isset($pageDescription) || $pageDescription === '') {
    $pageDescription = 'Resources Enterprises ecommerce store for premium cutlery, footwear, salt artisans and ceramics. Export quality wholesale products for retail, hotels, and B2B businesses.';
}

if (!isset($pageKeywords) || $pageKeywords === '') {
    $pageKeywords = 'resources enterprises, premium cutlery, wholesale footwear, himalayan salt, ceramics, export quality, b2b supplier, pakistan export';
}

if (!isset($pageImage) || $pageImage === '') {
    $pageImage = url('logo/logo.png');
}

$currentUser = current_user($pdo);
$cartItemCount = cart_count();
$successFlash = get_flash('success');
$errorFlash = get_flash('error');

$pageUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($pageTitle) ?></title>
  <meta name="description" content="<?= h($pageDescription) ?>">
  <meta name="keywords" content="<?= h($pageKeywords) ?>">
  <meta name="author" content="Resources Enterprises">
  <meta name="robots" content="index, follow">

  <!-- Open Graph / Facebook -->
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= h($pageUrl) ?>">
  <meta property="og:title" content="<?= h($pageTitle) ?>">
  <meta property="og:description" content="<?= h($pageDescription) ?>">
  <meta property="og:image" content="<?= h($pageImage) ?>">
  <meta property="og:site_name" content="<?= h(APP_NAME) ?>">

  <!-- Twitter -->
  <meta property="twitter:card" content="summary_large_image">
  <meta property="twitter:url" content="<?= h($pageUrl) ?>">
  <meta property="twitter:title" content="<?= h($pageTitle) ?>">
  <meta property="twitter:description" content="<?= h($pageDescription) ?>">
  <meta property="twitter:image" content="<?= h($pageImage) ?>">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
  <link rel="icon" type="image/png" href="<?= url('logo/logo.png') ?>">

  <!-- Structured Data / JSON-LD -->
  <?php if (isset($product) && is_array($product)): ?>
  <script type="application/ld+json">
  {
    "@context": "https://schema.org/",
    "@type": "Product",
    "name": <?= json_encode((string)$product['name']) ?>,
    "image": [<?= json_encode(url((string)$product['image_url'])) ?>],
    "description": <?= json_encode((string)($product['short_description'] ?: $product['description'])) ?>,
    "sku": <?= json_encode((string)($product['sku'] ?? '')) ?>,
    "brand": {
      "@type": "Brand",
      "name": "Resources Enterprises"
    },
    "offers": {
      "@type": "Offer",
      "url": <?= json_encode($pageUrl) ?>,
      "priceCurrency": "USD",
      "price": <?= json_encode((string)$product['price']) ?>,
      "priceValidUntil": "<?= date('Y-12-31', strtotime('+1 year')) ?>",
      "availability": "https://schema.org/<?= (int)$product['stock'] > 0 ? 'InStock' : 'OutOfStock' ?>"
    }
  }
  </script>
  <?php endif; ?>
</head>
<body>
  <header class="top-nav">
    <div class="nav-left">
      <?php if (!is_admin_panel()): ?>
        <button class="hamburger-btn" id="luxuryMenuToggle" aria-label="Toggle menu">
          <span></span>
          <span></span>
          <span></span>
        </button>
      <?php endif; ?>
    </div>

    <div class="logo-wrap">
      <a href="<?= url('index.php') ?>" class="logo" aria-label="Resources Enterprises homepage">
        <img src="<?= url('logo/logo.png') ?>" alt="Resources Enterprises Logo" class="main-logo">
      </a>
    </div>

    <div class="nav-actions">
      <a href="<?= url('favorites.php') ?>" class="icon-btn" aria-label="Favorites">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l8.78-8.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
        </svg>
      </a>

      <a href="<?= url('cart.php') ?>" class="icon-btn cart-btn" aria-label="Open cart">
        <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="9" cy="20" r="1"></circle>
          <circle cx="18" cy="20" r="1"></circle>
          <path d="M3 4h2l2.6 11.2A2 2 0 0 0 9.55 17h8.94a2 2 0 0 0 1.95-1.57L22 7H7"></path>
        </svg>
        <span class="cart-count"><?= h($cartItemCount) ?></span>
      </a>

      <?php if ($currentUser): ?>
        <a href="<?= url('profile.php') ?>" class="icon-btn" aria-label="User profile">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 21a8 8 0 0 0-16 0"></path>
            <circle cx="12" cy="8" r="4"></circle>
          </svg>
        </a>
      <?php endif; ?>
    </div>
  </header>

  <!-- Luxury Side Menu -->
  <aside class="luxury-side-menu" id="luxurySideMenu">
    <div class="menu-inner">
      <div class="menu-header">
        <span class="menu-title">MENU</span>
        <button class="menu-close-btn" id="luxuryMenuClose">&times;</button>
      </div>

      <div class="menu-body">
        <nav class="side-nav">
          <a href="<?= url('index.php') ?>" class="menu-item main-link">
            <span class="item-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
            </span>
            <span>Home</span>
          </a>
          
          <a href="<?= url('about.php') ?>" class="menu-item main-link">
            <span class="item-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
            </span>
            <span>About</span>
          </a>

          <a href="<?= url('products.php') ?>" class="menu-item main-link">
            <span class="item-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"></path><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path><path d="m3.3 7 8.7 5 8.7-5"></path><path d="M12 22V12"></path></svg>
            </span>
            <span>Products</span>
          </a>

          <?php if ($currentUser): ?>
            <a href="<?= url('favorites.php') ?>" class="menu-item main-link">
              <span class="item-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l8.78-8.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
              </span>
              <span>Favorites</span>
            </a>
          <?php endif; ?>

          <div class="menu-dropdown-container">
            <button class="menu-item dropdown-toggle" data-target="shopDropdown">
              <span class="item-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"></path><path d="M3 6h18"></path><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
              </span>
              <span>Shop</span>
              <span class="arrow-icon">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-9"></path></svg>
              </span>
            </button>
            <div class="dropdown-content" id="shopDropdown">
              <a href="<?= url('products.php?category=Cutlery') ?>" class="dropdown-item">Cutlery</a>
              <a href="<?= url('products.php?category=Kitchenware') ?>" class="dropdown-item">Kitchenware</a>
              <a href="<?= url('products.php?category=Salt Artisans') ?>" class="dropdown-item">Salt Crafts</a>
              <a href="<?= url('products.php?category=Footwear') ?>" class="dropdown-item">Footwear</a>
              <a href="<?= url('products.php?category=Ceramics') ?>" class="dropdown-item">Value Ceramics</a>
              <a href="<?= url('products.php?category=Industrial') ?>" class="dropdown-item">Engineering Parts</a>
              <a href="<?= url('feasibility.php') ?>" class="dropdown-item">Project Feasibility</a>
            </div>
          </div>

          <div class="menu-dropdown-container">
            <button class="menu-item dropdown-toggle" data-target="accountDropdown">
              <span class="item-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
              </span>
              <span>Account</span>
              <span class="arrow-icon">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-9"></path></svg>
              </span>
            </button>
            <div class="dropdown-content" id="accountDropdown">
              <?php if ($currentUser): ?>
                <?php if (is_admin($pdo)): ?>
                  <a href="<?= url('admin/dashboard.php') ?>" class="dropdown-item" style="color: var(--premium); font-weight: 800;">Admin Panel</a>
                <?php endif; ?>
                <a href="<?= url('dashboard.php') ?>" class="dropdown-item">Dashboard</a>
                <a href="<?= url('orders.php') ?>" class="dropdown-item">My Orders</a>
                <a href="<?= url('profile.php') ?>" class="dropdown-item">Profile</a>
                <a href="<?= url('logout.php') ?>" class="dropdown-item">Logout</a>
              <?php else: ?>
                <a href="<?= url('login.php') ?>" class="dropdown-item">Login</a>
                <a href="<?= url('signup.php') ?>" class="dropdown-item">Create Account</a>
              <?php endif; ?>
            </div>
          </div>
        </nav>

        <div class="menu-section footer-section">
          <nav class="footer-nav">
            <a href="<?= url('contact.php') ?>">Contact Us</a>
            <a href="<?= url('privacy.php') ?>">Privacy Policy</a>
            <a href="<?= url('refund.php') ?>">Refund & Return</a>
          </nav>
        </div>
      </div>
    </div>
  </aside>

  <div id="luxuryMenuOverlay" class="luxury-menu-overlay" aria-hidden="true"></div>

  <!-- Custom Luxury Notification -->
  <div id="customNotification" class="custom-notification">
    <div class="notification-content">
      <span class="notification-icon"></span>
      <p class="notification-message"></p>
    </div>
    <div class="notification-progress"></div>
  </div>

  <?php if ($successFlash || $errorFlash): ?>
    <div class="flash-wrap">
      <?php if ($successFlash): ?>
        <p class="flash success"><?= h($successFlash) ?></p>
      <?php endif; ?>
      <?php if ($errorFlash): ?>
        <p class="flash error"><?= h($errorFlash) ?></p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <main>
