<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'About';
require __DIR__ . '/includes/header.php';
?>
<div class="page-header wrap"><span class="eyebrow">About</span><h1>Good tools, chosen carefully</h1></div>
<div class="wrap section" style="padding-top:8px;">
  <div class="prose">
    <p><?= e(SITE_NAME) ?> started with a simple idea: the small things we carry every day — a knife, a wallet, a bag, a keychain — deserve the same attention as anything else we buy. We look for items that are well made, honestly priced, and built to be used, not just displayed.</p>
    <h2>What we carry</h2>
    <p>Our catalog spans everyday-carry gear for the pocket and the keychain, bags built for daily use, full-grain leather goods that only get better with age, and customized, engraved pieces made to order. We keep the range tight — every product on the site is something we'd genuinely recommend.</p>
    <h2>Shipping & returns</h2>
    <p>We ship nationwide with cash-on-delivery available on every order, and offer free shipping once your cart passes <?= money(FREE_SHIPPING_THRESHOLD) ?>. If something isn't right, unused items can be returned within 7 days of delivery.</p>
    <h2>Questions?</h2>
    <p>Reach out any time through the <a href="/contact.php">contact page</a> — we read every message.</p>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
