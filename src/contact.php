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
  <div class="cart-layout">
  <div class="form-card" style="margin-bottom:0;">
    <h3 style="margin-bottom:12px;">Reach us directly</h3>
    <p style="color:var(--ink-soft);">
      DM us on <a href="<?= e(SOCIAL_FACEBOOK_MESSENGER) ?>" target="_blank" rel="noopener"><strong>Facebook Messenger</strong></a>
      or <a href="<?= e(SOCIAL_INSTAGRAM) ?>" target="_blank" rel="noopener"><strong>Instagram</strong></a>.
      You can also send a direct mail and our staff will reach out to you.
    </p>
    <p style="margin-top:14px;">
      <a href="mailto:<?= e(CONTACT_EMAIL) ?>" class="btn btn-outline"><?= e(CONTACT_EMAIL) ?></a>
    </p>
    <div class="social-row" style="margin-top:20px;">
      <a href="<?= e(SOCIAL_FACEBOOK) ?>" target="_blank" rel="noopener" aria-label="Facebook" title="Facebook" style="border-color:var(--line);color:var(--ink-soft);">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5.02 3.66 9.18 8.44 9.94v-7.03H7.9v-2.91h2.54V9.85c0-2.51 1.49-3.9 3.77-3.9 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.89h2.78l-.45 2.91h-2.33V22c4.78-.76 8.44-4.92 8.44-9.94z"/></svg>
      </a>
      <a href="<?= e(SOCIAL_INSTAGRAM) ?>" target="_blank" rel="noopener" aria-label="Instagram" title="Instagram" style="border-color:var(--line);color:var(--ink-soft);">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4.2"/><circle cx="17.4" cy="6.6" r="1.1" fill="currentColor" stroke="none"/></svg>
      </a>
      <a href="<?= e(SOCIAL_YOUTUBE) ?>" target="_blank" rel="noopener" aria-label="YouTube" title="YouTube" style="border-color:var(--line);color:var(--ink-soft);">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M23 12s0-3.6-.46-5.3a3 3 0 0 0-2.1-2.1C18.6 4 12 4 12 4s-6.6 0-8.44.6a3 3 0 0 0-2.1 2.1C1 8.4 1 12 1 12s0 3.6.46 5.3a3 3 0 0 0 2.1 2.1C5.4 20 12 20 12 20s6.6 0 8.44-.6a3 3 0 0 0 2.1-2.1C23 15.6 23 12 23 12z"/><path d="M9.8 8.6v6.8L15.8 12z" fill="var(--paper)"/></svg>
      </a>
    </div>
  </div>

  <div class="form-card" style="margin-bottom:0;">
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
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
