<?php
require_once __DIR__ . '/db.php';

/* ---------------------------------------------------------- CSRF ---- */

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_verify(): bool {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function require_csrf(): void {
    if (!csrf_verify()) {
        http_response_code(419);
        die('Security check failed. Please go back and try again.');
    }
}

/* ------------------------------------------------- login throttling - */

/**
 * DB-backed brute-force guard for login forms, keyed by both the
 * attempted identifier (email/username) and the client IP so an
 * attacker can't dodge it just by clearing cookies (a session-based
 * guard could be reset that easily). Limit: 5 failed attempts per
 * 5-minute window: whichever of (identifier, IP) is more attacked
 * trips the lock first.
 */
function client_ip(): string {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    // Behind a reverse proxy (Nginx Proxy Manager, Caddy, Traefik, Cloudflare…) REMOTE_ADDR is
    // the proxy, i.e. the same for every visitor — that would put everyone in one throttle
    // bucket and stamp the proxy's address on every audit-log entry. So when the direct peer
    // is a private/loopback address (= our own proxy), read the visitor from X-Forwarded-For:
    // the right-most public address, since each proxy appends the peer it saw and only the
    // entries added by our own proxies can be trusted. A visitor connecting straight to the
    // container has a public REMOTE_ADDR, so a forged header from them is never consulted.
    $isPrivate = fn (string $ip): bool => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    if ($isPrivate($remote) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $chain = array_reverse(array_map('trim', explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])));
        foreach ($chain as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP) && !$isPrivate($ip)) return $ip;
        }
    }
    return $remote;
}

/** Returns seconds remaining before another attempt is allowed, or null if clear. */
function login_throttle_check(string $bucket, string $identifier, int $limit = 5): ?int {
    // Everything is computed inside the database so it can never disagree
    // with the timestamps it stored (PHP's clock/timezone is not involved).
    $stmt = db()->prepare(
        'SELECT COUNT(*) AS cnt,
                GREATEST(0, 300 - COALESCE(TIMESTAMPDIFF(SECOND, MIN(attempted_at), NOW()), 0)) AS remaining
         FROM login_attempts
         WHERE bucket = ? AND (identifier = ? OR ip = ?) AND attempted_at > (NOW() - INTERVAL 5 MINUTE)'
    );
    $stmt->execute([$bucket, strtolower($identifier), client_ip()]);
    $row = $stmt->fetch();
    if ($row && (int) $row['cnt'] >= $limit && (int) $row['remaining'] > 0) {
        return (int) $row['remaining'];
    }
    return null;
}

function login_throttle_hit(string $bucket, string $identifier): void {
    db()->prepare('INSERT INTO login_attempts (bucket, identifier, ip) VALUES (?,?,?)')
        ->execute([$bucket, strtolower($identifier), client_ip()]);
}

function login_throttle_clear(string $bucket, string $identifier): void {
    db()->prepare('DELETE FROM login_attempts WHERE bucket = ? AND (identifier = ? OR ip = ?)')
        ->execute([$bucket, strtolower($identifier), client_ip()]);
}

/* ------------------------------------------------------- flash msg -- */

