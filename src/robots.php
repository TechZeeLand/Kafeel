<?php
/** /robots.txt — served through nginx (see docker/nginx/default.conf). */
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo "User-agent: *\n";
foreach (['/admin/', '/api/', '/cart.php', '/checkout.php', '/login.php', '/register.php', '/account.php', '/addresses.php', '/orders.php',
          '/order-detail.php', '/order-success.php', '/wishlist.php', '/invoice.php', '/search.php', '/verify-email.php', '/resend-verification.php'] as $p) {
    echo "Disallow: $p\n";
}
echo "\nSitemap: " . abs_url('/sitemap.xml') . "\n";
