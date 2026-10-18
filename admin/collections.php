<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin($pdo);

$pageTitle = 'Manage Collections';

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM collections WHERE id = ?")->execute([$id]);
    set_flash('success', 'Collection deleted.');
    redirect('collections.php');
}

$edit = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM collections WHERE id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    enforce_csrf();
    $name        = trim((string)($_POST['name'] ?? ''));
    $slugRaw     = trim((string)($_POST['slug'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $active      = isset($_POST['active']) ? 1 : 0;

    if ($name === '') {
        set_flash('error', 'Collection name is required.');
        redirect('collections.php');
    }

    $image_url = '';
    if (isset($_POST['image_source'])) {
        if ($_POST['image_source'] === 'url') {
            $image_url = trim((string)($_POST['image_url'] ?? ''));
        } elseif ($_POST['image_source'] === 'upload') {
            if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
                $u = upload_image($_FILES['image_file']);
                if ($u) $image_url = $u;
            }
        }
    }

    if ($_POST['action'] === 'update' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        if ($image_url === '') {
            $s = $pdo->prepare("SELECT image_url FROM collections WHERE id = ?");
            $s->execute([$id]);
            $image_url = (string)$s->fetchColumn();
        }
        $slug = $slugRaw !== '' ? $slugRaw : (strtolower(preg_replace('/[^a-z0-9]+/', '-', $name)) . '-' . rand(100,999));
        $stmt = $pdo->prepare("UPDATE collections SET name=?, slug=?, description=?, image_url=?, active=? WHERE id=?");
        $stmt->execute([$name, $slug, $description, $image_url, $active, $id]);
        set_flash('success', 'Collection updated.');
    } else {
        $slug = $slugRaw !== '' ? $slugRaw.'-'.rand(100,999) : (strtolower(preg_replace('/[^a-z0-9]+/', '-', $name)) . '-' . rand(100,9999));
        $stmt = $pdo->prepare("INSERT INTO collections (name, slug, description, image_url, active, created_at) VALUES (?,?,?,?,?,NOW())");
        $stmt->execute([$name, $slug, $description, $image_url, $active]);
        set_flash('success', 'Collection added.');
    }
    redirect('collections.php');
}

$collections = $pdo->query("SELECT * FROM collections ORDER BY id DESC")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<div class="admin-layout">
  <aside class="admin-sidebar">
    <nav class="admin-nav">
      <a href="dashboard.php" class="admin-nav-item">Dashboard</a>
      <a href="orders.php" class="admin-nav-item">Manage Orders</a>
      <a href="products.php" class="admin-nav-item">Manage Products</a>
      <a href="payment-methods.php" class="admin-nav-item">Payment Methods</a>
      <a href="currencies.php" class="admin-nav-item">Currencies</a>
      <a href="users.php" class="admin-nav-item">Manage Users</a>
      <a href="reviews.php" class="admin-nav-item">Manage Reviews</a>
      <a href="collections.php" class="admin-nav-item active">Collections</a>
      <a href="messages.php" class="admin-nav-item">Messages</a>
      <a href="settings.php" class="admin-nav-item">Settings</a>
    </nav>
  </aside>
  <main class="admin-main">
    <header class="admin-header" style="display:flex;justify-content:space-between;align-items:center;">
      <h1>Manage Collections</h1>
      <button onclick="document.getElementById('collForm').style.display='block'" class="btn">+ Add Collection</button>
    </header>

    <?php
      $ok = get_flash('success'); $er = get_flash('error');
      if ($ok) echo '<div class="flash success" style="background:#D4EDDA;color:#155724;padding:14px 20px;border-radius:8px;margin-bottom:20px;font-weight:600;">'.h($ok).'</div>';
      if ($er) echo '<div class="flash error" style="background:#F8D7DA;color:#721C24;padding:14px 20px;border-radius:8px;margin-bottom:20px;font-weight:600;">'.h($er).'</div>';
    ?>

    <div id="collForm" class="admin-modal" style="<?= $edit ? 'display:block' : 'display:none'; ?>position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:2000;padding:40px;overflow-y:auto;">
      <div class="modal-content" style="background:var(--card);max-width:700px;margin:0 auto;padding:30px;border-radius:16px;position:relative;">
        <header class="modal-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
          <h3><?= $edit ? 'Edit Collection' : 'Add New Collection' ?></h3>
          <a href="collections.php" class="close-btn" style="font-size:2rem;text-decoration:none;color:var(--muted);">&times;</a>
        </header>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="action" value="<?= $edit ? 'update' : 'add' ?>">
          <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
            <div class="form-group" style="grid-column: span 1;">
              <label class="input-label" style="font-weight:700;color:var(--brand);margin-bottom:8px;display:block;">Name *</label>
              <input name="name" class="input" value="<?= h($edit['name'] ?? '') ?>" required style="width:100%;padding:11px 15px;border:2px solid var(--line);border-radius:10px;">
            </div>
            <div class="form-group" style="grid-column: span 1;">
              <label class="input-label" style="font-weight:700;color:var(--brand);margin-bottom:8px;display:block;">URL Slug <small style="color:var(--muted);">(edit only)</small></label>
              <input name="slug" class="input" value="<?= h($edit['slug'] ?? '') ?>" placeholder="auto-generated" style="width:100%;padding:11px 15px;border:2px solid var(--line);border-radius:10px;">
            </div>
          </div>
          <div class="form-group" style="margin-top:15px;">
            <label class="input-label" style="font-weight:700;color:var(--brand);margin-bottom:8px;display:block;">Description</label>
            <textarea name="description" class="input" rows="3" style="width:100%;padding:11px 15px;border:2px solid var(--line);border-radius:10px;"><?= h($edit['description'] ?? '') ?></textarea>
          </div>
          <div class="form-group checkbox-group" style="margin:15px 0;display:flex;align-items:center;gap:10px;">
            <input type="checkbox" name="active" id="active" <?= (($edit['active'] ?? 1) ? 'checked' : '') ?> style="width:20px;height:20px;">
            <label for="active">Active / Visible</label>
          </div>
          <div class="form-group" style="margin-top:10px;">
            <label class="input-label" style="font-weight:700;color:var(--brand);margin-bottom:8px;display:block;">Image</label>
            <div class="image-source-toggle" style="display:flex;gap:20px;margin-bottom:12px;background:rgba(0,0,0,0.03);padding:10px 15px;border-radius:8px;border:1px solid var(--line);">
              <label class="radio-label"><input type="radio" name="image_source" value="url" checked> URL</label>
              <label class="radio-label"><input type="radio" name="image_source" value="upload"> Upload</label>
            </div>
            <input name="image_url" class="input" value="<?= h($edit['image_url'] ?? '') ?>" placeholder="https://... image URL" style="width:100%;padding:11px 15px;border:2px solid var(--line);border-radius:10px;margin-bottom:10px;">
            <input type="file" name="image_file" accept="image/*" class="input" style="width:100%;padding:11px 15px;border:2px solid var(--line);border-radius:10px;">
          </div>
          <div style="margin-top:20px;display:flex;gap:10px;">
            <button type="submit" class="btn" style="background:var(--brand);color:#fff;border:none;padding:12px 28px;font-weight:700;border-radius:12px;cursor:pointer;"><?= $edit ? 'Update' : 'Create' ?> Collection</button>
            <a href="collections.php" class="btn" style="background:var(--muted);color:#fff;text-decoration:none;padding:12px 28px;border-radius:12px;font-weight:700;">Cancel</a>
          </div>
        </form>
      </div>
    </div>

    <?php if (empty($collections)): ?>
      <div class="empty-state" style="background:var(--card);border-radius:12px;padding:60px 30px;text-align:center;box-shadow:var(--shadow);">
        <div style="font-size:4rem;margin-bottom:20px;opacity:0.3;">🎨</div>
        <h3 style="font-family:'Playfair Display',serif;font-size:1.8rem;margin-bottom:10px;">No Collections Yet</h3>
        <p class="muted">Click "+ Add Collection" to create your first collection.</p>
      </div>
    <?php else: ?>
      <div class="admin-table-wrap" style="background:var(--card);border-radius:12px;box-shadow:var(--shadow);overflow-x:auto;">
        <table class="admin-table" style="width:100%;border-collapse:collapse;text-align:left;">
          <thead>
            <tr style="background:rgba(0,0,0,0.02);">
              <th style="padding:15px 20px;font-size:0.85rem;text-transform:uppercase;color:var(--muted);">ID</th>
              <th style="padding:15px 20px;font-size:0.85rem;text-transform:uppercase;color:var(--muted);">Image</th>
              <th style="padding:15px 20px;font-size:0.85rem;text-transform:uppercase;color:var(--muted);">Name</th>
              <th style="padding:15px 20px;font-size:0.85rem;text-transform:uppercase;color:var(--muted);">Slug</th>
              <th style="padding:15px 20px;font-size:0.85rem;text-transform:uppercase;color:var(--muted);">Status</th>
              <th style="padding:15px 20px;font-size:0.85rem;text-transform:uppercase;color:var(--muted);">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($collections as $c): ?>
            <tr style="border-bottom:1px solid var(--line);">
              <td style="padding:15px 20px;font-weight:700;"><?= (int)$c['id'] ?></td>
              <td style="padding:15px 20px;">
                <?php if (!empty($c['image_url'])): ?>
                  <img src="<?= url($c['image_url']) ?>" width="60" height="60" style="object-fit:cover;border-radius:8px;">
                <?php else: ?>
                  <span style="color:var(--muted);font-size:0.8rem;">— No image —</span>
                <?php endif; ?>
              </td>
              <td style="padding:15px 20px;">
                <div style="font-weight:700;color:var(--brand);"><?= h($c['name']) ?></div>
                <?php if (!empty($c['description'])): ?><div style="color:var(--muted);font-size:0.8rem;max-width:300px;"><?= h(substr($c['description'],0,100).(strlen($c['description'])>100?'…':'')) ?></div><?php endif; ?>
              </td>
              <td style="padding:15px 20px;font-size:0.85rem;color:var(--muted);">/<?= h($c['slug']) ?></td>
              <td style="padding:15px 20px;">
                <span class="badge" style="padding:5px 12px;border-radius:30px;font-size:0.72rem;font-weight:800;text-transform:uppercase;<?= !empty($c['active']) ? 'background:#D4EDDA;color:#155724;' : 'background:#F8D7DA;color:#721C24;' ?>">
                  <?= !empty($c['active']) ? 'Active' : 'Hidden' ?>
                </span>
              </td>
              <td style="padding:15px 20px;">
                <div style="display:flex;gap:8px;">
                  <a href="?edit=<?= (int)$c['id'] ?>" class="icon-btn edit-btn" title="Edit" style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:8px;background:#fef9c3;color:#a16207;">✎</a>
                  <a href="?delete=<?= (int)$c['id'] ?>" class="icon-btn delete-btn" title="Delete" onclick="return confirm('Delete collection?')" style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:8px;background:#fee2e2;color:#b91c1c;">🗑</a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </main>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
