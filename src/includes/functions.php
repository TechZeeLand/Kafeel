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

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
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
    $sql = 'SELECT c.id, c.quantity, c.variant_id, p.id AS product_id, p.name, p.slug, p.price, p.image_main,
                   p.stock AS product_stock, p.weight_grams,
                   v.color AS variant_color, v.size AS variant_size, v.price_delta, v.stock AS variant_stock
            FROM cart_items c
            JOIN products p ON p.id = c.product_id
            LEFT JOIN product_variants v ON v.id = c.variant_id
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

function cart_add(int $productId, int $qty = 1, ?int $variantId = null): void {
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
        $upd = $pdo->prepare('UPDATE cart_items SET quantity = quantity + ? WHERE id = ?');
        $upd->execute([$qty, $row['id']]);
    } else {
        $ins = $pdo->prepare('INSERT INTO cart_items (user_id, session_id, product_id, variant_id, quantity) VALUES (?,?,?,?,?)');
        $ins->execute([$uid, $uid ? null : $sid, $productId, $variantId, $qty]);
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

/** All settings as a flat [key => value] array, cached for the request. */
function all_settings(): array {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT setting_key, setting_value FROM settings')->fetchAll() as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $cache;
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
}

/** Theme + seasonal-effect settings, with sane defaults if unset. */
function theme_settings(): array {
    return [
        'primary' => get_setting('theme_primary', '#a97c34'),
        'secondary' => get_setting('theme_secondary', '#5f7d5b'),
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

/* --------------------------------------------------------- variants - */

function product_variants_for(int $productId, bool $activeOnly = true): array {
    $sql = 'SELECT * FROM product_variants WHERE product_id = ?' . ($activeOnly ? ' AND is_active = 1' : '') . ' ORDER BY sort_order, id';
    $stmt = db()->prepare($sql);
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

/* --------------------------------------------------- order tracking - */

/** Appends a status-history row (used at order creation and every admin status change). */
function order_status_add(int $orderId, string $status, ?string $note = null): void {
    db()->prepare('INSERT INTO order_status_history (order_id, status, note) VALUES (?,?,?)')
        ->execute([$orderId, $status, $note]);
}

function order_status_history(int $orderId): array {
    $stmt = db()->prepare('SELECT * FROM order_status_history WHERE order_id = ? ORDER BY changed_at ASC, id ASC');
    $stmt->execute([$orderId]);
    return $stmt->fetchAll();
}
