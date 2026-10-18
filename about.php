<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'About Us | ' . APP_NAME;
$pageDescription = 'Learn about Resource Enterprises - our story, vision, mission, and core values.';

include __DIR__ . '/includes/header.php';
?>
<section class="page-hero fade">
  <div class="section-inner">
    <p class="eyebrow">Our Story</p>
    <h2>About Resource Enterprises</h2>
    <p>Bridging tradition and innovation since day one.</p>
  </div>
</section>

<section class="about-section">
  <div class="about-container">
    <article class="about-vision fade">
      <div class="about-section-header">
        <span class="about-section-number">01</span>
        <h2>Vision Statement</h2>
      </div>
      <div class="about-content">
        <p>To become a globally recognized enterrpise that transforms everyday living through innovative, high-quality products while representing Pakistan’s excellence on the international stage.</p>
      </div>
    </article>

    <article class="about-mission fade">
      <div class="about-section-header">
        <span class="about-section-number">02</span>
        <h2>Mission Statement</h2>
      </div>
      <div class="about-content">
        <p>At Resource Enterprises, we deliver diverse, high-quality products across multiple categories including kitchenware, lifestyle goods, engineering solutions, and more. We serve modern consumers and businesses by combining functionality, design, and affordability. Through innovation, strong digital presence, and design thinking, we aim to simplify lives, empower industries, and build long-term trust in every transaction.</p>
      </div>
    </article>

    <article class="about-values fade">
      <div class="about-section-header">
        <span class="about-section-number">03</span>
        <h2>Core Values</h2>
      </div>
      <div class="values-grid">
        <div class="value-card">
          <div class="value-card-number">01</div>
          <h3>Quality First</h3>
          <p>We ensure every product meets high standards of durability, design, and performance.</p>
        </div>
        <div class="value-card">
          <div class="value-card-number">02</div>
          <h3>Customer Commitment</h3>
          <p>We prioritize customer satisfaction by delivering value, reliability, and excellent service.</p>
        </div>
        <div class="value-card">
          <div class="value-card-number">03</div>
          <h3>Innovation & Growth</h3>
          <p>We continuously improve and adapt to stay ahead in a competitive market.</p>
        </div>
        <div class="value-card">
          <div class="value-card-number">04</div>
          <h3>Integrity & Trust</h3>
          <p>We operate with honesty and transparency in every interaction.</p>
        </div>
        <div class="value-card">
          <div class="value-card-number">05</div>
          <h3>Versatility</h3>
          <p>We embrace multiple industries and product categories to serve diverse needs.</p>
        </div>
        <div class="value-card">
          <div class="value-card-number">06</div>
          <h3>Generation-to-Generation Bonding</h3>
          <p>We create products that connect traditions with modern living, strengthening relationships and values across generations.</p>
        </div>
        <div class="value-card">
          <div class="value-card-number">07</div>
          <h3>Excellence in Execution</h3>
          <p>We focus on delivering results with precision and professionalism.</p>
        </div>
      </div>
    </article>

    <div class="about-cta fade">
      <div class="about-cta-content">
        <h2>Ready to Partner with Us?</h2>
        <p>Whether you're looking for premium cutlery, unique salt artisans, or reliable export solutions, we're here to help. Let's start a conversation.</p>
        <div class="about-cta-buttons">
          <a href="contact.php" class="btn btn-dark" style="padding: 20px 40px;">Get In Touch</a>
          <a href="products.php" class="btn btn-outline-dark" style="padding: 20px 40px;">Explore Products</a>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
