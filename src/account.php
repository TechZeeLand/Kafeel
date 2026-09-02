<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        if (strlen($name) < 2) {
            $errors[] = 'Please enter your full name.';
        } else {
            db()->prepare('UPDATE users SET name = ?, phone = ? WHERE id = ?')->execute([$name, $phone ?: null, $user['id']]);
            $success = 'Profile updated.';
            $user = current_user();
        }
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $hash = $stmt->fetchColumn();
        if (!password_verify($current, $hash)) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $errors[] = 'New passwords do not match.';
        } else {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            $success = 'Password changed.';
        }
    }
}

$orderCountStmt = db()->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
$orderCountStmt->execute([$user['id']]);
$orderCount = (int) $orderCountStmt->fetchColumn();

$favCountStmt = db()->prepare('SELECT COUNT(*) FROM favorites WHERE user_id = ?');
$favCountStmt->execute([$user['id']]);
$favCount = (int) $favCountStmt->fetchColumn();

$__activeAccountTab = 'overview';
$pageTitle = 'My account';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header wrap"><span class="eyebrow">Account</span><h1>Hi, <?= e(explode(' ', $user['name'])[0]) ?></h1></div>

<div class="wrap account-layout">
  <?php include __DIR__ . '/includes/account_nav.php'; ?>

  <div>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

    <div class="stat-grid" style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px;margin-bottom:26px;">
      <div class="panel" style="padding:20px;"><div class="mono" style="color:var(--ink-faint);font-size:0.75rem;text-transform:uppercase;">Orders placed</div><div style="font-family:var(--font-mono);font-size:1.8rem;font-weight:700;margin-top:6px;"><?= $orderCount ?></div></div>
      <div class="panel" style="padding:20px;"><div class="mono" style="color:var(--ink-faint);font-size:0.75rem;text-transform:uppercase;">Wishlist items</div><div style="font-family:var(--font-mono);font-size:1.8rem;font-weight:700;margin-top:6px;"><?= $favCount ?></div></div>
    </div>

    <div class="form-card" style="margin-bottom:22px;">
      <h3 style="margin-bottom:16px;">Profile details</h3>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_profile">
        <div class="field-row">
          <div class="field"><label for="name">Full name</label><input id="name" name="name" required value="<?= e($user['name']) ?>"></div>
          <div class="field"><label for="phone">Phone</label><input id="phone" name="phone" value="<?= e($user['phone'] ?? '') ?>"></div>
        </div>
        <div class="field"><label>Email</label><input value="<?= e($user['email']) ?>" disabled></div>
        <button type="submit" class="btn btn-primary">Save changes</button>
      </form>
    </div>

    <div class="form-card">
      <h3 style="margin-bottom:16px;">Change password</h3>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_password">
        <div class="field"><label for="current_password">Current password</label><input type="password" id="current_password" name="current_password" required></div>
        <div class="field-row">
          <div class="field"><label for="new_password">New password</label><input type="password" id="new_password" name="new_password" required minlength="8"></div>
          <div class="field"><label for="confirm_password">Confirm new password</label><input type="password" id="confirm_password" name="confirm_password" required minlength="8"></div>
        </div>
        <button type="submit" class="btn btn-primary">Update password</button>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