function flash_set(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_get(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ----------------------------------------------------- formatting --- */

function money(float $amount): string {
    return STORE_CURRENCY_SYMBOL . number_format($amount, 2);
}

function slugify(string $text): string {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = trim($text, '-');
    $text = iconv('utf-8', 'ascii//TRANSLIT', $text) ?: $text;
    $text = strtolower($text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    return $text !== '' ? $text : 'item-' . substr(md5((string)microtime(true)), 0, 6);
}

function e($s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/**
 * Database timestamps are stored in UTC (see db.php). This converts one to
 * the store's timezone (TZ env, default Asia/Dhaka) for display.
 */
function db_time(?string $utc): ?DateTimeImmutable {
    if ($utc === null || $utc === '' || str_starts_with($utc, '0000')) return null;
    try {
        return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone(date_default_timezone_get()));
    } catch (Throwable $e) {
        return null;
    }
}

function fmt_dt(?string $utc, string $format = 'd M Y, g:i A'): string {
    $d = db_time($utc);
    return $d ? $d->format($format) : '—';
}

/**
 * Public base URL for links in emails, link previews and the sitemap. SITE_URL
 * wins unless it's a localhost placeholder; otherwise the address of the
 * current request is used — but only if the Host header looks like a real host
 * (a forged Host header must never end up in canonical links or emails).
 */
function base_url(): string {
    $configured = SITE_URL;
    $host = $configured !== '' ? (parse_url($configured, PHP_URL_HOST) ?: '') : '';
    $isLocal = in_array($host, ['', 'localhost', '127.0.0.1', '0.0.0.0'], true);
    if (!$isLocal) return $configured;
    $reqHost = $_SERVER['HTTP_HOST'] ?? '';
    if ($reqHost !== '' && preg_match('/^[a-z0-9.-]+(:\d{1,5})?$|^\[[0-9a-f:]+\](:\d{1,5})?$/i', $reqHost)) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        return ($https ? 'https://' : 'http://') . $reqHost;
    }
    return $configured;
}

/** Only allows same-site relative paths, for post-action redirects. */
function safe_local_path(?string $url, string $fallback = '/'): string {
    if (!$url) return $fallback;
    $parts = parse_url($url);
    if ($parts === false) return $fallback;
    if (!empty($parts['host']) && !empty($_SERVER['HTTP_HOST']) && strcasecmp($parts['host'], preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'])) !== 0) {
        return $fallback;
    }
    $path = $parts['path'] ?? '/';
    if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')) return $fallback;
    return $path . (isset($parts['query']) ? '?' . $parts['query'] : '');
}

function redirect(string $path): void {
    header('Location: ' . $path);
    exit;
}

/* -------------------------------------------------------- cart ------ */

function cart_identity(): array {
    // Returns [user_id or null, session_id or null] - always one is set.
    if (!empty($_SESSION['user_id'])) {
        return [(int)$_SESSION['user_id'], null];
    }
    return [null, session_id()];
}

/** Combined "Red / Large" style label for a variant row (color and/or size). */
function variant_label(array $variant): string {
    $parts = array_filter([$variant['color'] ?? null, $variant['size'] ?? null]);
    return $parts ? implode(' / ', $parts) : '';
}

function cart_items(): array {
    // A cart line's photo and weight follow the chosen options: the color's
    // (or else the size's) preview image, and the size's weight override.
    $sql = 'SELECT c.id, c.quantity, c.variant_id, p.id AS product_id, p.name, p.slug, p.price,
                   COALESCE(co.image, so.image, p.image_main) AS image_main,
                   p.stock AS product_stock, p.is_active AS product_active,
                   COALESCE(so.weight_grams, p.weight_grams) AS weight_grams,
                   v.color AS variant_color, v.size AS variant_size, v.price_delta, v.stock AS variant_stock,
                   v.is_active AS variant_active
            FROM cart_items c
            JOIN products p ON p.id = c.product_id
            LEFT JOIN product_variants v ON v.id = c.variant_id
            LEFT JOIN product_options co ON co.product_id = v.product_id AND co.kind = \'color\' AND co.name = v.color
            LEFT JOIN product_options so ON so.product_id = v.product_id AND so.kind = \'size\' AND so.name = v.size
            WHERE %s ORDER BY c.id DESC';
    [$uid, $sid] = cart_identity();
    if ($uid) {
        $stmt = db()->prepare(sprintf($sql, 'c.user_id = ?'));
        $stmt->execute([$uid]);
    } else {
        $stmt = db()->prepare(sprintf($sql, 'c.session_id = ?'));
        $stmt->execute([$sid]);
    }
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['price'] = (float) $r['price'] + (float) ($r['price_delta'] ?? 0);
        $r['stock'] = $r['variant_id'] ? (int) $r['variant_stock'] : (int) $r['product_stock'];
        $r['available'] = $r['product_active'] && (!$r['variant_id'] || $r['variant_active']);
        $r['variant_label'] = $r['variant_id'] ? variant_label(['color' => $r['variant_color'], 'size' => $r['variant_size']]) : null;
    }
    unset($r);
    return $rows;
}

function cart_count(): int {
    [$uid, $sid] = cart_identity();
    if ($uid) {
        $stmt = db()->prepare('SELECT COALESCE(SUM(quantity),0) FROM cart_items WHERE user_id = ?');
        $stmt->execute([$uid]);
    } else {
        $stmt = db()->prepare('SELECT COALESCE(SUM(quantity),0) FROM cart_items WHERE session_id = ?');
        $stmt->execute([$sid]);
    }
    return (int) $stmt->fetchColumn();
}

/**
 * Cart contents + subtotal + total weight. Shipping isn't included here
 * because it depends on the delivery area, which is only known at
 * checkout — see shipping_fee_for_area().
 */
function cart_totals(): array {
    $items = cart_items();
    $subtotal = 0.0;
    $weightGrams = 0;
    foreach ($items as $it) {
        $subtotal += $it['price'] * $it['quantity'];
        $weightGrams += (int) $it['weight_grams'] * (int) $it['quantity'];
    }
    return [
        'items' => $items,
        'subtotal' => $subtotal,
        'weight_grams' => $weightGrams,
    ];
}

/** Human label for a delivery_area value. */
function delivery_area_label(string $area): string {
    if ($area === 'outside_dhaka') return 'Outside Dhaka';
    if ($area === 'suburbs') return 'Dhaka Suburbs';
    return 'Inside Dhaka';
}

/** Base flat fee for a delivery zone, before the over-weight surcharge. */
function shipping_base_fee_for_area(string $area): float {
    if ($area === 'outside_dhaka') return SHIPPING_OUTSIDE_DHAKA_FEE;
    if ($area === 'suburbs') return SHIPPING_SUBURBS_FEE;
    return SHIPPING_INSIDE_DHAKA_FEE;
}

/**
 * Shipping fee for a given delivery area + parcel weight: a flat zone fee,
 * plus a per-kg surcharge for every kg (or part of a kg) over the free
 * weight allowance.
 */
function shipping_fee_for_area(string $area, int $weightGrams): float {
    $base = shipping_base_fee_for_area($area);
    $freeGrams = SHIPPING_FREE_WEIGHT_KG * 1000;
    $extraGrams = max(0, $weightGrams - $freeGrams);
    $extraKg = (int) ceil($extraGrams / 1000);
    return $base + ($extraKg * SHIPPING_EXTRA_PER_KG);
}

function cart_add(int $productId, int $qty = 1, ?int $variantId = null, ?int $maxQty = null): void {
    [$uid, $sid] = cart_identity();
    $qty = max(1, $qty);
    $pdo = db();
    // variant_id <=> ? is a NULL-safe equality comparison, so "no variant"
    // still matches an existing no-variant cart line rather than always
    // inserting a new row.
    if ($uid) {
        $stmt = $pdo->prepare('SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ? AND variant_id <=> ?');
        $stmt->execute([$uid, $productId, $variantId]);
    } else {
        $stmt = $pdo->prepare('SELECT id, quantity FROM cart_items WHERE session_id = ? AND product_id = ? AND variant_id <=> ?');
        $stmt->execute([$sid, $productId, $variantId]);
    }
    $row = $stmt->fetch();
    if ($row) {
        // Never let repeated "add" clicks push the line past what's in stock.
        $newQty = (int) $row['quantity'] + $qty;
        if ($maxQty !== null) $newQty = min($newQty, $maxQty);
        $upd = $pdo->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?');
        $upd->execute([max(1, $newQty), $row['id']]);
    } else {
        $ins = $pdo->prepare('INSERT INTO cart_items (user_id, session_id, product_id, variant_id, quantity) VALUES (?,?,?,?,?)');
        $ins->execute([$uid, $uid ? null : $sid, $productId, $variantId, $maxQty !== null ? min($qty, $maxQty) : $qty]);
    }
}

function cart_set_qty(int $cartItemId, int $qty): void {
    [$uid, $sid] = cart_identity();
    $pdo = db();
    if ($qty <= 0) {
        cart_remove($cartItemId);
        return;
    }
    if ($uid) {
        $stmt = $pdo->prepare('UPDATE cart_items SET quantity = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$qty, $cartItemId, $uid]);
    } else {
        $stmt = $pdo->prepare('UPDATE cart_items SET quantity = ? WHERE id = ? AND session_id = ?');
        $stmt->execute([$qty, $cartItemId, $sid]);
    }
}

function cart_remove(int $cartItemId): void {
    [$uid, $sid] = cart_identity();
    $pdo = db();
    if ($uid) {
        $stmt = $pdo->prepare('DELETE FROM cart_items WHERE id = ? AND user_id = ?');
        $stmt->execute([$cartItemId, $uid]);
    } else {
        $stmt = $pdo->prepare('DELETE FROM cart_items WHERE id = ? AND session_id = ?');
        $stmt->execute([$cartItemId, $sid]);
    }
}

function cart_clear(): void {
    [$uid, $sid] = cart_identity();
    $pdo = db();
    if ($uid) {
        $pdo->prepare('DELETE FROM cart_items WHERE user_id = ?')->execute([$uid]);
    } else {
        $pdo->prepare('DELETE FROM cart_items WHERE session_id = ?')->execute([$sid]);
    }
}

/** Merge a guest session's cart into a user's cart after login. */
function cart_merge_session_into_user(int $userId, string $sessionId): void {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT product_id, variant_id, quantity FROM cart_items WHERE session_id = ?');
    $stmt->execute([$sessionId]);
    foreach ($stmt->fetchAll() as $row) {
        $existing = $pdo->prepare('SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ? AND variant_id <=> ?');
        $existing->execute([$userId, $row['product_id'], $row['variant_id']]);
        $ex = $existing->fetch();
        if ($ex) {
            $pdo->prepare('UPDATE cart_items SET quantity = quantity + ? WHERE id = ?')
                ->execute([$row['quantity'], $ex['id']]);
        } else {
            $pdo->prepare('INSERT INTO cart_items (user_id, product_id, variant_id, quantity) VALUES (?,?,?,?)')
                ->execute([$userId, $row['product_id'], $row['variant_id'], $row['quantity']]);
        }
    }
    $pdo->prepare('DELETE FROM cart_items WHERE session_id = ?')->execute([$sessionId]);
}

/* ----------------------------------------------------- favorites ---- */

function favorite_ids_for_user(int $userId): array {
    $stmt = db()->prepare('SELECT product_id FROM favorites WHERE user_id = ?');
    $stmt->execute([$userId]);
    return array_map('intval', array_column($stmt->fetchAll(), 'product_id'));
}

/** How many customers have this product in their wishlist. */
function product_wish_count(int $productId): int {
    $stmt = db()->prepare('SELECT COUNT(*) FROM favorites WHERE product_id = ?');
    $stmt->execute([$productId]);
    return (int) $stmt->fetchColumn();
}

function favorite_toggle(int $userId, int $productId): bool {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id FROM favorites WHERE user_id = ? AND product_id = ?');
    $stmt->execute([$userId, $productId]);
    if ($row = $stmt->fetch()) {
        $pdo->prepare('DELETE FROM favorites WHERE id = ?')->execute([$row['id']]);
        return false; // now removed
    }
    $pdo->prepare('INSERT INTO favorites (user_id, product_id) VALUES (?,?)')->execute([$userId, $productId]);
    return true; // now added
}

/* --------------------------------------------------- image upload --- */

/**
 * Handles a single <input type=file> upload, validates it, and stores it
 * under /uploads/products. Returns the relative URL path or null.
 */
function handle_product_image_upload(string $fieldName): ?string {
    if (empty($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$fieldName];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload error code ' . $file['error']);
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('Image is too large (max 5MB).');
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Unsupported image type. Use JPG, PNG, WEBP or GIF.');
    }
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0775, true);
    }
    $filename = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    $dest = UPLOAD_DIR . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save uploaded image.');
    }
    return UPLOAD_URL . '/' . $filename;
}

