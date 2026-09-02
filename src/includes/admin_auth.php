<?php
require_once __DIR__ . '/functions.php';

function admin_logged_in(): bool {
    return !empty($_SESSION['admin_id']);
}

function current_admin(): ?array {
    static $admin = false;
    if ($admin === false) {
        if (empty($_SESSION['admin_id'])) {
            $admin = null;
        } else {
            $stmt = db()->prepare('SELECT id, username, name, role FROM admins WHERE id = ?');
            $stmt->execute([$_SESSION['admin_id']]);
            $admin = $stmt->fetch() ?: null;
        }
    }
    return $admin;
}

function require_admin(): void {
    if (!admin_logged_in()) {
        redirect('/admin/login.php');
    }
}

function attempt_admin_login(string $username, string $password): bool {
    $stmt = db()->prepare('SELECT id, password_hash FROM admins WHERE username = ?');
    $stmt->execute([trim($username)]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($password, $row['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $row['id'];
    return true;
}

function admin_logout(): void {
    unset($_SESSION['admin_id']);
    session_regenerate_id(true);
}
