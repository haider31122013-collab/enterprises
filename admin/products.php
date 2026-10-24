<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin($pdo);

$pageTitle = 'Manage Products';

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
    set_flash('success', 'Product deleted successfully.');
    redirect('products.php');
}

// Handle Add/Edit
$editProduct = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $editProduct = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $name = trim($_POST['name']);
    $category = trim($_POST['category']);
    $subcategory = trim($_POST['subcategory'] ?? '');
    $price = (float)$_POST['price'];
    $stock = (int)$_POST['stock'];
    $description = trim($_POST['description']);
    $short_description = trim($_POST['short_description'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $weight = trim($_POST['weight'] ?? '');
    $dimensions = trim($_POST['dimensions'] ?? '');
    $meta_title = trim($_POST['meta_title'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');
    $meta_keywords = trim($_POST['meta_keywords'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    $active = isset($_POST['active']) ? 1 : 0;
    $featured = isset($_POST['featured']) ? 1 : 0;

    $slugInput = trim($_POST['slug'] ?? '');
    if ($slugInput !== '' && $_POST['action'] === 'update') {
        $slug = $slugInput;
    } elseif ($slugInput !== '' && $_POST['action'] === 'add') {
        $slug = $slugInput . '-' . rand(100, 999);
    } else {
        $baseSlug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $name));
        $slug = trim($baseSlug, '-') . '-' . rand(1000, 9999);
    }

    // Handle Main Image
    $image_url = '';
    if (isset($_POST['image_source'])) {
        if ($_POST['image_source'] === 'url') {
            $image_url = trim($_POST['image_url']);
        } elseif ($_POST['image_source'] === 'upload') {
            if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
                $uploaded = upload_image($_FILES['image_file']);
                if ($uploaded) {
                    $image_url = $uploaded;
                }
            }
        }
    }
    // Fallback for update if no new image provided
    if ($image_url === '' && $_POST['action'] === 'update' && isset($_POST['id'])) {
        $stmt = $pdo->prepare("SELECT image_url FROM products WHERE id = ?");
        $stmt->execute([(int)$_POST['id']]);
        $image_url = $stmt->fetchColumn();
    }

    if ($_POST['action'] === 'add') {
        $stmt = $pdo->prepare("INSERT INTO products (name, slug, category, subcategory, description, short_description, price, stock, sku, weight, dimensions, image_url, meta_title, meta_description, meta_keywords, tags, active, featured, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$name, $slug, $category, $subcategory, $description, $short_description, $price, $stock, $sku, $weight, $dimensions, $image_url, $meta_title, $meta_description, $meta_keywords, $tags, $active, $featured]);
        $productId = $pdo->lastInsertId();
    } elseif ($_POST['action'] === 'update') {
        $productId = (int)$_POST['id'];
        $stmt = $pdo->prepare("UPDATE products SET name = ?, slug = ?, category = ?, subcategory = ?, description = ?, short_description = ?, price = ?, stock = ?, sku = ?, weight = ?, dimensions = ?, image_url = ?, meta_title = ?, meta_description = ?, meta_keywords = ?, tags = ?, active = ?, featured = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$name, $slug, $category, $subcategory, $description, $short_description, $price, $stock, $sku, $weight, $dimensions, $image_url, $meta_title, $meta_description, $meta_keywords, $tags, $active, $featured, $productId]);

        // Handle Removal of existing additional images
        if (isset($_POST['keep_images']) && is_array($_POST['keep_images'])) {
            $keepIds = array_map('intval', $_POST['keep_images']);
            $placeholders = implode(',', array_fill(0, count($keepIds), '?'));
            $pdo->prepare("DELETE FROM product_images WHERE product_id = ? AND id NOT IN ($placeholders)")->execute(array_merge([$productId], $keepIds));
        } else {
            // If no images kept, delete all for this product
            $pdo->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$productId]);
        }
    }

    // Handle Additional Images
    if (isset($productId)) {
        // From URLs
        if (isset($_POST['additional_image_urls']) && is_array($_POST['additional_image_urls'])) {
            foreach ($_POST['additional_image_urls'] as $url) {
                $url = trim($url);
                if ($url !== '') {
                    $pdo->prepare("INSERT INTO product_images (product_id, image_url) VALUES (?, ?)")->execute([$productId, $url]);
                }
            }
        }
        // From Uploads
        if (isset($_FILES['additional_image_files'])) {
            $files = $_FILES['additional_image_files'];
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i]
                    ];
                    $uploaded = upload_image($file);
                    if ($uploaded) {
                        $pdo->prepare("INSERT INTO product_images (product_id, image_url) VALUES (?, ?)")->execute([$productId, $uploaded]);
                    }
                }
            }
        }
    }

    set_flash('success', 'Product ' . ($_POST['action'] === 'add' ? 'added' : 'updated') . ' successfully.');
    redirect('products.php');
}

