document.addEventListener("DOMContentLoaded", () => {
  const luxuryMenuToggle = document.getElementById("luxuryMenuToggle");
  const luxurySideMenu = document.getElementById("luxurySideMenu");
  const luxuryMenuOverlay = document.getElementById("luxuryMenuOverlay");
  const luxuryMenuClose = document.getElementById("luxuryMenuClose");

  const setMenuState = (isOpen) => {
    if (!luxurySideMenu || !luxuryMenuOverlay) {
      return;
    }

    luxurySideMenu.classList.toggle("open", isOpen);
    luxuryMenuOverlay.classList.toggle("open", isOpen);
    document.body.classList.toggle("menu-open", isOpen);
  };

  if (luxuryMenuToggle) {
    luxuryMenuToggle.addEventListener("click", () => {
      setMenuState(true);
    });
  }

  if (luxuryMenuClose) {
    luxuryMenuClose.addEventListener("click", () => {
      setMenuState(false);
    });
  }

  if (luxuryMenuOverlay) {
    luxuryMenuOverlay.addEventListener("click", () => {
      setMenuState(false);
    });
  }

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && luxurySideMenu?.classList.contains("open")) {
      setMenuState(false);
    }
  });

  if (luxurySideMenu) {
    luxurySideMenu.querySelectorAll("a:not(.dropdown-item)").forEach((link) => {
      link.addEventListener("click", () => {
        setMenuState(false);
      });
    });

    // Side Menu Dropdown Toggle
    const dropdownToggles = luxurySideMenu.querySelectorAll(".dropdown-toggle");
    dropdownToggles.forEach(toggle => {
      toggle.addEventListener("click", (e) => {
        e.preventDefault();
        const container = toggle.closest(".menu-dropdown-container");
        const isOpen = container.classList.contains("open");
        
        // Close other dropdowns
        luxurySideMenu.querySelectorAll(".menu-dropdown-container").forEach(other => {
          if (other !== container) other.classList.remove("open");
        });

        container.classList.toggle("open", !isOpen);
      });
    });

    // Close menu when clicking a dropdown link
    luxurySideMenu.querySelectorAll(".dropdown-item").forEach(link => {
      link.addEventListener("click", () => {
        setMenuState(false);
      });
    });
  }

  // Back to Top functionality
  const backToTopBtn = document.getElementById("backToTop");
  if (backToTopBtn) {
    backToTopBtn.addEventListener("click", () => {
      window.scrollTo({
        top: 0,
        behavior: "smooth"
      });
    });
  }

  // Testimonials Slider Clone for seamless scroll
  const track = document.getElementById("testimonialsTrack");
  if (track) {
    const slides = Array.from(track.children);
    slides.forEach(slide => {
      const clone = slide.cloneNode(true);
      track.appendChild(clone);
    });
  }

  const fadeTargets = document.querySelectorAll(".fade");
  const sequenceTargets = document.querySelectorAll(".reveal-seq");

  // Custom Notification Function
  const showNotification = (message, type = 'success') => {
    const notification = document.getElementById("customNotification");
    if (!notification) return;

    const iconEl = notification.querySelector(".notification-icon");
    const messageEl = notification.querySelector(".notification-message");

    // Reset classes
    notification.className = "custom-notification show " + type;
    
    // Set content
    messageEl.textContent = message;
    iconEl.innerHTML = type === 'success' ? '✓' : '⚠';

    // Auto hide after 3 seconds
    setTimeout(() => {
      notification.classList.remove("show");
    }, 3000);
  };

  // Auto-hide PHP Flash Messages (Logout, etc.)
  const flashMessages = document.querySelectorAll('.flash');
  flashMessages.forEach(flash => {
    // Progress bar effect manually for existing flash messages
    const progress = document.createElement('div');
    progress.className = 'notification-progress';
    flash.style.position = 'relative';
    flash.style.overflow = 'hidden';
    flash.appendChild(progress);
    progress.style.animation = 'progressAnim 4s linear forwards';

    setTimeout(() => {
      flash.style.opacity = '0';
      flash.style.transform = 'translateX(120%)';
      flash.style.transition = 'all 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55)';
      setTimeout(() => {
        if (flash.parentElement && flash.parentElement.children.length === 1) {
          flash.parentElement.remove();
        } else {
          flash.remove();
        }
      }, 6000);
    }, 4000);
  });

  if ((fadeTargets.length > 0 || sequenceTargets.length > 0) && "IntersectionObserver" in window) {
    const observer = new IntersectionObserver(
      (entries, obs) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) {
            return;
          }

          if (entry.target.classList.contains("reveal-seq")) {
            entry.target.classList.add("seq-visible");
          } else {
            entry.target.classList.add("visible");
          }

          obs.unobserve(entry.target);
        });
      },
      { threshold: 0.18, rootMargin: "0px 0px -40px 0px" }
    );

    fadeTargets.forEach((item) => observer.observe(item));
    sequenceTargets.forEach((item) => observer.observe(item));
  } else {
    fadeTargets.forEach((item) => item.classList.add("visible"));
    sequenceTargets.forEach((item) => item.classList.add("seq-visible"));
  }

  // Heart (Favorites) Toggle
  document.querySelectorAll(".heart-icon").forEach((btn) => {
    btn.addEventListener("click", (e) => {
      e.preventDefault();
      const productId = btn.dataset.id;
      
      const formData = new FormData();
      formData.append('action', 'toggle');
      formData.append('product_id', productId);

      fetch('favorites.php', {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          btn.classList.toggle("active");
          const path = btn.querySelector("path");
          if (data.status === 'added') {
            path.setAttribute("fill", "currentColor");
            showNotification("Added to favorites!", "success");
          } else {
            path.setAttribute("fill", "none");
            showNotification("Removed from favorites!", "success");
            // If we are on the favorites page, remove the card
            if (window.location.pathname.includes('favorites.php')) {
              btn.closest('.product-card').remove();
            }
          }
        } else {
          showNotification(data.message || 'Error managing favorites', "error");
        }
      })
      .catch(err => {
        console.error('Favorites Error:', err);
        showNotification("Something went wrong.", "error");
      });
    });
  });

  document.querySelectorAll(".share-icon").forEach((btn) => {
    btn.addEventListener("click", (e) => {
      e.preventDefault();
      const url = window.location.origin + "/" + btn.dataset.url;
      navigator.clipboard.writeText(url).then(() => {
        showNotification("Product link copied to clipboard!", "success");
      });
    });
  });

  // FAQ Toggle Logic
  document.querySelectorAll(".faq-question").forEach(question => {
    question.addEventListener("click", () => {
      const item = question.parentElement;
      const isOpen = item.classList.contains("open");
      
      // Close other FAQs
      document.querySelectorAll(".faq-item").forEach(other => {
        if (other !== item) other.classList.remove("open");
      });

      item.classList.toggle("open", !isOpen);
    });
  });

  // Mobile product card click interaction
  if (window.innerWidth <= 920) {
    document.querySelectorAll('.product-card').forEach(card => {
      card.addEventListener('click', (e) => {
        if (e.target.closest('.icon-action') || e.target.closest('.product-bucket')) {
          return;
        }
        e.stopPropagation();
        document.querySelectorAll('.product-card').forEach(c => {
          if (c !== card) c.classList.remove('active-mobile');
        });
        card.classList.toggle('active-mobile');
      });
    });

    document.addEventListener('click', () => {
      document.querySelectorAll('.product-card').forEach(card => {
        card.classList.remove('active-mobile');
      });
    });
  }

  // AJAX Add to Cart
  document.querySelectorAll('form[action="cart.php"]').forEach(form => {
    form.addEventListener('submit', (e) => {
      const action = form.querySelector('input[name="action"]')?.value;
      if (action !== 'add') return;

      e.preventDefault();
      const formData = new FormData(form);
      
      fetch('cart.php', {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          const cartCounts = document.querySelectorAll('.cart-count');
          cartCounts.forEach(el => el.textContent = data.cart_count);
          
          const btn = form.querySelector('button[type="submit"]');
          const originalText = btn.innerHTML;
          btn.innerHTML = '✓ Added';
          btn.style.background = '#2b7a4b';
          showNotification("Product added to bucket!", "success");
          
          setTimeout(() => {
            btn.innerHTML = originalText;
            btn.style.background = '';
          }, 2000);
        } else {
          showNotification(data.message || 'Error adding product to cart', "error");
        }
      })
      .catch(err => {
        console.error('Cart Error:', err);
        showNotification("Failed to add product.", "error");
      });
    });
  });

  // AJAX Cart Update
  const cartList = document.querySelector('.cart-list');
  if (cartList) {
    cartList.addEventListener('change', (e) => {
      if (e.target.classList.contains('qty-input')) {
        const input = e.target;
        const form = input.closest('form');
        const productId = input.name.match(/\[(\d+)\]/)?.[1] || form.querySelector('input[name="product_id"]')?.value;
        const csrfToken = form.querySelector('input[name="csrf_token"]').value;
        
        const formData = new FormData();
        formData.append('action', 'update');
        formData.append('csrf_token', csrfToken);
        formData.append('product_id', productId);
        formData.append('quantity', input.value);

        fetch('cart.php', {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: formData
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            const subtotalEl = document.querySelector('.cart-summary .price');
            if (subtotalEl) subtotalEl.textContent = 'Subtotal: ' + data.subtotal;
            
            const cartCounts = document.querySelectorAll('.cart-count');
            cartCounts.forEach(el => el.textContent = data.cart_count);

            if (parseInt(input.value) === 0) {
              window.location.reload();
            }
          }
        });
      }
    });
  }

  // Password Visibility Toggle
  document.querySelectorAll(".password-toggle-btn").forEach((btn) => {
    btn.addEventListener("click", (e) => {
      e.preventDefault();
      const inputGroup = btn.closest(".password-input-group");
      const input = inputGroup.querySelector("input");
      const type = input.getAttribute("type") === "password" ? "text" : "password";
      input.setAttribute("type", type);

      // Toggle Eye Icon
      if (type === "text") {
        btn.innerHTML = `
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
            <line x1="1" y1="1" x2="23" y2="23"></line>
          </svg>`;
      } else {
        btn.innerHTML = `
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
            <circle cx="12" cy="12" r="3"></circle>
          </svg>`;
      }
    });
  });
});
