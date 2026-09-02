<?php
require_once __DIR__ . '/../includes/functions.php';

$id = (int) ($_GET['id'] ?? 0);
$product = null;
if ($id) {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) { flash_set('error', 'Product not found.'); redirect('/admin/products.php'); }
}

$categories = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $name = trim($_POST['name'] ?? '');
    $categoryId = (int) ($_POST['category_id'] ?? 0) ?: null;
    $sku = trim($_POST['sku'] ?? '');
    $shortDesc = trim($_POST['short_desc'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float) ($_POST['price'] ?? 0);
    $comparePrice = $_POST['compare_price'] !== '' ? (float) $_POST['compare_price'] : null;
    $stock = (int) ($_POST['stock'] ?? 0);
    $isActive = !empty($_POST['is_active']) ? 1 : 0;
    $isFeatured = !empty($_POST['is_featured']) ? 1 : 0;

    if (strlen($name) < 2) $errors[] = 'Please enter a product name.';
    if ($price <= 0) $errors[] = 'Please enter a valid price.';
    if ($stock < 0) $errors[] = 'Stock cannot be negative.';

    if (!$errors) {
        $slug = slugify($name);
        // ensure slug uniqueness
        $baseSlug = $slug; $n = 1;
        while (true) {
            $check = db()->prepare('SELECT id FROM products WHERE slug = ? AND id != ?');
            $check->execute([$slug, $id ?: 0]);
            if (!$check->fetch()) break;
            $slug = $baseSlug . '-' . (++$n);
        }

        try {
            $mainImage = $product['image_main'] ?? null;
            $uploaded = handle_product_image_upload('image_main');
            if ($uploaded) $mainImage = $uploaded;

            if ($product) {
                db()->prepare(
                    'UPDATE products SET category_id=?, name=?, slug=?, sku=?, short_desc=?, description=?, price=?, compare_price=?, stock=?, image_main=?, is_active=?, is_featured=? WHERE id=?'
                )->execute([$categoryId, $name, $slug, $sku ?: null, $shortDesc ?: null, $description ?: null, $price, $comparePrice, $stock, $mainImage, $isActive, $isFeatured, $product['id']]);
                $productId = $product['id'];
                flash_set('success', 'Product updated.');
            } else {
                db()->prepare(
                    'INSERT INTO products (category_id, name, slug, sku, short_desc, description, price, compare_price, stock, image_main, is_active, is_featured) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([$categoryId, $name, $slug, $sku ?: null, $shortDesc ?: null, $description ?: null, $price, $comparePrice, $stock, $mainImage, $isActive, $isFeatured]);
                $productId = (int) db()->lastInsertId();
                flash_set('success', 'Product created.');
            }

            // Additional gallery images (multi-upload)
            if (!empty($_FILES['gallery_images']) && is_array($_FILES['gallery_images']['name'])) {
                $count = count($_FILES['gallery_images']['name']);
                for ($i = 0; $i < $count; $i++) {
                    if ($_FILES['gallery_images']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                    $singleFile = [
                        'name' => $_FILES['gallery_images']['name'][$i],
                        'type' => $_FILES['gallery_images']['type'][$i],
                        'tmp_name' => $_FILES['gallery_images']['tmp_name'][$i],
                        'error' => $_FILES['gallery_images']['error'][$i],
                        'size' => $_FILES['gallery_images']['size'][$i],
                    ];
                    $_FILES['__single'] = $singleFile;
                    $path = handle_product_image_upload('__single');
                    if ($path) {
                        db()->prepare('INSERT INTO product_images (product_id, image_path, sort_order) VALUES (?,?,?)')
                            ->execute([$productId, $path, $i]);
                    }
                }
            }

            redirect('/admin/product_form.php?id=' . $productId);
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$gallery = [];
if ($product) {
    $gStmt = db()->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order');
    $gStmt->execute([$product['id']]);
    $gallery = $gStmt->fetchAll();
}

$pageTitle = $product ? 'Edit product' : 'Add product';
require __DIR__ . '/includes/header.php';
?>

<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<form method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="panel">
    <div class="panel-head"><h2>Basic information</h2></div>
    <div class="panel-body">
      <div class="field-row">
        <div class="field"><label for="name">Product name</label><input id="name" name="name" required value="<?= e($product['name'] ?? ($_POST['name'] ?? '')) ?>"></div>
        <div class="field"><label for="sku">SKU</label><input id="sku" name="sku" value="<?= e($product['sku'] ?? ($_POST['sku'] ?? '')) ?>"></div>
      </div>
      <div class="field-row">
        <div class="field">
          <label for="category_id">Category</label>
          <select id="category_id" name="category_id">
            <option value="">— None —</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= (($product['category_id'] ?? null) == $c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>&nbsp;</label>
          <div style="display:flex;gap:20px;padding-top:10px;">
            <label style="display:flex;align-items:center;gap:6px;font-weight:400;"><input type="checkbox" name="is_active" <?= ($product['is_active'] ?? 1) ? 'checked' : '' ?>> Active (visible in store)</label>
            <label style="display:flex;align-items:center;gap:6px;font-weight:400;"><input type="checkbox" name="is_featured" <?= !empty($product['is_featured']) ? 'checked' : '' ?>> Featured</label>
          </div>
        </div>
      </div>
      <div class="field"><label for="short_desc">Short description</label><input id="short_desc" name="short_desc" maxlength="255" value="<?= e($product['short_desc'] ?? ($_POST['short_desc'] ?? '')) ?>"><div class="hint">Shown on product cards and search results.</div></div>
      <div class="field"><label for="description">Full description</label><textarea id="description" name="description" rows="6"><?= e($product['description'] ?? ($_POST['description'] ?? '')) ?></textarea></div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Pricing & inventory</h2></div>
    <div class="panel-body">
      <div class="field-row">
        <div class="field"><label for="price">Price</label><input type="number" step="0.01" min="0" id="price" name="price" required value="<?= e((string)($product['price'] ?? ($_POST['price'] ?? ''))) ?>"></div>
        <div class="field"><label for="compare_price">Compare-at price (optional)</label><input type="number" step="0.01" min="0" id="compare_price" name="compare_price" value="<?= e((string)($product['compare_price'] ?? ($_POST['compare_price'] ?? ''))) ?>"><div class="hint">Shown crossed out to indicate a sale.</div></div>
      </div>
      <div class="field" style="max-width:220px;"><label for="stock">Stock quantity</label><input type="number" min="0" id="stock" name="stock" required value="<?= e((string)($product['stock'] ?? ($_POST['stock'] ?? 0))) ?>"></div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Images</h2></div>
    <div class="panel-body">
      <div class="field">
        <label for="image_main">Main image</label>
        <?php if (!empty($product['image_main'])): ?><img src="<?= e($product['image_main']) ?>" style="width:100px;height:100px;object-fit:cover;border-radius:6px;border:1px solid var(--line);margin-bottom:10px;"><?php endif; ?>
        <input type="file" id="image_main" name="image_main" accept="image/*">
        <div class="hint">JPG, PNG, WEBP or GIF, up to 5MB. Uploading a new file replaces the current image.</div>
      </div>
      <?php if ($gallery): ?>
        <div class="field">
          <label>Additional gallery images</label>
          <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <?php foreach ($gallery as $g): ?>
              <div style="position:relative;">
                <img src="<?= e($g['image_path']) ?>" style="width:80px;height:80px;object-fit:cover;border-radius:6px;border:1px solid var(--line);">
                <form method="post" action="/admin/product_image_delete.php" style="position:absolute;top:-8px;right:-8px;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="image_id" value="<?= (int)$g['id'] ?>">
                  <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                  <button class="btn btn-danger btn-sm" style="padding:2px 7px;border-radius:50%;" title="Remove">✕</button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
      <div class="field">
        <label for="gallery_images">Add gallery images</label>
        <input type="file" id="gallery_images" name="gallery_images[]" accept="image/*" multiple>
        <div class="hint">You can select multiple files. Save the product first to add gallery photos.</div>
      </div>
    </div>
  </div>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary"><?= $product ? 'Save changes' : 'Create product' ?></button>
    <a href="/admin/products.php" class="btn btn-outline">Cancel</a>
  </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
