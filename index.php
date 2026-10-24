<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_website_review') {
    if (!is_logged_in()) {
        set_flash('error', 'Please login to submit a website review.');
    } else {
        $rating = (int) ($_POST['rating'] ?? 5);
        $comment = trim((string) ($_POST['comment'] ?? ''));
        $userId = (int) (current_user_id());

        if ($comment === '') {
            set_flash('error', 'Review comment is required.');
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO website_reviews (user_id, rating, comment, created_at) VALUES (:user_id, :rating, :comment, NOW())');
                $stmt->execute([
                    ':user_id' => $userId,
                    ':rating' => $rating,
                    ':comment' => $comment
                ]);
                set_flash('success', 'Thank you for your website review!');
                header("Location: index.php#website-reviews");
                exit;
            } catch (PDOException $e) {
                set_flash('error', 'Database error: Could not save review. Please ensure the website_reviews table exists.');
            }
        }
    }
}

$pageTitle = 'Resources Enterprises | Ecommerce Store';
$pageDescription = 'Premium ecommerce store for cutlery, footwear, salt artisans, and ceramics.';

$featuredProducts = $pdo
    ->query('SELECT id, name, slug, category, description, price, image_url FROM products WHERE active = 1 ORDER BY id ASC LIMIT 4')
    ->fetchAll();

include __DIR__ . '/includes/header.php';
?>
<section id="hero" class="section hero">
  <div class="section-inner fade">
    <h1>RESOURCES ENTERPRISES</h1>
    <p>Solutions for good</p>
    <a href="products.php" class="cta-btn">Shop now</a>
  </div>
</section>

<section id="about" class="section">
  <div class="section-inner split reveal-seq">
    <div class="copy">
      <p class="eyebrow">Who We Are</p>
      <h2>Who We Are</h2>
      <p>Resources Enterprises delivers premium products across industries, combining craftsmanship, innovation, and reliability for global markets. We are your trusted partner in manufacturing and export.</p>
    </div>
    <figure class="media">
      <img src="https://images.unsplash.com/photo-1631556764629-b51c3ae40d18?auto=format&fit=crop&w=1400&q=80" alt="Woman researcher working in a modern manufacturing laboratory environment">
    </figure>
  </div>
</section>

<section id="cutlery" class="section highlight">
  <div class="section-inner split reverse reveal-seq">
    <div class="copy">
      <p class="eyebrow">Cutlery</p>
      <h2>Craftsmanship in Cutlery</h2>
      <p>Precision-engineered stainless steel products, built for durability and performance. Our cutlery range represents the pinnacle of Wazirabad's legendary metalworking heritage.</p>
    </div>
    <figure class="media">
      <img src="https://images.unsplash.com/photo-1507648154934-f230d5bd6942?auto=format&fit=crop&w=1400&q=80" alt="Premium stainless steel kitchen knives and cutlery sets displayed on a modern surface">
    </figure>
  </div>
</section>

<section id="footwear" class="section">
  <div class="section-inner split reveal-seq">
    <div class="copy">
      <p class="eyebrow">Footwear</p>
      <h2>Footwear &amp; Lifestyle</h2>
      <p>Durable, comfortable, modern designs meeting international quality standards. We combine traditional techniques with contemporary style to deliver superior footwear.</p>
    </div>
    <figure class="media">
      <img src="https://images.unsplash.com/photo-1559513493-8ee89c9e69ee?auto=format&fit=crop&w=1400&q=80" alt="Brown lifestyle sneakers arranged in a premium style setup">
    </figure>
  </div>
</section>

<section id="salt" class="section highlight">
  <div class="section-inner split reverse reveal-seq">
    <div class="copy">
      <p class="eyebrow">Salt Artisans</p>
      <h2>Salt &amp; Artisan Creations</h2>
      <p>Elegant handcrafted salt and artisan products, naturally beautiful and export-ready. Each piece is unique, reflecting the natural beauty of the earth's minerals.</p>
    </div>
    <figure class="media">
      <img src="https://images.unsplash.com/photo-1579101450104-5890cd7db9ad?auto=format&fit=crop&w=1400&q=80" alt="Warm-lit handcrafted Himalayan salt lamps arranged as an artisan product display">
    </figure>
  </div>
</section>

<section id="ranges" class="section">
  <div class="section-inner fade">
    <p class="eyebrow" style="text-align:center;">Portfolio</p>
    <h2 style="text-align:center;">Featured Products</h2>
    <p class="lead">Explore our diverse portfolio of premium manufactured goods.</p>

    <div class="product-grid">
      <?php foreach ($featuredProducts as $product): ?>
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
              <button class="icon-action share-icon" data-url="product.php?slug=<?= urlencode($product['slug']) ?>" title="Share product">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="18" cy="5" r="3"></circle>
                  <circle cx="6" cy="12" r="3"></circle>
                  <circle cx="18" cy="19" r="3"></circle>
                  <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                  <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                </svg>
              </button>
            </div>
          </figure>
          <div class="product-card-content">
            <p class="product-category"><?= h($product['category']) ?></p>
            <h3><?= h($product['name']) ?></h3>
            <p><?= h($product['description']) ?></p>
            <p class="price"><?= h(money((float) $product['price'])) ?></p>
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
  </div>