$products = $pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="admin-layout">
  <aside class="admin-sidebar">
    <nav class="admin-nav">
      <a href="dashboard.php" class="admin-nav-item">Dashboard</a>
      <a href="orders.php" class="admin-nav-item">Manage Orders</a>
      <a href="products.php" class="admin-nav-item active">Manage Products</a>
      <a href="payment-methods.php" class="admin-nav-item">Payment Methods</a>
      <a href="currencies.php" class="admin-nav-item">Currencies</a>
      <a href="users.php" class="admin-nav-item">Manage Users</a>
      <a href="reviews.php" class="admin-nav-item">Manage Reviews</a>
      <a href="collections.php" class="admin-nav-item">Collections</a>
      <a href="messages.php" class="admin-nav-item">Messages</a>
      <a href="settings.php" class="admin-nav-item">Settings</a>
    </nav>
  </aside>

  <main class="admin-main">
    <header class="admin-header">
      <h1>Manage Products</h1>
      <button onclick="document.getElementById('productForm').style.display='block'" class="btn">Add New Product</button>
    </header>

    <div id="productForm" class="admin-modal" style="<?= $editProduct ? 'display:block' : 'display:none' ?>">
      <div class="modal-content">
        <header class="modal-header">
          <h3><?= $editProduct ? 'Edit Product' : 'Add New Product' ?></h3>
          <a href="products.php" class="close-btn">&times;</a>
        </header>
        <form method="post" class="review-form" enctype="multipart/form-data">
          <input type="hidden" name="action" value="<?= $editProduct ? 'update' : 'add' ?>">
          <?php if ($editProduct): ?>
            <input type="hidden" name="id" value="<?= $editProduct['id'] ?>">
          <?php endif; ?>
          
          <div class="form-grid">
            <div class="form-group" style="grid-column: span 2;">
              <label>Product Name <span style="color:red;">*</span></label>
              <input type="text" name="name" class="input" value="<?= $editProduct['name'] ?? '' ?>" required oninput="autoFillSEO(this)">
            </div>
            <div class="form-group" style="grid-column: span 2;">
              <label>URL Slug <small style="color:var(--muted);font-weight:500;">(edit mode only - leave empty to auto-generate)</small></label>
              <input type="text" name="slug" class="input" value="<?= $editProduct['slug'] ?? '' ?>" placeholder="auto-generated-from-name">
            </div>
            <div class="form-group">
              <label>Category <span style="color:red;">*</span></label>
              <select name="category" id="adminCategory" class="input" required onchange="updateSubcategories()">
                <option value="">Select Category</option>
                <option value="Cutlery" <?= ($editProduct['category'] ?? '') === 'Cutlery' ? 'selected' : '' ?>>Cutlery</option>
                <option value="Kitchenware" <?= ($editProduct['category'] ?? '') === 'Kitchenware' ? 'selected' : '' ?>>Kitchenware</option>
                <option value="Salt Crafts" <?= ($editProduct['category'] ?? '') === 'Salt Crafts' ? 'selected' : '' ?>>Salt Crafts</option>
                <option value="Footwear" <?= ($editProduct['category'] ?? '') === 'Footwear' ? 'selected' : '' ?>>Footwear</option>
                <option value="Value Ceramics" <?= ($editProduct['category'] ?? '') === 'Value Ceramics' ? 'selected' : '' ?>>Value Ceramics</option>
                <option value="Engineering Parts" <?= ($editProduct['category'] ?? '') === 'Engineering Parts' ? 'selected' : '' ?>>Engineering Parts</option>
                <option value="Project Feasibility" <?= ($editProduct['category'] ?? '') === 'Project Feasibility' ? 'selected' : '' ?>>Project Feasibility</option>
              </select>
            </div>
            <div class="form-group">
              <label>Subcategory <span style="color:red;">*</span></label>
              <select name="subcategory" id="adminSubcategory" class="input" required>
                <option value="">Select Subcategory</option>
                <!-- Options populated by JS -->
              </select>
            </div>
            <div class="form-group">
              <label>SKU</label>
              <input type="text" name="sku" class="input" value="<?= $editProduct['sku'] ?? '' ?>" placeholder="SKU-CUT-001">
            </div>
            <div class="form-group">
              <label>Price ($) <span style="color:red;">*</span></label>
              <input type="number" step="0.01" name="price" class="input" value="<?= $editProduct['price'] ?? '' ?>" required>
            </div>
            <div class="form-group">
              <label>Stock Qty <span style="color:red;">*</span></label>
              <input type="number" name="stock" class="input" value="<?= $editProduct['stock'] ?? '0' ?>" required>
            </div>
            <div class="form-group">
              <label>Weight</label>
              <input type="text" name="weight" class="input" value="<?= $editProduct['weight'] ?? '' ?>" placeholder="2.5 kg">
            </div>
            <div class="form-group" style="grid-column: span 2;">
              <label>Dimensions (LxWxH)</label>
              <input type="text" name="dimensions" class="input" value="<?= $editProduct['dimensions'] ?? '' ?>" placeholder="45x30x10 cm">
            </div>
          </div>

          <div style="border-top:2px dashed var(--line); padding-top:25px; margin-top:10px;"></div>

          <div class="form-group">
            <label>Short Description <small style="color:var(--muted);font-weight:500;">(for listing pages - 1-2 lines)</small></label>
            <textarea name="short_description" class="input" rows="2" maxlength="500" placeholder="Short summary shown on product listing cards..."><?= $editProduct['short_description'] ?? '' ?></textarea>
          </div>

          <div class="form-group">
            <label>Full Description <span style="color:red;">*</span></label>
            <textarea name="description" class="input" rows="5" required placeholder="Complete product details..."><?= $editProduct['description'] ?? '' ?></textarea>
          </div>

          <div style="border-top:2px dashed var(--line); padding-top:25px; margin-top:10px;"></div>

          <h3 style="font-family:'Playfair Display', serif; font-size:1.3rem; color:var(--brand); margin:0 0 18px 0;">🔍 SEO Settings</h3>

          <div class="form-group">
            <label>Meta Title <small id="metaTitleCount" style="color:var(--muted);font-weight:500;">(0-70 chars recommended)</small></label>
            <input type="text" name="meta_title" id="metaTitleInput" class="input" maxlength="255" value="<?= $editProduct['meta_title'] ?? '' ?>" placeholder="Product Name | Category - Resources Enterprises" oninput="countChars('metaTitleInput','metaTitleCount',70)">
          </div>

          <div class="form-group">
            <label>Meta Description <small id="metaDescCount" style="color:var(--muted);font-weight:500;">(0-160 chars recommended)</small></label>
            <textarea name="meta_description" id="metaDescInput" class="input" rows="2" maxlength="500" placeholder="Buy premium quality product at wholesale prices. Export quality... Best price..." oninput="countChars('metaDescInput','metaDescCount',160)"><?= $editProduct['meta_description'] ?? '' ?></textarea>
          </div>

          <div class="form-group">
            <label>Meta Keywords <small style="color:var(--muted);font-weight:500;">(comma separated)</small></label>
            <input type="text" name="meta_keywords" class="input" value="<?= $editProduct['meta_keywords'] ?? '' ?>" placeholder="premium knives, chef set, wholesale cutlery, export quality">
          </div>

          <div class="form-group">
            <label>Tags <small style="color:var(--muted);font-weight:500;">(comma separated)</small></label>
            <input type="text" name="tags" class="input" value="<?= $editProduct['tags'] ?? '' ?>" placeholder="new, sale, popular, featured">
          </div>

          <div style="border-top:2px dashed var(--line); padding-top:20px; margin-top:15px;"></div>

          <div style="display:flex; gap:30px; flex-wrap:wrap;">
            <div class="form-group checkbox-group" style="margin-top:0;">
              <input type="checkbox" name="active" id="active" <?= ($editProduct['active'] ?? 1) ? 'checked' : '' ?>>
              <label for="active" style="margin:0;">✅ Active / Visible on site</label>
            </div>
            <div class="form-group checkbox-group" style="margin-top:0;">
              <input type="checkbox" name="featured" id="featured" <?= ($editProduct['featured'] ?? 0) ? 'checked' : '' ?>>
              <label for="featured" style="margin:0;">⭐ Featured Product (homepage)</label>
            </div>
          </div>

          <div class="form-group">
            <label>Main Image Source</label>
            <div class="image-source-toggle">
              <label class="radio-label">
                <input type="radio" name="image_source" value="url" checked onchange="toggleImageInput(this)"> URL
              </label>
              <label class="radio-label">
                <input type="radio" name="image_source" value="upload" onchange="toggleImageInput(this)"> Upload
              </label>
            </div>
            <div id="imageInputContainer">
              <div id="imageUrlInput" class="image-input-row">
                <input type="text" name="image_url" class="input" placeholder="Paste image URL here..." value="<?= $editProduct['image_url'] ?? '' ?>">
              </div>
              <div id="imageUploadInput" class="image-input-row" style="display:none">
                <input type="file" name="image_file" class="input" accept="image/*">
              </div>
            </div>
          </div>

          <div class="form-group">
            <label>Additional Images (Optional)</label>
            <div id="additionalImages">
              <?php if ($editProduct): 
                $extraImages = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ?");
                $extraImages->execute([$editProduct['id']]);
                foreach ($extraImages->fetchAll() as $img): ?>
                  <div class="additional-image-row existing">
                    <img src="<?= url($img['image_url']) ?>" width="40" height="40" style="object-fit:cover; border-radius:4px;">
                    <input type="text" class="input" value="<?= $img['image_url'] ?>" readonly>
                    <input type="hidden" name="keep_images[]" value="<?= $img['id'] ?>">
                    <button type="button" class="btn-remove" onclick="this.parentElement.remove()">Remove</button>
                  </div>
                <?php endforeach;
              endif; ?>
            </div>
            <button type="button" class="btn small-btn" onclick="addAdditionalImageRow()">+ Add Another Image</button>
          </div>

          <div class="form-group">
            <label>Description</label>
            <textarea name="description" class="input" rows="4" required><?= $editProduct['description'] ?? '' ?></textarea>
          </div>

          <div class="form-group checkbox-group">
            <input type="checkbox" name="active" id="active" <?= ($editProduct['active'] ?? 1) ? 'checked' : '' ?>>
            <label for="active">Active / Visible</label>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn"><?= $editProduct ? 'Update Product' : 'Add Product' ?></button>
            <a href="products.php" class="btn btn-outline">Cancel</a>
          </div>
        </form>
      </div>
    </div>

    <!-- Products Table -->
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Image</th>
            <th>Product</th>
            <th>SKU</th>
            <th>Cat/Sub</th>
            <th>Price</th>
            <th>Stock</th>
            <th>Flags</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($products as $p): ?>
            <tr>
              <td style="font-weight:700;"><?= $p['id'] ?></td>
              <td><img src="<?= url($p['image_url']) ?>" width="50" height="50" style="border-radius:4px; object-fit: cover;"></td>
              <td>
                <div style="font-weight:700; color:var(--brand); max-width:220px;"><?= h($p['name']) ?></div>
                <?php if (!empty($p['slug'])): ?><div style="font-size:0.7rem; color:var(--muted);">/<?= h($p['slug']) ?></div><?php endif; ?>
              </td>
              <td>
                <?php if (!empty($p['sku'])): ?>
                  <code style="background:var(--bg-soft); padding:3px 8px; border-radius:4px; font-size:0.75rem; color:var(--brand); font-weight:700;"><?= h($p['sku']) ?></code>
                <?php else: ?>
                  <span style="color:var(--muted); font-size:0.75rem;">—</span>
                <?php endif; ?>
              </td>
              <td>
                <div style="font-weight: 700; color: var(--brand); font-size:0.85rem;"><?= h($p['category']) ?></div>
                <div style="font-size: 0.7rem; color: var(--muted);"><?= h($p['subcategory'] ?: '—') ?></div>
              </td>
              <td style="font-weight:800; color:#5D100B;"><?= h(money((float)$p['price'])) ?></td>
              <td>
                <span class="stock-badge <?= $p['stock'] < 10 ? 'low-stock' : '' ?>">
                  <?= $p['stock'] ?>
                </span>
              </td>
              <td style="min-width:120px;">
                <div style="display:flex; flex-direction:column; gap:4px;">
                  <span><?= $p['active'] ? '✅ Active' : '❌ Hidden' ?></span>
                  <?php if (!empty($p['featured'])): ?><span>⭐ Featured</span><?php endif; ?>
                  <?php if (!empty($p['meta_title'])): ?><span style="font-size:0.65rem; color:var(--muted);">🔍 SEO ✅</span><?php endif; ?>
                </div>
              </td>
              <td>
                <div class="table-actions">
                  <a href="../product.php?slug=<?= h($p['slug']) ?>" target="_blank" class="icon-btn" title="View Live" style="background:#dbeafe; color:#1e40af;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                  </a>
                  <a href="?edit=<?= $p['id'] ?>" class="icon-btn edit-btn" title="Edit">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                  </a>
                  <a href="?delete=<?= $p['id'] ?>" class="icon-btn delete-btn" onclick="return confirm('Are you sure?')" title="Delete">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>

