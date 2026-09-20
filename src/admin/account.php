<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $admin = current_admin();
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    $stmt = db()->prepare('SELECT password_hash FROM admins WHERE id = ?');
    $stmt->execute([$admin['id']]);
    $hash = (string) $stmt->fetchColumn();

    // Guard against password guessing through this form as well.
    $wait = login_throttle_check('admin_pw', (string) $admin['username']);
    if ($wait !== null) {
        $errors[] = 'Too many attempts. Please try again in ' . (int) ceil($wait / 60) . ' minute(s).';
    } elseif (!password_verify($current, $hash)) {
        login_throttle_hit('admin_pw', (string) $admin['username']);
        $errors[] = 'Your current password is incorrect.';
    } elseif (strlen($new) < 10) {
        $errors[] = 'Choose a new password of at least 10 characters.';
    } elseif ($new === 'ChangeMe123!' || $new === $current) {
        $errors[] = 'The new password must be different from the old one.';
    } elseif ($new !== $confirm) {
        $errors[] = 'The two new passwords don\'t match.';
    } else {
        db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $admin['id']]);
        login_throttle_clear('admin_pw', (string) $admin['username']);
        session_regenerate_id(true);
        flash_set('success', 'Password changed.');
        redirect('/admin/account.php');
    }
}

$admin = current_admin();
$pageTitle = 'My account';
require __DIR__ . '/includes/header.php';
?>
<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<form method="post" class="panel" style="max-width:520px;" autocomplete="off">
  <?= csrf_field() ?>
  <div class="panel-head"><h2>Change password</h2></div>
  <div class="panel-body">
    <p class="help">Signed in as <strong><?= e($admin['username']) ?></strong>. Use a long, unique password — 10 characters or more.</p>
    <div class="field"><label for="current_password">Current password</label><input type="password" id="current_password" name="current_password" required autocomplete="current-password"></div>
    <div class="field"><label for="new_password">New password</label><input type="password" id="new_password" name="new_password" required minlength="10" autocomplete="new-password"></div>
    <div class="field" style="margin-bottom:0;"><label for="confirm_password">Repeat new password</label><input type="password" id="confirm_password" name="confirm_password" required minlength="10" autocomplete="new-password"></div>
  </div>
  <div class="panel-foot"><button class="btn btn-primary" type="submit">Update password</button></div>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