/** Loosely validates that a URL points at YouTube (watch, youtu.be, or shorts links). */
function is_youtube_url(string $url): bool {
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) return false;
    $host = strtolower(preg_replace('/^www\./', '', $host));
    return in_array($host, ['youtube.com', 'youtu.be', 'm.youtube.com'], true);
}

function product_image_src(?string $path): string {
    if (!$path) {
        return '/assets/img/placeholder.svg';
    }
    return $path;
}

/* ------------------------------------------------------- settings --- */

/** All settings as a flat [key => value] array, cached for the request (and kept current by set_setting()). */
function all_settings(): array {
    if (!isset($GLOBALS['__settings_cache'])) {
        $load = function (): array {
            $out = [];
            foreach (db()->query('SELECT setting_key, setting_value FROM settings')->fetchAll() as $row) {
                $out[$row['setting_key']] = $row['setting_value'];
            }
            return $out;
        };
        $cache = $load();
        require_once __DIR__ . '/migrate.php';
        if ((int) ($cache['schema_version'] ?? 3) < MIGRATE_LATEST) {
            // First request after an upgrade: apply pending migrations.
            try {
                run_pending_migrations(db(), (int) ($cache['schema_version'] ?? 3));
                $cache = $load();
            } catch (Throwable $e) {
                error_log('[migrate] ' . $e->getMessage());
                $GLOBALS['__migration_error'] = $e->getMessage();
            }
        }
        $GLOBALS['__settings_cache'] = $cache;
    }
    return $GLOBALS['__settings_cache'];
}

