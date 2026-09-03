<?php
/**
 * Central config. All values come from environment variables so the same
 * code works unmodified inside the docker container. See docker-compose.yml
 * and .env.example for how these are supplied.
 */

function env_val(string $key, $default = null) {
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : $v;
}

/**
 * Guarded with defined() checks (rather than plain define()) because PHP's
 * built-in `php -S` dev server can keep a worker process alive across
 * keep-alive requests, and this file is requested very early via
 * require_once from several entry points. The guards make repeated
 * evaluation harmless in any environment (dev server, php-fpm, CLI tests).
 */
if (!defined('DB_HOST')) {
    define('DB_HOST', env_val('DB_HOST', 'db'));
    define('DB_NAME', env_val('DB_NAME', 'kafeel_db'));
    define('DB_USER', env_val('DB_USER', 'admin'));
    define('DB_PASS', env_val('DB_PASS', 'ChangeMe123!'));

    define('SITE_NAME', env_val('SITE_NAME', 'Kafeel (كَفِيلْ)'));
    define('SITE_URL', rtrim(env_val('SITE_URL', ''), '/')); // e.g. https://shop.example.com, blank = relative
    // Named STORE_CURRENCY_SYMBOL (not CURRENCY_SYMBOL) because PHP's
    // standard extension already defines a built-in CURRENCY_SYMBOL
    // constant (used by nl_langinfo()) — defining our own under that name
    // silently collides with it.
    define('STORE_CURRENCY_SYMBOL', env_val('CURRENCY_SYMBOL', '৳'));

    // --- Shipping (flat zone fee + per-kg surcharge over the free weight) ---
    define('SHIPPING_INSIDE_DHAKA_FEE', (float) env_val('SHIPPING_INSIDE_DHAKA_FEE', 80));
    define('SHIPPING_OUTSIDE_DHAKA_FEE', (float) env_val('SHIPPING_OUTSIDE_DHAKA_FEE', 130));
    define('SHIPPING_FREE_WEIGHT_KG', (float) env_val('SHIPPING_FREE_WEIGHT_KG', 1));
    define('SHIPPING_EXTRA_PER_KG', (float) env_val('SHIPPING_EXTRA_PER_KG', 20));
    define('DELIVERY_DAYS_MIN', (int) env_val('DELIVERY_DAYS_MIN', 3));
    define('DELIVERY_DAYS_MAX', (int) env_val('DELIVERY_DAYS_MAX', 5));

    // --- Contact / social links ---
    define('CONTACT_EMAIL', env_val('CONTACT_EMAIL', 'hello@kafeelshopbd.com'));
    define('CONTACT_PHONE', env_val('CONTACT_PHONE', ''));
    define('SOCIAL_FACEBOOK', env_val('SOCIAL_FACEBOOK', 'https://www.facebook.com/kafeelshopbd'));
    define('SOCIAL_FACEBOOK_MESSENGER', env_val('SOCIAL_FACEBOOK_MESSENGER', 'https://www.m.me/kafeelshopbd'));
    define('SOCIAL_INSTAGRAM', env_val('SOCIAL_INSTAGRAM', 'https://www.instagram.com/kafeelbd/'));
    define('SOCIAL_YOUTUBE', env_val('SOCIAL_YOUTUBE', 'https://www.youtube.com/@Kafeelbd'));

    define('UPLOAD_DIR', __DIR__ . '/../uploads/products');
    define('UPLOAD_URL', '/uploads/products');
    define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024); // 5MB
}

// Session cookie hardening - must run before session_start()
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

date_default_timezone_set(env_val('TZ', 'Asia/Dhaka'));
error_reporting(E_ALL);
ini_set('display_errors', env_val('APP_DEBUG', '0') === '1' ? '1' : '0');
