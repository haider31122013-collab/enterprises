<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$product = null;

if ($slug !== '') {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE active = 1 AND slug = :slug LIMIT 1');
    $stmt->execute([':slug' => $slug]);
    $product = $stmt->fetch();
}

if (!$product && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE active = 1 AND id = :id LIMIT 1');
    $stmt->execute([':id' => (int) $_GET['id']]);
    $product = $stmt->fetch();
}

if (!$product) {
    $pageTitle = 'Product Not Found | ' . APP_NAME;
    include __DIR__ . '/includes/header.php';
    ?>
    <section class="section">
      <div class="section-inner empty-state fade">
        <h2 style="margin-bottom: 8px;">Product Not Found</h2>
        <p class="muted" style="margin-bottom: 16px;">The product you requested is not available.</p>
        <a class="btn" href="products.php">Back To Products</a>
      </div>
    </section>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = !empty($product['meta_title']) ? $product['meta_title'] : ($product['name'] . ' | ' . $product['category'] . ' | ' . APP_NAME);
$pageDescription = !empty($product['meta_description']) ? $product['meta_description'] : ($product['short_description'] ?: substr(strip_tags((string)$product['description']), 0, 160));
$pageKeywords = !empty($product['meta_keywords']) ? $product['meta_keywords'] : '';
$pageImage = url((string)$product['image_url']);

// Fetch additional images
$imagesStmt = $pdo->prepare('SELECT image_url FROM product_images WHERE product_id = :product_id ORDER BY id ASC');
$imagesStmt->execute([':product_id' => $product['id']]);
$additionalImages = $imagesStmt->fetchAll(PDO::FETCH_COLUMN);
$allImages = array_merge([$product['image_url']], $additionalImages);

$relatedStmt = $pdo->prepare(
    'SELECT id, name, slug, price, image_url FROM products WHERE active = 1 AND category = :category AND id != :id ORDER BY id DESC LIMIT 4'
);
$relatedStmt->execute([
    ':category' => $product['category'],
    ':id' => $product['id'],
]);
$relatedProducts = $relatedStmt->fetchAll();

// Review Submission Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_review') {
    if (!is_logged_in()) {
        set_flash('error', 'Please login to submit a review.');
    } else {
        $rating = (int) ($_POST['rating'] ?? 5);
        $comment = trim((string) ($_POST['comment'] ?? ''));
        $productId = (int) ($product['id']);
        $userId = (int) ($currentUser['id'] ?? current_user_id());

        if ($comment === '') {
            set_flash('error', 'Review comment is required.');
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO reviews (product_id, user_id, rating, comment, created_at) VALUES (:product_id, :user_id, :rating, :comment, NOW())');
                $stmt->execute([
                    ':product_id' => $productId,
                    ':user_id' => $userId,
                    ':rating' => $rating,
                    ':comment' => $comment
                ]);
                set_flash('success', 'Thank you for your review!');
                header("Location: product.php?slug=" . urlencode($product['slug']));
                exit;
            } catch (PDOException $e) {
                set_flash('error', 'Database error: Could not save review. Please ensure the reviews table exists.');
            }
        }
    }
}

// Fetch Reviews
$reviews = [];
try {
    $reviewsStmt = $pdo->prepare('SELECT r.*, u.full_name FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = :product_id ORDER BY r.created_at DESC');
    $reviewsStmt->execute([':product_id' => $product['id']]);
    $reviews = $reviewsStmt->fetchAll();
} catch (PDOException $e) {
    // Gracefully handle missing table
    $reviews = [];
}

