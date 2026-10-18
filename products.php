<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Products | ' . APP_NAME;
$pageDescription = 'Browse all products from Resource Enterprises ecommerce store.';

$search = trim((string) ($_GET['q'] ?? ''));
$category = trim((string) ($_GET['category'] ?? ''));
$subcategory = trim((string) ($_GET['subcategory'] ?? ''));

$all_subcategories = [
    'Cutlery' => ['Swords', 'Knives', 'Daggers', 'Hunting Knives', 'Kitchen Knives', 'Folding Knives', 'Custom/Handmade Blades', 'Knife Sets', 'Accessories (Sheaths, sharpeners)'],
    'Kitchenware' => ['Cookware (Pots, pans)', 'Bakeware', 'Utensils (Spatulas, ladles)', 'Storage Containers', 'Dinner Sets', 'Tea & Coffee Ware', 'Serving Items', 'Kitchen Tools (Peelers, graters)'],
    'Salt Crafts' => ['Salt Lamps', 'Salt Candle Holders', 'Cooking Slabs/Plates', 'Salt Tiles & Bricks', 'Decorative Items', 'Bath & Wellness (Salt scrubs, bath salts)', 'Custom Carved Items'],
    'Footwear' => ['Sneakers', 'Joggers', 'Casual Shoes', 'Formal Shoes', 'Sandals & Slippers', 'Sports Shoes', 'Kids Footwear', 'Custom Designs'],
    'Value Ceramics' => ['Dinnerware Sets', 'Mugs & Cups', 'Plates & Bowls', 'Decorative Ceramics', 'Planters', 'Handmade/Artisan Pieces', 'Hotel & Bulk Supply'],
    'Engineering Parts' => ['Automotive Parts', 'Machinery Components', 'Precision Parts', 'Fasteners (Bolts, nuts)', 'Metal Fabrication Items', 'Custom Manufacturing', 'Industrial Tools', 'Export Quality Parts'],
    'Project Feasibility' => ['Business Feasibility Reports', 'Market Research', 'Cost Analysis', 'Financial Projections', 'Industrial Project Planning', 'Import/Export Consultancy', 'Startup Guidance', 'Custom Feasibility Reports']
];

$categories = array_keys($all_subcategories);

$sql = 'SELECT id, name, slug, category, subcategory, description, price, stock, image_url FROM products WHERE active = 1';
$params = [];

if ($search !== '') {
    $sql .= ' AND (name LIKE :search OR description LIKE :search OR category LIKE :search OR subcategory LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}

if ($category !== '') {
    $sql .= ' AND category = :category';
    $params[':category'] = $category;
}

if ($subcategory !== '') {
    $sql .= ' AND subcategory = :subcategory';
    $params[':subcategory'] = $subcategory;
}

$sql .= ' ORDER BY id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<section class="page-hero fade">
  <div class="section-inner">
    <p class="eyebrow">Catalog</p>
    <h2>Products</h2>
    <p>Choose from our full ecommerce collection. Add products to cart and place your order directly.</p>
  </div>
</section>

<div class="filters fade">
  <form class="filter-form" method="get" action="products.php" id="filterForm">
    <div class="filter-main">
      <input class="input" type="text" name="q" value="<?= h($search) ?>" placeholder="Search products...">
      
      <select class="select" name="category" onchange="document.getElementById('filterForm').submit()">
        <option value="">All Categories</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= h($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= h($cat) ?></option>
        <?php endforeach; ?>
      </select>

      <?php if ($category !== '' && isset($all_subcategories[$category])): ?>
        <select class="select" name="subcategory" onchange="document.getElementById('filterForm').submit()">
          <option value="">All Subcategories</option>
          <?php foreach ($all_subcategories[$category] as $sub): ?>
            <option value="<?= h($sub) ?>" <?= $subcategory === $sub ? 'selected' : '' ?>><?= h($sub) ?></option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>

      <button class="btn" type="submit">Search</button>
    </div>
  </form>
</div>

<section class="section" style="padding-top: 28px;">
  <div class="section-inner">
    <?php if ($products === []): ?>
      <article class="empty-state fade">
        <div style="font-size: 4rem; margin-bottom: 20px; opacity: 0.2;">🔍</div>
        <h3 style="font-family: 'Playfair Display', serif; font-size: 2rem; color: var(--brand); margin-bottom: 10px;">No products found</h3>
        <p class="muted">Try changing your search or category filter.</p>
        <a href="products.php" class="btn" style="margin-top: 20px;">Clear All Filters</a>
      </article>
    <?php else: ?>
      <div class="product-grid">
        <?php foreach ($products as $product): ?>
          <article class="product-card fade">
            <figure>
              <img src="<?= h($product['image_url']) ?>" alt="<?= h($product['name']) ?>">
              <div class="icons-overlay">
                <a href="product.php?slug=<?= urlencode($product['slug']) ?>" class="icon-action view-icon" title="View product">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                </a>
                <button class="icon-action heart-icon" data-id="<?= h((string) $product['id']) ?>" title="Add to favorites">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l8.78-8.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                  </svg>
                </button>
              </div>
            </figure>
            <div class="product-card-content">
              <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px;">
                <p class="product-category"><?= h($product['category']) ?></p>
                <?php if (!empty($product['subcategory'])): ?>
                  <span style="font-size: 0.6rem; color: var(--muted); opacity: 0.6; text-transform: uppercase; letter-spacing: 0.1em;">• <?= h($product['subcategory']) ?></span>
                <?php endif; ?>
              </div>
              <h3><?= h($product['name']) ?></h3>
              <p class="price" style="margin-bottom: 15px; color: #5D100B;"><?= h(money((float) $product['price'])) ?></p>
              <p class="helper-text" style="font-size: 0.85rem; line-height: 1.5; margin-bottom: 15px;"><?= h(mb_strimwidth((string) $product['description'], 0, 100, "...")) ?></p>
            </div>
            <form method="post" action="cart.php" class="bucket-form">
              <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="add">
              <input type="hidden" name="product_id" value="<?= h((string) $product['id']) ?>">
              <input type="hidden" name="quantity" value="1">
              <button type="submit" class="product-bucket">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"></path>
                  <line x1="3" y1="6" x2="21" y2="6"></line>
                  <path d="M16 10a4 4 0 0 1-8 0"></path>
                </svg>
                Add to Bucket
              </button>
            </form>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
