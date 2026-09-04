<?php
require_once __DIR__ . '/../../includes/admin_auth.php';
require_admin();
$__admin = current_admin();
$__path = basename($_SERVER['SCRIPT_NAME']);
function nav_active(string $file, string $current): string {
    return $file === $current ? 'active' : '';
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= isset($pageTitle) ? e($pageTitle) . ' — Admin' : 'Admin' ?> · <?= e(SITE_NAME) ?></title>
<link rel="icon" href="/assets/img/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<div class="admin-shell">
  <aside class="admin-sidebar">
    <a href="/admin/index.php" class="brand">⚙ <?= e(SITE_NAME) ?></a>
    <nav>
      <a href="/admin/index.php" class="<?= nav_active('index.php', $__path) ?>">Dashboard</a>
      <div class="section-label">Catalog</div>
      <a href="/admin/products.php" class="<?= nav_active('products.php', $__path) === 'active' || $__path === 'product_form.php' ? 'active' : '' ?>">Products</a>
      <a href="/admin/categories.php" class="<?= nav_active('categories.php', $__path) === 'active' || $__path === 'category_form.php' ? 'active' : '' ?>">Categories</a>
      <div class="section-label">Sales</div>
      <a href="/admin/orders.php" class="<?= $__path === 'orders.php' || $__path === 'order_detail.php' ? 'active' : '' ?>">Orders</a>
      <div class="section-label">People</div>
      <a href="/admin/users.php" class="<?= nav_active('users.php', $__path) ?>">Customers</a>
      <div class="section-label">Appearance</div>
      <a href="/admin/theme_settings.php" class="<?= nav_active('theme_settings.php', $__path) ?>">Theme & seasonal effects</a>
    </nav>
  </aside>

  <div class="admin-main">
    <div class="admin-topbar">
      <h1><?= e($pageTitle ?? 'Admin') ?></h1>
      <div class="admin-user">
        <span>Signed in as <strong><?= e($__admin['name']) ?></strong></span>
        <a href="/" target="_blank">View store ↗</a>
        <a href="/admin/logout.php">Log out</a>
      </div>
    </div>
    <div class="admin-content">
      <?php $__flashes = flash_get(); foreach ($__flashes as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
      <?php endforeach; ?>
