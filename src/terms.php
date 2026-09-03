<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Terms of Service';
require __DIR__ . '/includes/header.php';
?>
<div class="page-header wrap"><span class="eyebrow">Legal</span><h1>Terms of Service</h1></div>
<div class="wrap section" style="padding-top:8px;">
  <div class="prose">
    <p>Last updated: <?= date('d F Y') ?>. These terms apply whenever you browse or order from <?= e(SITE_NAME) ?>. By placing an order with us, you agree to them.</p>

    <h2>Orders</h2>
    <p>Placing an order is an offer to buy the listed product at the listed price. We confirm your order after it's placed; we may cancel or adjust an order if a product turns out to be out of stock or incorrectly priced, and we'll let you know if that happens.</p>

    <h2>Pricing</h2>
    <p>All prices are shown in Bangladeshi Taka (৳) and include applicable taxes unless stated otherwise. Delivery charges are shown separately at checkout.</p>

    <h2>Payment</h2>
    <p>We currently accept cash on delivery only — you pay the courier when your order arrives. No advance online payment is required to place an order.</p>

    <h2>Delivery</h2>
    <p>We aim to deliver within <?= (int)DELIVERY_DAYS_MIN ?>–<?= (int)DELIVERY_DAYS_MAX ?> days of an order being placed. Delivery timelines can occasionally be affected by courier delays, weather, or remote locations, and are estimates rather than guarantees.</p>

    <h2>Product information</h2>
    <p>We try to describe and photograph every product accurately. Colors may vary slightly due to screen display, and handmade or leather items may show natural variation piece to piece.</p>

    <h2>Returns & cancellations</h2>
    <p>See our <a href="/refund-policy.php">refund & return policy</a> for how to cancel or return an order.</p>

    <h2>Accounts</h2>
    <p>If you create an account, you're responsible for keeping your login details secure. Let us know right away if you believe your account has been accessed without your permission.</p>

    <h2>Changes to these terms</h2>
    <p>We may update these terms from time to time as the store evolves. The current version is always available on this page.</p>

    <h2>Contact</h2>
    <p>Questions about these terms can be sent to <a href="mailto:<?= e(CONTACT_EMAIL) ?>"><?= e(CONTACT_EMAIL) ?></a> or through our <a href="/contact.php">contact page</a>.</p>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
