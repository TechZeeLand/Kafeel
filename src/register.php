<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) redirect('/account.php');

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        [$ok, $msg] = register_user($name, $email, $password, $phone);
        if ($ok) {
            flash_set('success', 'Account created — welcome!');
            redirect('/account.php');
        }
        $error = $msg;
    }
}

$pageTitle = 'Create account';
require __DIR__ . '/includes/header.php';
?>
<div class="wrap">
  <div class="form-card form-narrow">
    <h2 style="text-align:center;margin-bottom:6px;">Create your account</h2>
    <p style="text-align:center;color:var(--ink-soft);margin-bottom:26px;font-size:0.9rem;">Save addresses, track orders, and build a wishlist.</p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="field">
        <label for="name">Full name</label>
        <input id="name" name="name" required autofocus value="<?= e($_POST['name'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="phone">Phone (optional)</label>
        <input id="phone" name="phone" value="<?= e($_POST['phone'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required minlength="8">
        <div class="hint">At least 8 characters.</div>
      </div>
      <div class="field">
        <label for="confirm_password">Confirm password</label>
        <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
      </div>
      <button type="submit" class="btn btn-primary btn-block">Create account</button>
    </form>
    <div class="form-foot">Already have an account? <a href="/login.php">Log in</a></div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