<script>
const subcats = {
    'Cutlery': ['Swords', 'Knives', 'Daggers', 'Hunting Knives', 'Kitchen Knives', 'Folding Knives', 'Custom/Handmade Blades', 'Knife Sets', 'Accessories (Sheaths, sharpeners)'],
    'Kitchenware': ['Cookware (Pots, pans)', 'Bakeware', 'Utensils (Spatulas, ladles)', 'Storage Containers', 'Dinner Sets', 'Tea & Coffee Ware', 'Serving Items', 'Kitchen Tools (Peelers, graters)'],
    'Salt Crafts': ['Salt Lamps', 'Salt Candle Holders', 'Cooking Slabs/Plates', 'Salt Tiles & Bricks', 'Decorative Items', 'Bath & Wellness (Salt scrubs, bath salts)', 'Custom Carved Items'],
    'Footwear': ['Sneakers', 'Joggers', 'Casual Shoes', 'Formal Shoes', 'Sandals & Slippers', 'Sports Shoes', 'Kids Footwear', 'Custom Designs'],
    'Value Ceramics': ['Dinnerware Sets', 'Mugs & Cups', 'Plates & Bowls', 'Decorative Ceramics', 'Planters', 'Handmade/Artisan Pieces', 'Hotel & Bulk Supply'],
    'Engineering Parts': ['Automotive Parts', 'Machinery Components', 'Precision Parts', 'Fasteners (Bolts, nuts)', 'Metal Fabrication Items', 'Custom Manufacturing', 'Industrial Tools', 'Export Quality Parts'],
    'Project Feasibility': ['Business Feasibility Reports', 'Market Research', 'Cost Analysis', 'Financial Projections', 'Industrial Project Planning', 'Import/Export Consultancy', 'Startup Guidance', 'Custom Feasibility Reports']
};