include __DIR__ . '/includes/header.php';
?>
<section class="product-detail">
  <div class="section-inner product-detail-grid fade">
    <div class="product-detail-media">
      <div class="slider-container">
        <div class="slider-track" id="productSlider">
          <?php foreach ($allImages as $index => $imgUrl): ?>
            <div class="slide <?= $index === 0 ? 'active' : '' ?>">
              <img src="<?= url($imgUrl) ?>" alt="<?= h($product['name']) ?> - Image <?= $index + 1 ?>">
            </div>
          <?php endforeach; ?>
        </div>
        
        <?php if (count($allImages) > 1): ?>
          <div class="slider-counter" id="sliderCounter">1 / <?= count($allImages) ?></div>
          <button class="slider-btn prev" onclick="moveSlide(-1)">&lsaquo;</button>
          <button class="slider-btn next" onclick="moveSlide(1)">&rsaquo;</button>
          
          <div class="slider-dots">
            <?php foreach ($allImages as $index => $imgUrl): ?>
              <span class="dot <?= $index === 0 ? 'active' : '' ?>" onclick="setSlide(<?= $index ?>)"></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <?php if (count($allImages) > 1): ?>
        <div class="product-thumbnails">
          <?php foreach ($allImages as $index => $imgUrl): ?>
            <div class="thumb <?= $index === 0 ? 'active' : '' ?>" onclick="setSlide(<?= $index ?>)">
              <img src="<?= url($imgUrl) ?>" alt="Thumbnail <?= $index + 1 ?>">
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <article class="product-detail-content">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; flex-wrap:wrap; gap:8px;">
        <p class="product-category" style="margin:0;"><?= h($product['category']) ?> <?php if (!empty($product['subcategory'])): ?>• <span style="font-weight:500;"><?= h($product['subcategory']) ?></span><?php endif; ?></p>
        <?php if (!empty($product['featured'])): ?>
          <span style="background:linear-gradient(135deg,#FFD700,#FFA500); color:#5D100B; font-size:0.75rem; font-weight:800; padding:4px 12px; border-radius:20px; text-transform:uppercase; letter-spacing:0.05em;">⭐ Featured</span>
        <?php endif; ?>
      </div>
      <h1 style="margin-bottom:10px;"><?= h($product['name']) ?></h1>

      <?php if (!empty($product['short_description'])): ?>
        <p style="font-size:1.05rem; color:#333; font-weight:500; line-height:1.6; margin-bottom:18px; background:var(--bg-soft); padding:14px 18px; border-left:4px solid var(--brand); border-radius:8px;">
          <?= h($product['short_description']) ?>
        </p>
      <?php endif; ?>

      <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; margin-bottom:22px;">
        <?php if (!empty($product['sku'])): ?>
          <div style="background:var(--bg-soft); padding:12px 14px; border-radius:10px;">
            <span style="display:block; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.08em; color:var(--muted); font-weight:700; margin-bottom:3px;">SKU</span>
            <span style="font-weight:800; color:var(--brand);"><?= h($product['sku']) ?></span>
          </div>
        <?php endif; ?>
        <?php if (!empty($product['weight'])): ?>
          <div style="background:var(--bg-soft); padding:12px 14px; border-radius:10px;">
            <span style="display:block; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.08em; color:var(--muted); font-weight:700; margin-bottom:3px;">Weight</span>
            <span style="font-weight:700; color:#333;"><?= h($product['weight']) ?></span>
          </div>
        <?php endif; ?>
        <?php if (!empty($product['dimensions'])): ?>
          <div style="background:var(--bg-soft); padding:12px 14px; border-radius:10px;">
            <span style="display:block; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.08em; color:var(--muted); font-weight:700; margin-bottom:3px;">Dimensions</span>
            <span style="font-weight:700; color:#333;"><?= h($product['dimensions']) ?></span>
          </div>
        <?php endif; ?>
        <div style="background:var(--bg-soft); padding:12px 14px; border-radius:10px;">
          <span style="display:block; font-size:0.7rem; text-transform:uppercase; letter-spacing:0.08em; color:var(--muted); font-weight:700; margin-bottom:3px;">Availability</span>
          <span style="font-weight:700; color:<?= (int)$product['stock'] > 0 ? '#15803d' : '#b91c1c' ?>;"><?= (int)$product['stock'] > 0 ? 'In Stock (' . (int)$product['stock'] . ')' : 'Out of Stock' ?></span>
        </div>
      </div>

      <p style="font-size:0.98rem; line-height:1.7; color:#444; margin-bottom:18px;"><?= nl2br(h($product['description'])) ?></p>

      <?php if (!empty($product['tags'])): ?>
        <div style="margin-bottom:20px;">
          <span style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.08em; color:var(--muted); font-weight:700; margin-right:10px;">Tags:</span>
          <?php foreach (explode(',', (string)$product['tags']) as $tag):
            $tag = trim($tag);
            if ($tag === '') continue;
          ?>
            <span style="display:inline-block; background:linear-gradient(135deg, rgba(93,16,11,0.08), rgba(210,173,161,0.15)); color:var(--brand); padding:4px 12px; border-radius:20px; font-size:0.78rem; font-weight:600; margin:3px 4px;"><?= h($tag) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <p class="price" style="margin:25px 0 10px;"><?= h(money((float) $product['price'])) ?></p>

      <form method="post" action="cart.php" class="form-actions">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="product_id" value="<?= h((string) $product['id']) ?>">
        <input type="number" class="qty-input" name="quantity" min="1" max="<?= h((string) $product['stock']) ?>" value="1" aria-label="Quantity">
        <button type="submit" class="btn" <?= (int)$product['stock'] <= 0 ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '' ?>>Add To Cart</button>
        <a href="products.php" class="btn btn-outline">Continue Shopping</a>
      </form>
    </article>
  </div>
