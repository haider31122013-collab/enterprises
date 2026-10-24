# Resources Enterprises - E-Commerce & Wholesale Platform

A full-featured, luxury e-commerce platform and B2B wholesale store built with PHP, MySQL, vanilla JavaScript, and CSS. Includes a comprehensive administrator suite, multi-currency engine, dynamic payment gateway manager, order lifecycle management, and customer storefront.

---

## 🌟 Key Features

### 🛍️ Customer Storefront
- **Modern Responsive Design**: Luxury responsive aesthetic optimized for desktop, tablet, and mobile.
- **Product Catalog & Collections**: Categorized browsing (Cutlery, Kitchenware, Salt Artisans, Footwear, Ceramics, Industrial parts).
- **Cart & Wishlist**: Real-time cart calculation and customer favorites list.
- **Dynamic Checkout**: Supports multiple payment methods, order notes, shipping address, and instant order tracking.
- **Customer Portal**: Order history with product thumbnails, item breakdown, and profile manager.

### ⚙️ Admin Control Panel (`/admin`)
- **Dashboard**: Live analytics for products, orders, users, reviews, payment methods, and active currency.
- **Order Management**: Full order tracking with order item snapshots (images, titles, and prices remain intact even if products are removed from catalog).
- **Product Management**: Full CRUD, image uploads, category sorting, stock tracking, and SEO meta tags.
- **Payment Methods Manager**: Dynamically configure payment gateways (Askari Bank Transfer, COD, JazzCash, QR code support, and custom instructions).
- **Currency Engine with Today's Conversion Rate**:
  - Supports 21 global & regional currencies (PKR, USD, EUR, GBP, AED, SAR, etc.).
  - Prompts for today's exchange rate upon currency switch.
  - Interactive live price preview.
  - Automatically updates entire product catalog prices to match the new currency.
- **Admin Settings & Security (`admin/settings.php`)**:
  - Update Admin Profile Name.
  - Change Admin Login Email with validation and duplication checks.
  - Change Admin Password with current password verification and eye show/hide toggle.
- **User & Review Management**: Customer account viewer and review moderation.
- **Contact Messages**: Contact form inquiries with read/unread tracking.

---

## 🚀 Installation & Local Setup

### 1. Prerequisites
- **Web Server**: Apache / Nginx (XAMPP, WAMP, Laragon, or PHP built-in server)
- **PHP**: 8.0 or higher (with `pdo_mysql` extension)
- **Database**: MySQL 5.7+ or MariaDB 10.3+

### 2. Database Setup
1. Open MySQL / phpMyAdmin.
2. Create database `enterprise_db` or run the automated setup:
   - **Option A (Browser/CLI Setup)**: Run `setup_local_database.php` in your browser:  
     `http://localhost/enterprise/setup_local_database.php`
   - **Option B (SQL Import)**: Import `database.sql` directly into your MySQL server.

### 3. Application Configuration
Database credentials and app constants are defined in `includes/bootstrap.php`:
```php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'enterprise_db');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 4. Admin Access
Navigate to: `http://localhost/enterprise/admin/login.php` (or `http://localhost/enterprise/login.php`)
- **Default Email**: `admin@resource.com`
- **Default Password**: `admin123`
*(Alternative: `adminresource00@gmail.com` / `Resource123!`)*

Credentials can be customized anytime from **Admin Panel -> Settings**.

---

## 📁 Project Structure

```
├── admin/                     # Admin control panel pages
│   ├── dashboard.php          # Main analytics dashboard
│   ├── orders.php             # Order management list & filter
│   ├── order-details.php      # Order detail & status manager
│   ├── products.php           # Catalog CRUD
│   ├── payment-methods.php    # Payment gateway manager
│   ├── currencies.php         # Multi-currency switcher & converter
│   ├── settings.php           # Admin credentials & security settings
│   ├── users.php              # User accounts
│   ├── reviews.php            # Product reviews
│   ├── collections.php        # Collections manager
│   └── messages.php           # Contact inquiries
├── assets/                    # Static assets (CSS, JS, images, icons)
├── includes/                  # Core modules, bootstrap, header & footer
├── logo/                      # Brand assets
├── uploads/                   # Uploaded product and media images
├── index.php                  # Store homepage
├── products.php               # Catalog listing & search
├── product.php                # Single product detail view
├── cart.php                   # Shopping cart
├── checkout.php               # Dynamic checkout & payment instructions
├── orders.php                 # Customer order history
├── database.sql               # Database schema & seed data
└── setup_local_database.php   # Automated database installer
```

---

## 🔒 Security
- PDO prepared statements for SQL injection prevention.
- CSRF token validation on sensitive POST actions.
- Secure password hashing using `PASSWORD_DEFAULT`.
- Admin route protection with `require_admin()` authentication middleware.

---

## 📄 License
Proprietary - Developed for Resources Enterprises.