function get_setting(string $key, ?string $default = null): ?string {
    $all = all_settings();
    return $all[$key] ?? $default;
}

function set_setting(string $key, string $value): void {
    db()->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    )->execute([$key, $value]);
    if (isset($GLOBALS['__settings_cache'])) $GLOBALS['__settings_cache'][$key] = $value;
}

/** Setting if set (non-empty), otherwise the fallback (usually the env value). */
function setting_or(string $key, $fallback) {
    $v = get_setting($key, '');
    return ($v !== null && $v !== '') ? $v : $fallback;
}

const THEME_DEFAULTS = ['primary' => '#a97c34', 'secondary' => '#5f7d5b', 'dark' => '#20293b'];
const THEME_PAPER_LIGHT = '#efece2';
const THEME_PAPER_DARK = '#12161f';

/** Theme + seasonal-effect settings, with sane defaults if unset. */
function theme_settings(): array {
    $hex = fn (string $k, string $d) => preg_match('/^#[0-9a-fA-F]{6}$/', (string) get_setting($k, $d)) ? strtolower(get_setting($k, $d)) : $d;
    return [
        'primary' => $hex('theme_primary', THEME_DEFAULTS['primary']),
        'secondary' => $hex('theme_secondary', THEME_DEFAULTS['secondary']),
        'dark' => $hex('theme_dark', THEME_DEFAULTS['dark']),
        'seasonal_enabled' => get_setting('seasonal_enabled', '0') === '1',
        'seasonal_effect' => get_setting('seasonal_effect', 'snow'),
    ];
}