function updateSubcategories() {
    const cat = document.getElementById('adminCategory').value;
    const subSelect = document.getElementById('adminSubcategory');
    const currentSub = '<?= $editProduct['subcategory'] ?? '' ?>';
    
    subSelect.innerHTML = '<option value="">Select Subcategory</option>';
    
    if (subcats[cat]) {
        subcats[cat].forEach(sub => {
            const opt = document.createElement('option');
            opt.value = sub;
            opt.textContent = sub;
            if (sub === currentSub) opt.selected = true;
            subSelect.appendChild(opt);
        });
    }
}

// Initialize on load
document.addEventListener('DOMContentLoaded', function() {
    updateSubcategories();
    countChars('metaTitleInput', 'metaTitleCount', 70);
    countChars('metaDescInput', 'metaDescCount', 160);
});

function countChars(inputId, countId, max) {
    const input = document.getElementById(inputId);
    const count = document.getElementById(countId);
    if (!input || !count) return;
    const len = input.value.length;
    const color = len > max ? '#dc2626' : len > (max * 0.8) ? '#d97706' : 'var(--muted)';
    count.textContent = `(${len}/${max} chars - ${len <= max ? '✅' : '⚠️ too long'})`;
    count.style.color = color;
    count.style.fontWeight = '600';
}

