<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Privacy Policy';
require __DIR__ . '/includes/header.php';
?>
<div class="page-header wrap"><span class="eyebrow">Legal</span><h1>Privacy Policy</h1></div>
<div class="wrap section" style="padding-top:8px;">
  <div class="prose">
    <p>Last updated: <?= date('d F Y') ?>. This policy explains what information <?= e(SITE_NAME) ?> collects when you use our website and place an order, and how we use it.</p>

    <h2>Information we collect</h2>
    <p>When you browse the site, create an account, or place an order, we may collect: your name, phone number, delivery address, email address, and details of the products you order. We also store basic technical information such as your session and cart contents so the site works correctly.</p>

    <h2>How we use your information</h2>
    <p>We use this information to process and deliver your orders, to contact you about an order (for example to confirm delivery details or resolve an issue), to maintain your account if you create one, and to improve the site. We do not sell your personal information to third parties.</p>

    <h2>Who we share it with</h2>
    <p>We share order and delivery details with the courier service handling your delivery, so they can reach you. We do not share your information with advertisers or data brokers.</p>

    <h2>Cookies & sessions</h2>
    <p>We use a session cookie to keep your cart and login working while you browse. This is required for the site to function and doesn't track you across other websites.</p>

    <h2>Data retention</h2>
    <p>We keep order records for as long as needed for accounting, warranty, and customer-service purposes. You can ask us to delete your account and associated personal data at any time by contacting us.</p>

    <h2>Your rights</h2>
    <p>You can ask us what personal information we hold about you, ask us to correct it, or ask us to delete it, subject to any records we're legally required to keep. Reach out through the <a href="/contact.php">contact page</a> for any of these requests.</p>

    <h2>Contact</h2>
    <p>Questions about this policy can be sent to <a href="mailto:<?= e(CONTACT_EMAIL) ?>"><?= e(CONTACT_EMAIL) ?></a> or through our <a href="/contact.php">contact page</a>.</p>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
