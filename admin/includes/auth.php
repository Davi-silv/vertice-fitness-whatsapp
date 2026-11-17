<?php
declare(strict_types=1);

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $stmt = db()->prepare(
        'SELECT u.*, r.slug AS role_slug, r.name AS role_name
         FROM users u
         INNER JOIN roles r ON r.id = u.role_id
         WHERE u.id = ? AND u.active = 1'
    );
    $stmt->execute([(int) $_SESSION['user_id']]);
    $row = $stmt->fetch();
    $user = $row ?: null;
    if (!$user) {
        unset($_SESSION['user_id']);
    }
    return $user;
}

function require_login(): void
{
    if (!current_user()) {
        redirect(admin_url('login.php'));
    }
}

function attempt_login(string $email, string $password): bool
{
    $stmt = db()->prepare(
        'SELECT u.*, r.slug AS role_slug, r.name AS role_name
         FROM users u
         INNER JOIN roles r ON r.id = u.role_id
         WHERE lower(u.email) = lower(?) AND u.active = 1'
    );
    $stmt->execute([trim($email)]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    if ($user['unit_id'] !== null) {
        $_SESSION['unit_filter'] = (int) $user['unit_id'];
    }
    audit('login', 'users', (int) $user['id']);
    return true;
}

function logout_user(): void
{
    $user = current_user();
    if ($user) {
        audit('logout', 'users', (int) $user['id']);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
    }
    session_destroy();
}
