  </main>

  <?php if (!is_admin_panel()): ?>
  <div class="testimonials-slider">
    <div class="testimonials-track" id="testimonialsTrack">
      <?php
      // Fetch latest website reviews and combine with some defaults to keep slider full
      try {
          $dbReviews = $pdo->query("SELECT wr.comment, u.full_name FROM website_reviews wr LEFT JOIN users u ON wr.user_id = u.id ORDER BY wr.created_at DESC LIMIT 10")->fetchAll();
          
          $fallbacks = [
              ["comment" => "Excellent quality products and fast delivery!", "name" => "Sarah K."],
              ["comment" => "The salt lamps are beautiful and unique.", "name" => "John D."],
              ["comment" => "Professional service and reliable partnership.", "name" => "Ahmed R."],
              ["comment" => "Best cutlery items I have ever used.", "name" => "Maria S."]
          ];

          // Combine DB reviews with fallbacks
          $allReviews = [];
          foreach ($dbReviews as $dr) {
              $allReviews[] = ["comment" => $dr['comment'], "name" => $dr['full_name'] ?: 'Anonymous'];
          }
          foreach ($fallbacks as $f) {
              $allReviews[] = $f;
          }

          // Render all
          foreach ($allReviews as $r) {
              $nameStr = $r['name'] ? ' - ' . h($r['name']) : '';
              echo '<div class="testimonial-slide">"' . h($r['comment']) . '"' . $nameStr . '</div>';
          }
      } catch (PDOException $e) {
          echo '<div class="testimonial-slide">"Quality craftsmanship since 2023."</div>';
      }
      ?>
    </div>
  </div>

  <footer class="site-footer">

    <div class="footer-header">
      <h2>Resource Enterprises</h2>
      <p class="brand-description">Resource Enterprises is a premier manufacturing and export powerhouse based in Wazirabad and Lahore, Pakistan. We specialize in delivering world-class craftsmanship in cutlery, kitchenware, salt artisans, and engineering parts to global markets. Our commitment to quality and innovation ensures that we provide solutions for good, bridging the gap between traditional artistry and modern industrial excellence.</p>
      <div class="header-line"></div>
    </div>

    <div class="footer-grid">
      <!-- Contact us -->
      <section class="footer-column">
        <h4>Contact us</h4>
        <div class="column-content">
          <p>0316 4169611</p>
          <p>resourceent.operations@gmail.com</p>
          <a href="<?= url('contact.php') ?>" class="footer-btn">Send inquiry</a>
        </div>
      </section>

      <!-- Find us -->
      <section class="footer-column">
        <h4>Find us</h4>
        <div class="column-content">
          <div class="location-box">Location 1: Lahore, Pakistan</div>
          <div class="location-box">Location 2: Wazirabad, Pakistan</div>
        </div>
      </section>

      <!-- Follow us -->
      <section class="footer-column">
        <h4>Follow us</h4>
        <div class="column-content social-grid">
          <a href="https://www.instagram.com/resource_enterprises_official?igsh=MXU1Zm5ua2lvMmI3bg==" target="_blank" rel="noopener noreferrer" class="social-item">
            <span class="social-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
            </span>
            Instagram
          </a>
          <a href="https://www.threads.com/@resource_enterprises_official" target="_blank" rel="noopener noreferrer" class="social-item">
            <span class="social-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 12c-3 0-5.5-2.5-5.5-5.5S9 1 12 1s5.5 2.5 5.5 5.5S15 12 12 12Z"></path><path d="M12 23c-5.5 0-10-4.5-10-10S6.5 3 12 3s10 4.5 10 10-4.5 10-10 10Z"></path></svg>
            </span>
            Threads
          </a>
          <a href="https://www.tiktok.com/@resource_enterprises_off?_r=1&_t=ZS-95YObJbRoG8" target="_blank" rel="noopener noreferrer" class="social-item">
            <span class="social-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"></path></svg>
            </span>
            Tiktok
          </a>
          <a href="https://www.facebook.com/share/1CEEnBXnXw/" target="_blank" rel="noopener noreferrer" class="social-item">
            <span class="social-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg>
            </span>
            Facebook
          </a>
          <a href="https://www.linkedin.com/company/resources-ent/" target="_blank" rel="noopener noreferrer" class="social-item">
            <span class="social-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"></path><rect x="2" y="9" width="4" height="12"></rect><circle cx="4" cy="4" r="2"></circle></svg>
            </span>
            LinkedIn
          </a>
          <a href="https://wa.me/923164169611" target="_blank" rel="noopener noreferrer" class="social-item">
            <span class="social-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 1 1-7.6-11.7 8.38 8.38 0 0 1 3.8.9L21 3z"></path></svg>
            </span>
            WhatsApp
          </a>
        </div>
      </section>
    </div>

    <div class="footer-navigation">
      <a href="<?= url('refund.php') ?>" class="nav-pill">Refund & Return</a>
      <a href="<?= url('privacy.php') ?>" class="nav-pill">Privacy Policy</a>
      
      <div class="back-to-top-wrap">
        <button id="backToTop" class="back-to-top">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>
          Back to top
        </button>
      </div>
    </div>

    <div class="footer-copyright">
      Resource Enterprises &copy; 2023 All rights reserved
    </div>
  </footer>
  <?php endif; ?>

  <?php if (basename($_SERVER['PHP_SELF']) === 'index.php'): ?>
    <a href="https://wa.me/923164169611" class="floating-wa-btn" target="_blank" rel="noopener noreferrer" aria-label="Contact us on WhatsApp">
      <svg width="30" height="30" viewBox="0 0 24 24" fill="currentColor">
        <path d="M20.52 3.48A11.8 11.8 0 0 0 12.12 0C5.5 0 .12 5.37.12 12c0 2.11.55 4.17 1.6 5.99L0 24l6.2-1.62A11.84 11.84 0 0 0 12.12 24C18.75 24 24 18.63 24 12c0-3.15-1.22-6.11-3.48-8.52Zm-8.4 18.53c-1.81 0-3.58-.49-5.14-1.42l-.37-.22-3.68.96.98-3.58-.24-.37a9.8 9.8 0 0 1-1.52-5.24c0-5.46 4.45-9.9 9.92-9.9a9.87 9.87 0 0 1 7.01 2.9 9.8 9.8 0 0 1 2.9 6.99c0 5.46-4.45 9.88-9.86 9.88Zm5.42-7.43c-.3-.15-1.79-.88-2.07-.99-.28-.1-.49-.15-.69.16-.2.31-.79.98-.97 1.19-.18.2-.36.23-.67.08-.3-.15-1.28-.47-2.44-1.5-.9-.8-1.5-1.8-1.67-2.1-.18-.31-.02-.48.13-.63.13-.13.3-.34.46-.5.15-.17.2-.29.31-.49.1-.2.05-.37-.03-.52-.08-.15-.69-1.67-.95-2.29-.25-.6-.5-.52-.69-.52h-.59c-.2 0-.52.08-.79.37-.27.3-1.04 1.01-1.04 2.46s1.07 2.85 1.22 3.05c.15.2 2.08 3.17 5.05 4.44.7.3 1.25.48 1.68.61.71.22 1.36.19 1.88.12.57-.08 1.79-.73 2.04-1.44.25-.72.25-1.33.18-1.45-.08-.13-.28-.2-.58-.34Z"></path>
      </svg>
      <span>Contact us</span>
    </a>
  <?php endif; ?>

  <script src="<?= url('assets/js/main.js') ?>"></script>
</body>
</html>
