<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$slug = $_GET['slug'] ?? '';
$stmt = db()->prepare('SELECT * FROM categories WHERE slug = ? AND is_active = 1');
$stmt->execute([$slug]);
$category = $stmt->fetch();
if (!$category) {
    http_response_code(404);
    $pageTitle = 'Category not found';
    require __DIR__ . '/includes/header.php';
    echo '<div class="wrap section"><div class="empty-state"><h2>Category not found</h2><p>That category may have been removed.</p><a class="btn btn-primary" href="/">Back to shop</a></div></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$sort = $_GET['sort'] ?? 'newest';
$sortSql = match ($sort) {
    'price_asc' => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'name' => 'p.name ASC',
    default => 'p.created_at DESC',
};

$perPage = 12;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$countStmt = db()->prepare('SELECT COUNT(*) FROM products WHERE category_id = ? AND is_active = 1');
$countStmt->execute([$category['id']]);
$total = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$stmt = db()->prepare(
    "SELECT p.*, c.name AS category_name FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.category_id = ? AND p.is_active = 1
     ORDER BY $sortSql LIMIT $perPage OFFSET $offset"
);
$stmt->execute([$category['id']]);
$products = $stmt->fetchAll();

$__user = current_user();
$__favIds = $__user ? favorite_ids_for_user($__user['id']) : [];
$pageTitle = $category['name'];
require __DIR__ . '/includes/header.php';
?>

<div class="wrap">
  <div class="breadcrumb"><a href="/">Home</a> / <?= e($category['name']) ?></div>
</div>

<div class="page-header wrap">
  <span class="eyebrow">Category</span>
  <h1><?= e($category['name']) ?></h1>
  <?php if ($category['description']): ?><p class="prose"><?= e($category['description']) ?></p><?php endif; ?>
</div>

<div class="wrap">
  <div class="filter-bar">
    <span class="count"><?= $total ?> product<?= $total === 1 ? '' : 's' ?></span>
    <form method="get">
      <input type="hidden" name="slug" value="<?= e($slug) ?>">
      <select name="sort" onchange="this.form.submit()">
        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: low to high</option>
        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
        <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name: A–Z</option>
      </select>
    </form>
  </div>

  <?php if ($products): ?>
    <div class="product-grid">
      <?php foreach ($products as $p): include __DIR__ . '/includes/product_card.php'; endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty-state">
      <div class="icon">🗂️</div>
      <h2>No products here yet</h2>
      <p>Check back soon — we're stocking this category.</p>
    </div>
  <?php endif; ?>

  <?php if ($totalPages > 1): ?>
    <div class="pagination">
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <?php if ($i === $page): ?>
          <span class="current"><?= $i ?></span>
        <?php else: ?>
          <a href="?slug=<?= e($slug) ?>&sort=<?= e($sort) ?>&page=<?= $i ?>"><?= $i ?></a>
        <?php endif; ?>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
