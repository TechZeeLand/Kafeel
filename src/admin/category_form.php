<?php
require_once __DIR__ . '/../includes/functions.php';

$id = (int) ($_GET['id'] ?? 0);
$category = null;
if ($id) {
    $stmt = db()->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([$id]);
    $category = $stmt->fetch();
    if (!$category) { flash_set('error', 'Category not found.'); redirect('/admin/categories.php'); }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $sortOrder = (int) ($_POST['sort_order'] ?? 0);
    $isActive = !empty($_POST['is_active']) ? 1 : 0;

    if (strlen($name) < 2) $errors[] = 'Please enter a category name.';

    if (!$errors) {
        $slug = slugify($name);
        $baseSlug = $slug; $n = 1;
        while (true) {
            $check = db()->prepare('SELECT id FROM categories WHERE slug = ? AND id != ?');
            $check->execute([$slug, $id ?: 0]);
            if (!$check->fetch()) break;
            $slug = $baseSlug . '-' . (++$n);
        }

        try {
            $image = $category['image'] ?? null;
            $uploaded = handle_product_image_upload('image');
            if ($uploaded) $image = $uploaded;

            if ($category) {
                db()->prepare('UPDATE categories SET name=?, slug=?, description=?, image=?, sort_order=?, is_active=? WHERE id=?')
                    ->execute([$name, $slug, $description ?: null, $image, $sortOrder, $isActive, $category['id']]);
                flash_set('success', 'Category updated.');
            } else {
                db()->prepare('INSERT INTO categories (name, slug, description, image, sort_order, is_active) VALUES (?,?,?,?,?,?)')
                    ->execute([$name, $slug, $description ?: null, $image, $sortOrder, $isActive]);
                flash_set('success', 'Category created.');
            }
            redirect('/admin/categories.php');
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$pageTitle = $category ? 'Edit category' : 'Add category';
require __DIR__ . '/includes/header.php';
?>

<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<form method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="panel">
    <div class="panel-body">
      <div class="field"><label for="name">Category name</label><input id="name" name="name" required value="<?= e($category['name'] ?? ($_POST['name'] ?? '')) ?>"></div>
      <div class="field"><label for="description">Description</label><textarea id="description" name="description" rows="3"><?= e($category['description'] ?? ($_POST['description'] ?? '')) ?></textarea></div>
      <div class="field-row">
        <div class="field"><label for="sort_order">Sort order</label><input type="number" id="sort_order" name="sort_order" value="<?= e((string)($category['sort_order'] ?? 0)) ?>"><div class="hint">Lower numbers appear first in menus.</div></div>
        <div class="field">
          <label>&nbsp;</label>
          <label style="display:flex;align-items:center;gap:6px;font-weight:400;padding-top:10px;"><input type="checkbox" name="is_active" <?= ($category['is_active'] ?? 1) ? 'checked' : '' ?>> Active (visible in store)</label>
        </div>
      </div>
      <div class="field">
        <label for="image">Category image</label>
        <?php if (!empty($category['image'])): ?><img src="<?= e($category['image']) ?>" style="width:100px;height:75px;object-fit:cover;border-radius:6px;border:1px solid var(--line);margin-bottom:10px;"><?php endif; ?>
        <input type="file" id="image" name="image" accept="image/*">
        <div class="hint">Used on the homepage category tiles. Recommend a 4:3 landscape photo.</div>
      </div>
    </div>
  </div>
  <div class="form-actions">
    <button type="submit" class="btn btn-primary"><?= $category ? 'Save changes' : 'Create category' ?></button>
    <a href="/admin/categories.php" class="btn btn-outline">Cancel</a>
  </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
