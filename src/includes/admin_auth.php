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
    // The session says "admin", but the account may have been removed since — treat that as signed out.
    if (current_admin() === null) {
        unset($_SESSION['admin_id']);
        redirect('/admin/login.php');
    }
}

/** Only the Owner role may open the Activity log (staff accounts are the ones being audited). */
function admin_is_owner(): bool {
    $a = current_admin();
    return $a !== null && ($a['role'] ?? '') === 'owner';
}

function require_owner(): void {
    require_admin();
    if (!admin_is_owner()) {
        flash_set('error', 'Only the store owner can open that page.');
        redirect('/admin/index.php');
    }
}

/**
 * @return array{0: bool, 1: ?int} [success, seconds_until_retry (if throttled)]
 */
function attempt_admin_login(string $username, string $password): array {
    $username = trim($username);
    $wait = login_throttle_check('admin', $username);
    if ($wait !== null) {
        return [false, $wait];
    }

    $stmt = db()->prepare('SELECT id, username, name, password_hash FROM admins WHERE username = ?');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($password, $row['password_hash'])) {
        login_throttle_hit('admin', $username);
        // Wrong password for a real account is logged under that admin's name (so they can see
        // someone tried); an unknown username is logged as the text that was typed.
        admin_log('auth.login_failed', $row ? 'Wrong password for "' . $row['username'] . '"' : 'Sign-in attempt with unknown username "' . admin_log_clip($username, 60) . '"',
            null, null, [], $row ? ['id' => (int) $row['id'], 'name' => $row['name'], 'username' => $row['username']] : ['name' => admin_log_clip($username, 60) ?: 'Unknown', 'username' => null]);
        return [false, null];
    }
    login_throttle_clear('admin', $username);
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $row['id'];
    admin_log('auth.login', 'Signed in', null, null, [], ['id' => (int) $row['id'], 'name' => $row['name'], 'username' => $row['username']]);
    return [true, null];
}

function admin_logout(): void {
    if (admin_logged_in()) admin_log('auth.logout', 'Signed out');
    unset($_SESSION['admin_id']);
    session_regenerate_id(true);
}
