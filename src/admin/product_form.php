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
    $weightGrams = (int) ($_POST['weight_grams'] ?? 500);
    $heightMm = $_POST['height_mm'] !== '' ? (int) $_POST['height_mm'] : null;
    $widthMm = $_POST['width_mm'] !== '' ? (int) $_POST['width_mm'] : null;
    $depthMm = $_POST['depth_mm'] !== '' ? (int) $_POST['depth_mm'] : null;
    $color = trim($_POST['color'] ?? '');
    $youtubeUrl = trim($_POST['youtube_url'] ?? '');
    $isActive = !empty($_POST['is_active']) ? 1 : 0;
    $isFeatured = !empty($_POST['is_featured']) ? 1 : 0;

    if (strlen($name) < 2) $errors[] = 'Please enter a product name.';
    if ($price <= 0) $errors[] = 'Please enter a valid price.';
    if ($stock < 0) $errors[] = 'Stock cannot be negative.';
    if ($weightGrams <= 0) $errors[] = 'Please enter a valid weight in grams.';
    if ($youtubeUrl !== '' && !is_youtube_url($youtubeUrl)) $errors[] = 'YouTube link must be a youtube.com or youtu.be URL.';

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
                    'UPDATE products SET category_id=?, name=?, slug=?, sku=?, short_desc=?, description=?, price=?, compare_price=?, stock=?, weight_grams=?, height_mm=?, width_mm=?, depth_mm=?, color=?, image_main=?, youtube_url=?, is_active=?, is_featured=? WHERE id=?'
                )->execute([$categoryId, $name, $slug, $sku ?: null, $shortDesc ?: null, $description ?: null, $price, $comparePrice, $stock, $weightGrams, $heightMm, $widthMm, $depthMm, $color ?: null, $mainImage, $youtubeUrl ?: null, $isActive, $isFeatured, $product['id']]);
                $productId = $product['id'];
                flash_set('success', 'Product updated.');
            } else {
                db()->prepare(
                    'INSERT INTO products (category_id, name, slug, sku, short_desc, description, price, compare_price, stock, weight_grams, height_mm, width_mm, depth_mm, color, image_main, youtube_url, is_active, is_featured) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                )->execute([$categoryId, $name, $slug, $sku ?: null, $shortDesc ?: null, $description ?: null, $price, $comparePrice, $stock, $weightGrams, $heightMm, $widthMm, $depthMm, $color ?: null, $mainImage, $youtubeUrl ?: null, $isActive, $isFeatured]);
                $productId = (int) db()->lastInsertId();
                flash_set('success', 'Product created.');
            }

            // Variants: existing rows carry variant_id[]; blank color+size = skip;
            // "variant_delete[id]" checkboxes remove a row instead of saving it.
            if (isset($_POST['variant_color']) && is_array($_POST['variant_color'])) {
                $vIds = $_POST['variant_id'] ?? [];
                $vColors = $_POST['variant_color'];
                $vSizes = $_POST['variant_size'] ?? [];
                $vSkus = $_POST['variant_sku'] ?? [];
                $vDeltas = $_POST['variant_price_delta'] ?? [];
                $vStocks = $_POST['variant_stock'] ?? [];
                $vDelete = $_POST['variant_delete'] ?? [];

                $upd = db()->prepare('UPDATE product_variants SET color=?, size=?, sku=?, price_delta=?, stock=?, sort_order=? WHERE id=? AND product_id=?');
                $ins = db()->prepare('INSERT INTO product_variants (product_id, color, size, sku, price_delta, stock, sort_order) VALUES (?,?,?,?,?,?,?)');
                $del = db()->prepare('DELETE FROM product_variants WHERE id=? AND product_id=?');

                foreach ($vColors as $i => $vColor) {
                    $existingId = (int) ($vIds[$i] ?? 0);
                    if (!empty($vDelete[$i]) && $existingId) {
                        $del->execute([$existingId, $productId]);
                        continue;
                    }
                    $vColor = trim($vColor);
                    $vSize = trim($vSizes[$i] ?? '');
                    if ($vColor === '' && $vSize === '') continue; // blank row, ignore

                    $vSku = trim($vSkus[$i] ?? '');
                    $vDelta = (float) ($vDeltas[$i] ?? 0);
                    $vStock = max(0, (int) ($vStocks[$i] ?? 0));

                    if ($existingId) {
                        $upd->execute([$vColor ?: null, $vSize ?: null, $vSku ?: null, $vDelta, $vStock, $i, $existingId, $productId]);
                    } else {
                        $ins->execute([$productId, $vColor ?: null, $vSize ?: null, $vSku ?: null, $vDelta, $vStock, $i]);
                    }
                }
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
$variants = [];
if ($product) {
    $gStmt = db()->prepare('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order');
    $gStmt->execute([$product['id']]);
    $gallery = $gStmt->fetchAll();
    $variants = product_variants_for((int) $product['id'], false);
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
      <div class="field-row">
        <div class="field" style="max-width:220px;"><label for="stock">Stock quantity</label><input type="number" min="0" id="stock" name="stock" required value="<?= e((string)($product['stock'] ?? ($_POST['stock'] ?? 0))) ?>"></div>
        <div class="field" style="max-width:220px;"><label for="weight_grams">Weight (grams)</label><input type="number" min="1" id="weight_grams" name="weight_grams" required value="<?= e((string)($product['weight_grams'] ?? ($_POST['weight_grams'] ?? 500))) ?>"><div class="hint">Used to calculate the shipping surcharge over 1kg.</div></div>
      </div>
      <div class="field-row">
        <div class="field" style="max-width:150px;"><label for="height_mm">Height (mm)</label><input type="number" min="0" id="height_mm" name="height_mm" value="<?= e((string)($product['height_mm'] ?? ($_POST['height_mm'] ?? ''))) ?>"></div>
        <div class="field" style="max-width:150px;"><label for="width_mm">Width (mm)</label><input type="number" min="0" id="width_mm" name="width_mm" value="<?= e((string)($product['width_mm'] ?? ($_POST['width_mm'] ?? ''))) ?>"></div>
        <div class="field" style="max-width:150px;"><label for="depth_mm">Depth (mm)</label><input type="number" min="0" id="depth_mm" name="depth_mm" value="<?= e((string)($product['depth_mm'] ?? ($_POST['depth_mm'] ?? ''))) ?>"></div>
        <div class="field"><label for="color">Color</label><input id="color" name="color" placeholder="e.g. Chestnut Brown" value="<?= e($product['color'] ?? ($_POST['color'] ?? '')) ?>"></div>
      </div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Variants <span style="font-weight:400;color:var(--ink-faint);font-size:0.85rem;">(optional — different colors or sizes)</span></h2></div>
    <div class="panel-body">
      <div class="hint" style="margin-bottom:12px;">Leave empty if this product doesn't come in multiple colors/sizes. Add a row per option (e.g. "Black / Large"). Price adjustment is added to the base price; leave 0 if the price doesn't change. If any active variant exists, shoppers must pick one before adding to cart.</div>
      <div id="variantRows">
        <?php if (!$variants) { $variants = [['id' => '', 'color' => '', 'size' => '', 'sku' => '', 'price_delta' => 0, 'stock' => 0]]; } ?>
        <?php foreach ($variants as $i => $v): ?>
          <div class="variant-row" style="display:flex;gap:10px;align-items:flex-end;margin-bottom:10px;flex-wrap:wrap;">
            <input type="hidden" name="variant_id[]" value="<?= e((string)($v['id'] ?? '')) ?>">
            <div class="field" style="max-width:140px;margin:0;"><label>Color</label><input name="variant_color[]" value="<?= e($v['color'] ?? '') ?>" placeholder="Black"></div>
            <div class="field" style="max-width:120px;margin:0;"><label>Size</label><input name="variant_size[]" value="<?= e($v['size'] ?? '') ?>" placeholder="Large"></div>
            <div class="field" style="max-width:120px;margin:0;"><label>SKU</label><input name="variant_sku[]" value="<?= e($v['sku'] ?? '') ?>"></div>
            <div class="field" style="max-width:130px;margin:0;"><label>Price adj.</label><input type="number" step="0.01" name="variant_price_delta[]" value="<?= e((string)($v['price_delta'] ?? 0)) ?>"></div>
            <div class="field" style="max-width:100px;margin:0;"><label>Stock</label><input type="number" min="0" name="variant_stock[]" value="<?= e((string)($v['stock'] ?? 0)) ?>"></div>
            <?php if (!empty($v['id'])): ?>
              <label style="display:flex;align-items:center;gap:5px;font-weight:400;font-size:0.82rem;padding-bottom:9px;">
                <input type="checkbox" name="variant_delete[<?= $i ?>]" value="1"> Remove
              </label>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="button" id="addVariantRow" class="btn btn-outline btn-sm">+ Add another option</button>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Video</h2></div>
    <div class="panel-body">
      <div class="field">
        <label for="youtube_url">YouTube video link (optional)</label>
        <input type="url" id="youtube_url" name="youtube_url" placeholder="https://www.youtube.com/watch?v=..." value="<?= e($product['youtube_url'] ?? ($_POST['youtube_url'] ?? '')) ?>">
        <div class="hint">If set, a "Watch video" button appears on the product page and a play icon appears on the product card.</div>
      </div>
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

<script>
document.getElementById('addVariantRow').addEventListener('click', function () {
  var container = document.getElementById('variantRows');
  var row = document.createElement('div');
  row.className = 'variant-row';
  row.style.cssText = 'display:flex;gap:10px;align-items:flex-end;margin-bottom:10px;flex-wrap:wrap;';
  row.innerHTML =
    '<input type="hidden" name="variant_id[]" value="">' +
    '<div class="field" style="max-width:140px;margin:0;"><label>Color</label><input name="variant_color[]" placeholder="Black"></div>' +
    '<div class="field" style="max-width:120px;margin:0;"><label>Size</label><input name="variant_size[]" placeholder="Large"></div>' +
    '<div class="field" style="max-width:120px;margin:0;"><label>SKU</label><input name="variant_sku[]"></div>' +
    '<div class="field" style="max-width:130px;margin:0;"><label>Price adj.</label><input type="number" step="0.01" name="variant_price_delta[]" value="0"></div>' +
    '<div class="field" style="max-width:100px;margin:0;"><label>Stock</label><input type="number" min="0" name="variant_stock[]" value="0"></div>';
  container.appendChild(row);
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