/** Darken/lighten a #rrggbb hex color by a percentage (-100..100). */
function hex_shade(string $hex, float $percent): string {
    $hex = ltrim($hex, '#');
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) return '#' . str_pad($hex, 6, '0');
    [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    $adjust = function (int $c) use ($percent): int {
        $target = $percent >= 0 ? 255 : 0;
        $c = (int) round($c + ($target - $c) * (abs($percent) / 100));
        return max(0, min(255, $c));
    };
    return sprintf('#%02x%02x%02x', $adjust($r), $adjust($g), $adjust($b));
}

/** WCAG relative luminance of a #rrggbb color. */
function color_luminance(string $hex): float {
    $hex = ltrim($hex, '#');
    $c = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    foreach ($c as &$v) { $v /= 255; $v = $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4; }
    return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}

function contrast_ratio(string $a, string $b): float {
    $la = color_luminance($a); $lb = color_luminance($b);
    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

/** Text color (white or near-black) that reads best on the given background. */
function contrast_text(string $bg): string {
    return contrast_ratio($bg, '#ffffff') >= contrast_ratio($bg, '#111111') ? '#ffffff' : '#111111';
}

/** Nudges a color darker/lighter (toward what the background needs) until it reads at ≥ $min:1. */
function ensure_contrast(string $fg, string $bg, float $min = 4.5): string {
    $toward = color_luminance($bg) > 0.4 ? -1 : 1;
    for ($i = 0; $i < 40 && contrast_ratio($fg, $bg) < $min; $i++) {
        $fg = hex_shade($fg, $toward * 6);
    }
    return $fg;
}

/**
 * CSS custom properties for the admin-chosen theme. Emitted into <style> on
 * both storefront and admin so every button, accent and dark surface follows
 * the three theme colors, in light and dark mode alike.
 */
function theme_css(): string {
    $t = theme_settings();
    [$p, $s, $d] = [$t['primary'], $t['secondary'], $t['dark']];
    $light = fn (string $c) => ensure_contrast($c, THEME_PAPER_LIGHT);
    $dark = fn (string $c) => ensure_contrast($c, THEME_PAPER_DARK);
    return ":root{"
        . "--brass:$p;--brass-dark:" . hex_shade($p, -18) . ";--brass-tint:" . hex_shade($p, 55) . ";--on-brass:" . contrast_text($p) . ";"
        . "--accent-text:" . $light($p) . ";--sage:$s;--sage-text:" . $light($s) . ";"
        . "--surface-dark:$d;--on-dark:" . contrast_text($d) . ";}"
        . ":root[data-theme=\"dark\"]{--accent-text:" . $dark($p) . ";--sage-text:" . $dark($s) . ";}";
}

/** Announcement bar shown above the header: [enabled, text, link]. */
function topbar_settings(): array {
    $text = trim((string) get_setting('topbar_text', ''));
    return [
        'enabled' => get_setting('topbar_enabled', '0') === '1' && $text !== '',
        'text' => $text,
        'link' => trim((string) get_setting('topbar_link', '')),
        'raw_enabled' => get_setting('topbar_enabled', '0') === '1',
    ];
}

/** Effective SMTP configuration: admin-saved values win, env is the fallback. */
function smtp_settings(): array {
    return [
        'host' => (string) setting_or('smtp_host', SMTP_HOST),
        'port' => (int) setting_or('smtp_port', SMTP_PORT),
        'user' => (string) setting_or('smtp_user', SMTP_USER),
        'pass' => (string) setting_or('smtp_pass', SMTP_PASS),
        'secure' => get_setting('smtp_secure') !== null ? (string) get_setting('smtp_secure') : SMTP_SECURE, // tls | ssl | '' (none)
        // Sender defaults follow the store details, so renaming the store renames the sender too.
        'from_email' => (string) setting_or('smtp_from_email', env_val('SMTP_FROM_EMAIL') ?: (filter_var(setting_or('smtp_user', SMTP_USER), FILTER_VALIDATE_EMAIL) ?: store_info()['email'])),
        'from_name' => (string) setting_or('smtp_from_name', store_info()['name']),
    ];
}

/* --------------------------------------------------------------- tags -- */

/** Cleans a comma/newline separated tag string into a de-duplicated list. */
function parse_tags(?string $raw): array {
    $out = [];
    foreach (preg_split('/[,\n;]+/u', (string) $raw) as $t) {
        $t = trim(preg_replace('/\s+/u', ' ', $t));
        $t = mb_substr($t, 0, 40);
        if ($t === '') continue;
        $out[mb_strtolower($t)] ??= $t;
    }
    return array_slice(array_values($out), 0, 20);
}

function product_tags(array $product): array {
    return parse_tags($product['tags'] ?? '');
}

/* ------------------------------------------------------------- search -- */

function like_escape(string $s): string {
    return str_replace(['|', '%', '_'], ['||', '|%', '|_'], $s);
}

/** Splits a query into up to 6 search words (each ≥1 char). */
function search_words(string $q): array {
    $words = preg_split('/[\s,]+/u', trim($q), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    return array_slice(array_map(fn ($w) => mb_substr($w, 0, 40), $words), 0, 6);
}

/**
 * Partial-word product search. Every word the shopper typed has to appear
 * somewhere in the product (name, tags, SKU, descriptions, category, color /
 * size options) — but as a *fragment*, so "tita" finds "Titanium". Products
 * whose name/tags start a word with the fragment rank highest.
 *
 * @return array{where:string, where_params:array, rank:string, rank_params:array}
 */
function product_search_sql(string $q): array {
    $where = []; $wp = []; $rank = []; $rp = [];
    foreach (search_words($q) as $w) {
        $any = '%' . like_escape($w) . '%';
        $start = like_escape($w) . '%';
        $wordStart = '% ' . like_escape($w) . '%';
        $where[] = '(p.name LIKE ? ESCAPE \'|\' OR p.tags LIKE ? ESCAPE \'|\' OR p.sku LIKE ? ESCAPE \'|\' OR p.short_desc LIKE ? ESCAPE \'|\'
                    OR p.description LIKE ? ESCAPE \'|\' OR c.name LIKE ? ESCAPE \'|\' OR p.color LIKE ? ESCAPE \'|\'
                    OR EXISTS (SELECT 1 FROM product_variants sv WHERE sv.product_id = p.id AND sv.is_active = 1
                               AND (sv.color LIKE ? ESCAPE \'|\' OR sv.size LIKE ? ESCAPE \'|\')))';
        array_push($wp, $any, $any, $any, $any, $any, $any, $any, $any, $any);

        $rank[] = '(IF(p.name LIKE ? ESCAPE \'|\', 12, 0) + IF(p.name LIKE ? ESCAPE \'|\', 10, 0) + IF(p.name LIKE ? ESCAPE \'|\', 5, 0)
                  + IF(p.tags LIKE ? ESCAPE \'|\', 6, 0) + IF(p.sku LIKE ? ESCAPE \'|\', 5, 0)
                  + IF(p.short_desc LIKE ? ESCAPE \'|\', 2, 0) + IF(p.description LIKE ? ESCAPE \'|\', 1, 0) + IF(c.name LIKE ? ESCAPE \'|\', 2, 0))';
        array_push($rp, $start, $wordStart, $any, $any, $any, $any, $any, $any);
    }
    return [
        'where' => $where ? implode(' AND ', $where) : '1=1',
        'where_params' => $wp,
        'rank' => $rank ? implode(' + ', $rank) : '0',
        'rank_params' => $rp,
    ];
}

/* --------------------------------------------------------- variants ---- */

/** Extra SELECT columns product cards need to handle products that have variants. */
const PRODUCT_LIST_EXTRA = ', (SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id = p.id AND pv.is_active = 1) AS variant_count,
    (SELECT COALESCE(SUM(pv.stock), 0) FROM product_variants pv WHERE pv.product_id = p.id AND pv.is_active = 1) AS variant_stock,
    (SELECT COUNT(*) FROM favorites fw WHERE fw.product_id = p.id) AS wish_count,
    (SELECT COUNT(*) FROM product_reviews pr WHERE pr.product_id = p.id AND pr.status = \'published\') AS review_count,
    (SELECT AVG(pr2.rating) FROM product_reviews pr2 WHERE pr2.product_id = p.id AND pr2.status = \'published\') AS review_avg';

function product_variants_for(int $productId, bool $activeOnly = true): array {
    $sql = 'SELECT * FROM product_variants WHERE product_id = ?' . ($activeOnly ? ' AND is_active = 1' : '') . ' ORDER BY sort_order, id';
    $stmt = db()->prepare($sql);
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

/**
 * Color and size options for a product: ['color' => [...], 'size' => [...]].
 * Names used by a variant but missing from product_options (e.g. data from
 * before options existed) are added as bare rows so nothing ever disappears.
 */
function product_options_for(int $productId): array {
    $stmt = db()->prepare('SELECT * FROM product_options WHERE product_id = ? ORDER BY sort_order, id');
    $stmt->execute([$productId]);
    $out = ['color' => [], 'size' => []];
    foreach ($stmt->fetchAll() as $o) { $out[$o['kind']][$o['name']] = $o; }
    foreach (product_variants_for($productId, false) as $v) {
        foreach (['color', 'size'] as $kind) {
            $name = $v[$kind] ?? null;
            if ($name !== null && $name !== '' && !isset($out[$kind][$name])) {
                $out[$kind][$name] = ['id' => null, 'kind' => $kind, 'name' => $name, 'swatch' => null, 'image' => null,
                    'weight_grams' => null, 'height_mm' => null, 'width_mm' => null, 'depth_mm' => null];
            }
        }
    }
    return ['color' => array_values($out['color']), 'size' => array_values($out['size'])];
}

/** Deletes an uploaded file, but only ever from inside the uploads folder. */
function delete_upload_file(?string $urlPath): void {
    if (!$urlPath || strpos($urlPath, UPLOAD_URL . '/') !== 0) return;
    $file = UPLOAD_DIR . '/' . basename($urlPath);
    if (is_file($file)) @unlink($file);
}

/**
 * Puts an order's items back into stock ($direction = +1, when it's cancelled)
 * or takes them out again ($direction = -1, when a cancelled order is revived).
 * Returns false — changing nothing — if there isn't enough stock to revive it.
 * Call inside a transaction.
 */
function order_stock_adjust(PDO $pdo, int $orderId, int $direction): bool {
    $items = $pdo->prepare('SELECT product_id, variant_id, quantity FROM order_items WHERE order_id = ?');
    $items->execute([$orderId]);
    foreach ($items->fetchAll() as $it) {
        $table = $it['variant_id'] ? 'product_variants' : 'products';
        $rowId = $it['variant_id'] ?: $it['product_id'];
        if (!$rowId) continue; // product was deleted since — nothing to adjust
        if ($direction > 0) {
            $pdo->prepare("UPDATE $table SET stock = stock + ? WHERE id = ?")->execute([$it['quantity'], $rowId]);
        } else {
            $st = $pdo->prepare("UPDATE $table SET stock = stock - ? WHERE id = ? AND stock >= ?");
            $st->execute([$it['quantity'], $rowId, $it['quantity']]);
            if ($st->rowCount() < 1) return false;
        }
    }
    return true;
}

/* --------------------------------------------------- order tracking - */

/**
 * Appends a status-history row (used at order creation and every admin status change).
 * $from is the status the order had before, and $admin (id + name) is who changed it —
 * both are recorded for the admin portal only; customers never see them.
 * @param array{id:int,name:string}|null $admin
 */
function order_status_add(int $orderId, string $status, ?string $note = null, ?string $from = null, ?array $admin = null): void {
    db()->prepare('INSERT INTO order_status_history (order_id, from_status, status, note, changed_by, changed_by_name) VALUES (?,?,?,?,?,?)')
        ->execute([$orderId, $from, $status, $note, $admin['id'] ?? null, isset($admin['name']) ? mb_substr((string) $admin['name'], 0, 120) : null]);
}

/**
 * An order's status timeline, oldest first. By default only the customer-safe columns are
 * returned; pass $forAdmin = true (admin portal only) to also get from_status / changed_by_name.
 */
function order_status_history(int $orderId, bool $forAdmin = false): array {
    $cols = $forAdmin ? 'id, order_id, from_status, status, note, changed_at, changed_by, changed_by_name' : 'id, order_id, status, note, changed_at';
    $stmt = db()->prepare("SELECT $cols FROM order_status_history WHERE order_id = ? ORDER BY changed_at ASC, id ASC");
    $stmt->execute([$orderId]);
    return $stmt->fetchAll();
}

require_once __DIR__ . '/branding.php';
require_once __DIR__ . '/admin_log.php';
require_once __DIR__ . '/coupons.php';
require_once __DIR__ . '/reviews.php';
