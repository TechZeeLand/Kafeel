<?php
require_once __DIR__ . '/functions.php';

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function current_user(): ?array {
    static $user = false;
    if ($user === false) {
        if (empty($_SESSION['user_id'])) {
            $user = null;
        } else {
            $stmt = db()->prepare('SELECT id, name, email, phone, email_verified, created_at FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch() ?: null;
            if (!$user) {
                unset($_SESSION['user_id']);
            }
        }
    }
    return $user;
}

function require_login(): void {
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/account.php';
        redirect('/login.php');
    }
}

function attempt_login(string $email, string $password): bool {
    $stmt = db()->prepare('SELECT id, password_hash, status FROM users WHERE email = ?');
    $stmt->execute([strtolower(trim($email))]);
    $row = $stmt->fetch();
    if (!$row || $row['status'] !== 'active' || !password_verify($password, $row['password_hash'])) {
        return false;
    }
    $oldSessionId = session_id();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $row['id'];
    cart_merge_session_into_user((int) $row['id'], $oldSessionId);
    return true;
}

function register_user(string $name, string $email, string $password, string $phone = ''): array {
    $email = strtolower(trim($email));
    if (strlen($name) < 2) {
        return [false, 'Please enter your full name.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [false, 'Please enter a valid email address.'];
    }
    if (strlen($password) < 8) {
        return [false, 'Password must be at least 8 characters.'];
    }
    $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return [false, 'An account with that email already exists.'];
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $token = bin2hex(random_bytes(32));
    $ins = db()->prepare('INSERT INTO users (name, email, password_hash, phone, email_verified, email_verify_token, email_verify_sent_at) VALUES (?,?,?,?,0,?,NOW())');
    $ins->execute([$name, $email, $hash, $phone ?: null, $token]);
    $userId = (int) db()->lastInsertId();

    send_verification_email($userId, $name, $email, $token);

    $oldSessionId = session_id();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    cart_merge_session_into_user($userId, $oldSessionId);
    return [true, 'Account created.'];
}

/** Builds and sends the "verify your email" message for a newly (re)issued token. */
function send_verification_email(int $userId, string $name, string $email, string $token): bool {
    require_once __DIR__ . '/mail.php';
    $link = rtrim(SITE_URL, '/') . '/verify-email.php?uid=' . $userId . '&token=' . $token;
    $body = '<p>Hi ' . e(explode(' ', $name)[0]) . ',</p>'
        . '<p>Welcome to ' . e(SITE_NAME) . '! Please confirm your email address to activate your account.</p>'
        . '<p style="margin:24px 0;"><a href="' . e($link) . '" style="background:#a97c34;color:#fff;padding:11px 22px;border-radius:6px;text-decoration:none;font-weight:bold;">Verify my email</a></p>'
        . '<p class="muted" style="font-size:0.85rem;color:#8791a6;">Or paste this link into your browser:<br>' . e($link) . '</p>';
    return send_email($email, $name, 'Verify your email — ' . SITE_NAME, email_wrap('Confirm your email address', $body));
}

/** Issues a fresh token and resends the verification email (rate-limited to once per 2 minutes). */
function resend_verification_email(int $userId): bool {
    $stmt = db()->prepare('SELECT name, email, email_verified, email_verify_sent_at FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user || (int) $user['email_verified'] === 1) return false;
    if (!empty($user['email_verify_sent_at']) && (time() - strtotime($user['email_verify_sent_at'])) < 120) {
        return false; // too soon
    }
    $token = bin2hex(random_bytes(32));
    db()->prepare('UPDATE users SET email_verify_token = ?, email_verify_sent_at = NOW() WHERE id = ?')->execute([$token, $userId]);
    return send_verification_email($userId, $user['name'], $user['email'], $token);
}

function logout_user(): void {
    unset($_SESSION['user_id']);
    session_regenerate_id(true);
}
