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

function cart_items(): array {
    [$uid, $sid] = cart_identity();
    if ($uid) {
        $stmt = db()->prepare(
            'SELECT c.id, c.quantity, p.id AS product_id, p.name, p.slug, p.price, p.image_main, p.stock, p.weight_grams
             FROM cart_items c JOIN products p ON p.id = c.product_id
             WHERE c.user_id = ? ORDER BY c.id DESC'
        );
        $stmt->execute([$uid]);
    } else {
        $stmt = db()->prepare(
            'SELECT c.id, c.quantity, p.id AS product_id, p.name, p.slug, p.price, p.image_main, p.stock, p.weight_grams
             FROM cart_items c JOIN products p ON p.id = c.product_id
             WHERE c.session_id = ? ORDER BY c.id DESC'
        );
        $stmt->execute([$sid]);
    }
    return $stmt->fetchAll();
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
    return $area === 'outside_dhaka' ? 'Outside Dhaka' : 'Inside Dhaka';
}

/**
 * Shipping fee for a given delivery area + parcel weight: a flat zone fee,
 * plus a per-kg surcharge for every kg (or part of a kg) over the free
 * weight allowance.
 */
function shipping_fee_for_area(string $area, int $weightGrams): float {
    $base = $area === 'outside_dhaka' ? SHIPPING_OUTSIDE_DHAKA_FEE : SHIPPING_INSIDE_DHAKA_FEE;
    $freeGrams = SHIPPING_FREE_WEIGHT_KG * 1000;
    $extraGrams = max(0, $weightGrams - $freeGrams);
    $extraKg = (int) ceil($extraGrams / 1000);
    return $base + ($extraKg * SHIPPING_EXTRA_PER_KG);
}

function cart_add(int $productId, int $qty = 1): void {
    [$uid, $sid] = cart_identity();
    $qty = max(1, $qty);
    $pdo = db();
    if ($uid) {
        $stmt = $pdo->prepare('SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ?');
        $stmt->execute([$uid, $productId]);
    } else {
        $stmt = $pdo->prepare('SELECT id, quantity FROM cart_items WHERE session_id = ? AND product_id = ?');
        $stmt->execute([$sid, $productId]);
    }
    $row = $stmt->fetch();
    if ($row) {
        $upd = $pdo->prepare('UPDATE cart_items SET quantity = quantity + ? WHERE id = ?');
        $upd->execute([$qty, $row['id']]);
    } else {
        $ins = $pdo->prepare('INSERT INTO cart_items (user_id, session_id, product_id, quantity) VALUES (?,?,?,?)');
        $ins->execute([$uid, $uid ? null : $sid, $productId, $qty]);
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
    $stmt = $pdo->prepare('SELECT product_id, quantity FROM cart_items WHERE session_id = ?');
    $stmt->execute([$sessionId]);
    foreach ($stmt->fetchAll() as $row) {
        $existing = $pdo->prepare('SELECT id, quantity FROM cart_items WHERE user_id = ? AND product_id = ?');
        $existing->execute([$userId, $row['product_id']]);
        $ex = $existing->fetch();
        if ($ex) {
            $pdo->prepare('UPDATE cart_items SET quantity = quantity + ? WHERE id = ?')
                ->execute([$row['quantity'], $ex['id']]);
        } else {
            $pdo->prepare('INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?,?,?)')
                ->execute([$userId, $row['product_id'], $row['quantity']]);
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
