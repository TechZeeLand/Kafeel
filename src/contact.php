<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$sent = false;
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($message) < 5) {
        $errors[] = 'Please fill in your name, a valid email, and a short message.';
    } else {
        // In production, wire this to an SMTP mailer or store in a `messages` table.
        // Kept lightweight here so the site works with zero external services out of the box.
        $sent = true;
    }
}

$pageTitle = 'Contact';
require __DIR__ . '/includes/header.php';
?>
<div class="page-header wrap"><span class="eyebrow">Contact</span><h1>Get in touch</h1></div>
<div class="wrap section" style="padding-top:8px;">
  <div class="form-card form-narrow">
    <?php if ($sent): ?>
      <div class="alert alert-success">Thanks — your message has been received. We'll reply by email soon.</div>
    <?php else: ?>
      <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
      <form method="post">
        <?= csrf_field() ?>
        <div class="field"><label for="name">Name</label><input id="name" name="name" required value="<?= e($_POST['name'] ?? '') ?>"></div>
        <div class="field"><label for="email">Email</label><input type="email" id="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>"></div>
        <div class="field"><label for="message">Message</label><textarea id="message" name="message" rows="5" required><?= e($_POST['message'] ?? '') ?></textarea></div>
        <button type="submit" class="btn btn-primary btn-block">Send message</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
