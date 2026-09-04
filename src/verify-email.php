<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$uid = (int) ($_GET['uid'] ?? 0);
$token = trim($_GET['token'] ?? '');
$status = 'invalid';

if ($uid && $token !== '') {
    $stmt = db()->prepare('SELECT id, email_verified, email_verify_token FROM users WHERE id = ?');
    $stmt->execute([$uid]);
    $user = $stmt->fetch();
    if ($user && (int) $user['email_verified'] === 1) {
        $status = 'already';
    } elseif ($user && $user['email_verify_token'] && hash_equals($user['email_verify_token'], $token)) {
        db()->prepare('UPDATE users SET email_verified = 1, email_verify_token = NULL WHERE id = ?')->execute([$uid]);
        $status = 'success';
    }
}

$pageTitle = 'Verify email';
require __DIR__ . '/includes/header.php';
?>
<div class="wrap section">
  <div class="empty-state">
    <?php if ($status === 'success'): ?>
      <div class="icon">✅</div>
      <h2>Email verified</h2>
      <p>Your email address is confirmed. Thanks!</p>
      <a class="btn btn-primary" href="<?= is_logged_in() ? '/account.php' : '/login.php' ?>">Continue</a>
    <?php elseif ($status === 'already'): ?>
      <div class="icon">✅</div>
      <h2>Already verified</h2>
      <p>This email address was already confirmed.</p>
      <a class="btn btn-primary" href="<?= is_logged_in() ? '/account.php' : '/login.php' ?>">Continue</a>
    <?php else: ?>
      <div class="icon">⚠️</div>
      <h2>Link invalid or expired</h2>
      <p>This verification link isn't valid. If you're logged in, you can request a new one from your account page.</p>
      <a class="btn btn-primary" href="<?= is_logged_in() ? '/account.php' : '/login.php' ?>">Go to <?= is_logged_in() ? 'account' : 'login' ?></a>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