function autoFillSEO(nameInput) {
    const name = nameInput.value.trim();
    if (name === '') return;
    const metaTitleInput = document.getElementById('metaTitleInput');
    const metaDescInput = document.getElementById('metaDescInput');
    const keywordsInput = document.querySelector('input[name="meta_keywords"]');
    
    if (metaTitleInput && metaTitleInput.value === '') {
        const cat = document.getElementById('adminCategory').value || '';
        metaTitleInput.value = name + (cat ? ' | ' + cat : '') + ' - Resources Enterprises';
        countChars('metaTitleInput', 'metaTitleCount', 70);
    }
    if (metaDescInput && metaDescInput.value === '') {
        metaDescInput.value = 'Buy premium ' + name.toLowerCase() + ' at wholesale prices. Export quality, best price guarantee. Retail & bulk orders accepted.';
        countChars('metaDescInput', 'metaDescCount', 160);
    }
    if (keywordsInput && keywordsInput.value === '') {
        const words = name.toLowerCase().split(' ').filter(w => w.length > 2).slice(0, 5);
        keywordsInput.value = words.join(', ') + (words.length > 0 ? ', ' : '') + 'wholesale, export, premium';
    }
}

function toggleImageInput(radio) {
    const urlInput = document.getElementById('imageUrlInput');
    const uploadInput = document.getElementById('imageUploadInput');
    if (radio.value === 'url') {
        urlInput.style.display = 'block';
        uploadInput.style.display = 'none';
    } else {
        urlInput.style.display = 'none';
        uploadInput.style.display = 'block';
    }
}

