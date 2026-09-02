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
            $stmt = db()->prepare('SELECT id, name, email, phone, created_at FROM users WHERE id = ?');
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
    $ins = db()->prepare('INSERT INTO users (name, email, password_hash, phone) VALUES (?,?,?,?)');
    $ins->execute([$name, $email, $hash, $phone ?: null]);
    $userId = (int) db()->lastInsertId();

    $oldSessionId = session_id();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    cart_merge_session_into_user($userId, $oldSessionId);
    return [true, 'Account created.'];
}

function logout_user(): void {
    unset($_SESSION['user_id']);
    session_regenerate_id(true);
}
