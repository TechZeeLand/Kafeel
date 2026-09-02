<?php
require_once __DIR__ . '/../includes/admin_auth.php';

if (admin_logged_in()) redirect('/admin/index.php');

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (attempt_admin_login($username, $password)) {
        redirect('/admin/index.php');
    }
    $error = 'Invalid username or password.';
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin login · <?= e(SITE_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<div class="login-shell">
  <div class="login-card">
    <span class="brand">⚙ <?= e(SITE_NAME) ?></span>
    <div class="sub">Admin portal</div>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="field"><label for="username">Username</label><input id="username" name="username" required autofocus value="<?= e($_POST['username'] ?? '') ?>"></div>
      <div class="field"><label for="password">Password</label><input type="password" id="password" name="password" required></div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Log in</button>
    </form>
  </div>
</div>
</body>
</html>