</section>

<section class="section reviews-section">
  <div class="section-inner fade">
    <div class="reviews-header">
      <h2>Customer Reviews</h2>
      <div class="rating-summary">
        <?php
        $avgRating = 0;
        if (count($reviews) > 0) {
            $totalRating = array_sum(array_column($reviews, 'rating'));
            $avgRating = round($totalRating / count($reviews), 1);
        }
        ?>
        <div class="stars-outer">
          <div class="stars-inner" style="width: <?= ($avgRating / 5) * 100 ?>%"></div>
        </div>
        <span class="avg-text"><?= $avgRating ?> / 5 (<?= count($reviews) ?> Reviews)</span>
      </div>
    </div>

    <?php if (is_logged_in()): ?>
      <div class="review-form-container">
        <h3>Write a Review</h3>
        <form method="post" action="product.php?slug=<?= urlencode($product['slug']) ?>" class="review-form">
          <input type="hidden" name="action" value="submit_review">
          <div class="rating-input">
            <label>Rating:</label>
            <div class="star-rating">
              <?php for ($i = 5; $i >= 1; $i--): ?>
                <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?>>
                <label for="star<?= $i ?>" title="<?= $i ?> stars">
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                </label>
              <?php endfor; ?>
            </div>
          </div>
          <div class="form-group">
            <textarea name="comment" class="input" placeholder="Share your thoughts about this product..." rows="4" required></textarea>
          </div>
          <button type="submit" class="btn">Submit Review</button>
        </form>
      </div>
    <?php else: ?>
      <div class="review-login-prompt">
        <p>Please <a href="login.php">login</a> to share your review.</p>
      </div>
    <?php endif; ?>

    <div class="reviews-list">
      <?php if (count($reviews) > 0): ?>
        <?php foreach ($reviews as $review): ?>
          <article class="review-item">
            <div class="review-meta">
              <div class="reviewer-info">
                <span class="reviewer-name"><?= h($review['full_name']) ?></span>
                <span class="review-date"><?= date('M d, Y', strtotime($review['created_at'])) ?></span>
              </div>
              <div class="review-rating">
                <div class="stars-outer">
                  <div class="stars-inner" style="width: <?= ($review['rating'] / 5) * 100 ?>%"></div>
                </div>
              </div>
            </div>
            <div class="review-content">
              <p><?= nl2br(h($review['comment'])) ?></p>
            </div>
          </article>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="muted no-reviews">No reviews yet. Be the first to review this product!</p>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php if ($relatedProducts !== []): ?>
  <section class="section highlight" style="padding-top: 10px;">
    <div class="section-inner fade">
      <p class="eyebrow" style="text-align:center;">More</p>
      <h2 style="text-align:center;">Related Products</h2>
      <div class="product-grid" style="margin-top: 22px;">
        <?php foreach ($relatedProducts as $item): ?>
          <article class="product-card fade">
            <figure>
              <img src="<?= h($item['image_url']) ?>" alt="<?= h($item['name']) ?>">
              <div class="icons-overlay">
                <a href="product.php?slug=<?= urlencode($item['slug']) ?>" class="icon-action view-icon" title="View product">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                </a>
                <button class="icon-action heart-icon" data-id="<?= h((string) $item['id']) ?>" title="Add to favorites">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l8.78-8.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                  </svg>
                </button>
              </div>
            </figure>
            <div class="product-card-content">
              <h3><?= h($item['name']) ?></h3>
              <p class="price"><?= h(money((float) $item['price'])) ?></p>
            </div>
            <form method="post" action="cart.php" class="bucket-form">
              <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="add">
              <input type="hidden" name="product_id" value="<?= h((string) $item['id']) ?>">
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
    </div>
  </section>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