function addAdditionalImageRow() {
    const container = document.getElementById('additionalImages');
    const row = document.createElement('div');
    row.className = 'additional-image-row';
    const rowId = Date.now();
    
    row.innerHTML = `
        <div class="image-input-group">
            <div class="image-source-toggle">
                <label class="radio-label">
                    <input type="radio" name="source_${rowId}" value="url" checked onchange="toggleAdditionalInput('${rowId}', 'url')"> URL
                </label>
                <label class="radio-label">
                    <input type="radio" name="source_${rowId}" value="upload" onchange="toggleAdditionalInput('${rowId}', 'upload')"> Upload
                </label>
            </div>
            <button type="button" class="btn-remove" onclick="this.parentElement.parentElement.remove()" style="margin-left:auto">&times;</button>
        </div>
        <div id="url_${rowId}" class="image-input-row">
            <input type="text" name="additional_image_urls[]" class="input" placeholder="Paste image URL here...">
        </div>
        <div id="upload_${rowId}" class="image-input-row" style="display:none">
            <input type="file" name="additional_image_files[]" class="input" accept="image/*">
        </div>
    `;
    container.appendChild(row);
}

function toggleAdditionalInput(id, type) {
    document.getElementById(`url_${id}`).style.display = type === 'url' ? 'block' : 'none';
    document.getElementById(`upload_${id}`).style.display = type === 'upload' ? 'block' : 'none';
}
</script>

