<?php
require_once __DIR__ . '/auth.php';
$__user = current_user();
$__cartCount = cart_count();
$__categories = db()->query('SELECT id, name, slug FROM categories WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();
$__currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? e($pageTitle) . ' — ' . e(SITE_NAME) : e(SITE_NAME) ?></title>
<meta name="description" content="<?= e($pageDescription ?? (SITE_NAME . ' — EDC gear, bags and full-grain leather goods.')) ?>">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<link rel="icon" href="/assets/img/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<div class="topbar">
  <div class="wrap">
    <span>Free shipping on orders over <?= money(FREE_SHIPPING_THRESHOLD) ?></span>
    <span>Cash on delivery available nationwide</span>
  </div>
</div>

<header class="site-header">
  <div class="wrap header-row">
    <button class="nav-toggle" id="navToggle" aria-label="Open menu"><span></span></button>
    <a href="/" class="brand"><span class="mark">خ</span> <?= e(SITE_NAME) ?></a>

    <nav class="main-nav">
      <a href="/" class="<?= $__currentPath === '/' || $__currentPath === '/index.php' ? 'active' : '' ?>">Home</a>
      <?php foreach ($__categories as $c): ?>
        <a href="/category.php?slug=<?= e($c['slug']) ?>"><?= e($c['name']) ?></a>
      <?php endforeach; ?>
      <a href="/about.php">About</a>
      <a href="/contact.php">Contact</a>
    </nav>

    <form class="search-form" action="/search.php" method="get">
      <input type="search" name="q" placeholder="Search products…" value="<?= e($_GET['q'] ?? '') ?>">
      <button type="submit" aria-label="Search">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      </button>
    </form>

    <div class="header-actions">
      <a href="<?= $__user ? '/wishlist.php' : '/login.php' ?>" class="icon-btn">
        <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
        Saved
      </a>
      <a href="/cart.php" class="icon-btn">
        <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/></svg>
        Cart
        <?php if ($__cartCount > 0): ?><span class="badge"><?= $__cartCount ?></span><?php endif; ?>
      </a>
      <a href="<?= $__user ? '/account.php' : '/login.php' ?>" class="icon-btn">
        <svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        <?= $__user ? e(explode(' ', $__user['name'])[0]) : 'Login' ?>
      </a>
    </div>
  </div>
</header>

<div class="cat-strip">
  <div class="wrap">
    <a href="/index.php" class="<?= (empty($_GET['slug']) && $__currentPath === '/index.php') || $__currentPath === '/' ? 'active' : '' ?>">All products</a>
    <?php foreach ($__categories as $c): ?>
      <a href="/category.php?slug=<?= e($c['slug']) ?>" class="<?= (($_GET['slug'] ?? '') === $c['slug']) ? 'active' : '' ?>"><?= e($c['name']) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="mobile-nav" id="mobileNav">
  <div class="panel">
    <button class="btn btn-outline btn-sm close-btn" id="navClose">Close ✕</button>
    <a href="/">Home</a>
    <?php foreach ($__categories as $c): ?>
      <a href="/category.php?slug=<?= e($c['slug']) ?>"><?= e($c['name']) ?></a>
    <?php endforeach; ?>
    <a href="/about.php">About</a>
    <a href="/contact.php">Contact</a>
    <a href="/cart.php">Cart (<?= $__cartCount ?>)</a>
    <a href="<?= $__user ? '/account.php' : '/login.php' ?>"><?= $__user ? 'My account' : 'Login / Register' ?></a>
  </div>
</div>

<main>
<?php $__flashes = flash_get(); if ($__flashes): ?>
  <div class="wrap" style="padding-top:20px;">
    <?php foreach ($__flashes as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