let currentSlide = 0;
const slides = document.querySelectorAll('.slide');
const dots = document.querySelectorAll('.dot');
const thumbs = document.querySelectorAll('.thumb');

function setSlide(index) {
    if (index < 0) index = slides.length - 1;
    if (index >= slides.length) index = 0;
    
    slides.forEach(s => s.classList.remove('active'));
    if (dots.length) dots.forEach(d => d.classList.remove('active'));
    if (thumbs.length) thumbs.forEach(t => t.classList.remove('active'));
    
    slides[index].classList.add('active');
    if (dots[index]) dots[index].classList.add('active');
    if (thumbs[index]) thumbs[index].classList.add('active');
    
    const counter = document.getElementById('sliderCounter');
    if (counter) {
        counter.textContent = `${index + 1} / ${slides.length}`;
    }
    
    currentSlide = index;
}

function moveSlide(step) {
    setSlide(currentSlide + step);
}
</script>

<style>
.product-detail-media { height: auto; aspect-ratio: auto; overflow: visible; box-shadow: none; border: none; background: transparent; }
.slider-container { position: relative; width: 100%; height: 500px; overflow: hidden; border-radius: 12px; background: #f9f9f9; box-shadow: var(--shadow); border: 1px solid var(--line); }
.slider-counter { position: absolute; top: 20px; right: 20px; background: rgba(0, 0, 0, 0.6); color: #fff; padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; font-weight: 700; z-index: 20; backdrop-filter: blur(4px); pointer-events: none; }
.slider-track { width: 100%; height: 100%; display: flex; transition: transform 0.5s ease-in-out; }
.slide { min-width: 100%; height: 100%; display: none; background: #fdfdfd; }
.slide.active { display: block; }
.slide img { width: 100%; height: 100%; object-fit: contain; }

.slider-btn { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(255, 255, 255, 0.9); border: none; width: 44px; height: 44px; border-radius: 50%; font-size: 1.8rem; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 10; transition: all 0.3s; color: var(--brand-strong); }
.slider-btn:hover { background: #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.15); transform: translateY(-50%) scale(1.05); }
.prev { left: 15px; }
.next { right: 15px; }

.slider-dots { position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); display: flex; gap: 8px; z-index: 10; padding: 6px 12px; background: rgba(255,255,255,0.2); backdrop-filter: blur(4px); border-radius: 20px; }
.dot { width: 8px; height: 8px; border-radius: 50%; background: rgba(255,255,255,0.5); cursor: pointer; transition: all 0.3s; }
.dot.active { background: #fff; width: 24px; border-radius: 4px; }

.product-thumbnails { display: flex; gap: 12px; margin-top: 20px; overflow-x: auto; padding: 4px 4px 12px 4px; scrollbar-width: thin; scrollbar-color: var(--line) transparent; }
.thumb { width: 80px; height: 80px; border-radius: 10px; overflow: hidden; cursor: pointer; border: 2px solid transparent; opacity: 0.7; flex-shrink: 0; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.thumb.active { border-color: var(--brand); opacity: 1; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.thumb img { width: 100%; height: 100%; object-fit: cover; }

@media (max-width: 768px) {
    .slider-container { height: 350px; }
}
</style>