<style>
/* Reusing layout from dashboard and adding specific admin styles */
.admin-layout { display: grid; grid-template-columns: 250px 1fr; min-height: calc(100vh - 90px); background: var(--bg); }
.admin-sidebar { background: var(--brand-strong); color: #fff; padding: 40px 0; }
.admin-nav { display: flex; flex-direction: column; }
.admin-nav-item { padding: 15px 30px; color: rgba(255, 255, 255, 0.7); text-decoration: none; font-weight: 600; transition: all 0.3s; border-left: 4px solid transparent; }
.admin-nav-item:hover, .admin-nav-item.active { color: var(--premium); background: rgba(255, 255, 255, 0.05); border-left-color: var(--premium); }
.admin-main { padding: 40px; }
.admin-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; border-bottom: 1px solid var(--line); padding-bottom: 20px; }

.admin-table-wrap { background: var(--card); border-radius: 12px; box-shadow: var(--shadow); overflow-x: auto; }
.admin-table { width: 100%; border-collapse: collapse; text-align: left; }
.admin-table th { background: rgba(0,0,0,0.02); padding: 15px 20px; font-size: 0.85rem; text-transform: uppercase; color: var(--muted); }
.admin-table td { padding: 15px 20px; border-bottom: 1px solid var(--line); }

.stock-badge { padding: 4px 10px; border-radius: 50px; font-weight: 700; background: rgba(0,0,0,0.05); }
.stock-badge.low-stock { background: #fee2e2; color: #b91c1c; }

.admin-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; padding: 40px; overflow-y: auto; }
.modal-content { background: var(--card); max-width: 800px; margin: 0 auto; padding: 40px; border-radius: 16px; position: relative; }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
.close-btn { font-size: 2rem; text-decoration: none; color: var(--muted); }

.image-source-toggle { display: flex; gap: 20px; margin-bottom: 15px; background: rgba(0,0,0,0.03); padding: 10px 15px; border-radius: 8px; border: 1px solid var(--line); }
.image-source-toggle.small { gap: 12px; padding: 6px 12px; margin-bottom: 8px; font-size: 0.85rem; }
.radio-label { display: flex; align-items: center; gap: 8px; font-weight: 600; cursor: pointer; color: var(--brand-strong); }
.radio-label input { width: 18px; height: 18px; accent-color: var(--brand); cursor: pointer; }
.image-input-row { animation: fadeIn 0.3s ease; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

.image-input-group { display: flex; align-items: center; gap: 15px; margin-bottom: 10px; background: rgba(0,0,0,0.02); padding: 10px; border-radius: 8px; }
.or-divider { font-size: 0.75rem; font-weight: 800; color: var(--muted); white-space: nowrap; }
.additional-image-row { margin-bottom: 15px; }
.additional-image-row.existing { display: flex; align-items: center; gap: 15px; background: #f9f9f9; padding: 10px; border-radius: 8px; margin-bottom: 10px; }
.btn-remove { background: #fee2e2; color: #b91c1c; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 0.8rem; }
.small-btn { padding: 8px 15px; font-size: 0.85rem; margin-top: 5px; }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
.checkbox-group { display: flex; align-items: center; gap: 10px; margin-top: 20px; }
.checkbox-group input { width: 20px; height: 20px; }

.table-actions { display: flex; gap: 10px; }
.icon-btn { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 8px; transition: all 0.2s; }
.edit-btn { background: #fef9c3; color: #a16207; }
.edit-btn:hover { background: #fde047; }
.delete-btn { background: #fee2e2; color: #b91c1c; }
.delete-btn:hover { background: #fecaca; }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>