</section>

<section id="contact" class="section inquiry highlight fade">
  <div class="section-inner" style="text-align:center;">
    <p class="eyebrow">Inquiries</p>
    <h2>Partner With Excellence</h2>
    <p class="lead">Need custom quantities or export discussions? Reach us directly and we will respond quickly.</p>
    <a href="contact.php" class="cta-btn">Contact Us</a>
  </div>
</section>

<section id="collections" class="section highlight">
  <div class="section-inner fade">
    <p class="eyebrow" style="text-align:center;">Our Collections</p>
    <h2 style="text-align:center;">Explore Our Curated Ranges</h2>
    <div class="collection-grid" style="margin-top: 40px;">
      <article class="collection-card">
        <img src="https://images.unsplash.com/photo-1594913785162-e6785b48dc51?auto=format&fit=crop&w=1200&q=80" alt="Luxury Cutlery Collection">
        <div class="collection-overlay">
          <h3>Luxury Cutlery</h3>
          <a href="products.php?category=Cutlery" class="btn">View Collection</a>
        </div>
      </article>
      <article class="collection-card">
        <img src="https://images.unsplash.com/photo-1515488764276-beab7607c1e6?auto=format&fit=crop&w=1200&q=80" alt="Artisan Salt Collection">
        <div class="collection-overlay">
          <h3>Artisan Salt</h3>
          <a href="products.php?category=Salt Artisans" class="btn">View Collection</a>
        </div>
      </article>
    </div>
  </div>
</section>

<section id="faqs" class="section highlight">
  <div class="section-inner fade">
    <p class="eyebrow" style="text-align:center;">Assistance</p>
    <h2 style="text-align:center;">Frequently Asked Questions</h2>
    <div class="faq-section" style="margin-top: 40px;">
      <div class="faq-item">
        <button class="faq-question">How do I place an order? <span>+</span></button>
        <div class="faq-answer">
          <p>Browse our collection, add items to your bucket, and proceed to checkout. We accept various payment methods for your convenience.</p>
        </div>
      </div>
      <div class="faq-item">
        <button class="faq-question">What is your shipping policy? <span>+</span></button>
        <div class="faq-answer">
          <p>We offer reliable shipping options both locally and internationally. Delivery times vary based on your location and order volume.</p>
        </div>
      </div>
      <div class="faq-item">
        <button class="faq-question">Can I request custom product quantities? <span>+</span></button>
        <div class="faq-answer">
          <p>Yes, for bulk or custom inquiries, please reach out to us via our contact form or WhatsApp. We specialize in export and wholesale.</p>
        </div>
      </div>
      <div class="faq-item">
        <button class="faq-question">Do you offer returns or refunds? <span>+</span></button>
        <div class="faq-answer">
          <p>Our commitment is to your satisfaction. Please refer to our Refund & Return policy linked in the footer for detailed information.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<section id="website-reviews" class="section">
  <div class="section-inner fade">
    <p class="eyebrow" style="text-align:center;">Testimonials</p>
    <h2 style="text-align:center;">Share Your Experience</h2>
    
    <?php if (is_logged_in()): ?>
      <div class="review-form-container" style="max-width: 600px; margin: 40px auto 0;">
        <p class="lead" style="margin-bottom: 30px;">Your feedback helps us grow. Please share your thoughts about our website and service.</p>
        <form method="post" action="index.php" class="review-form">
          <input type="hidden" name="action" value="submit_website_review">
          <div class="rating-input" style="margin-bottom: 20px;">
            <label style="font-weight: 700; color: var(--brand);">Rating:</label>
            <div class="star-rating">
              <?php for ($i = 5; $i >= 1; $i--): ?>
                <input type="radio" id="website_star<?= $i ?>" name="rating" value="<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?>>
                <label for="website_star<?= $i ?>" title="<?= $i ?> stars">
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                </label>
              <?php endfor; ?>
            </div>
          </div>
          <div class="form-group">
            <textarea name="comment" class="input" placeholder="Share your experience with our website..." rows="4" required></textarea>
          </div>
          <button type="submit" class="btn" style="width: 100%; margin-top: 20px;">Submit Testimonial</button>
        </form>
      </div>
    <?php else: ?>
      <div class="review-login-prompt" style="text-align: center; padding: 40px; background: var(--bg-soft); border-radius: 16px; max-width: 600px; margin: 0 auto;">
        <p class="muted">Please <a href="login.php" style="color: var(--premium); font-weight: 700; text-decoration: underline;">login</a> to share your testimonial with us.</p>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
