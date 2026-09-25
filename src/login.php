<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) redirect('/account');

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    [$ok, $wait] = attempt_login($email, $password);
    if ($ok) {
        $redirectTo = safe_local_path($_SESSION['redirect_after_login'] ?? null, '/account');
        unset($_SESSION['redirect_after_login']);
        flash_set('success', 'Welcome back!');
        redirect($redirectTo);
    }
    $error = $wait !== null
        ? 'Too many login attempts. Please try again in ' . ceil($wait / 60) . ' minute(s).'
        : 'That email and password combination doesn\'t match our records.';
}

$pageTitle = 'Log in';
require __DIR__ . '/includes/header.php';
?>
<div class="wrap">
  <div class="form-card form-narrow">
    <h2 style="text-align:center;margin-bottom:6px;">Welcome back</h2>
    <p style="text-align:center;color:var(--ink-soft);margin-bottom:26px;font-size:0.9rem;">Log in to track orders and manage your wishlist.</p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Log in</button>
    </form>
    <div class="form-foot">New here? <a href="/register">Create an account</a></div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
