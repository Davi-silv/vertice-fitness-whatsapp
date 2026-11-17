<?php
declare(strict_types=1);

function user_permissions(?array $user = null): array
{
    static $cache = [];
    $user = $user ?? current_user();
    if (!$user) {
        return [];
    }
    $uid = (int) $user['id'];
    if (isset($cache[$uid])) {
        return $cache[$uid];
    }

    $stmt = db()->prepare(
        'SELECT p.slug
         FROM permissions p
         INNER JOIN role_permissions rp ON rp.permission_id = p.id
         WHERE rp.role_id = ?'
    );
    $stmt->execute([(int) $user['role_id']]);
    $cache[$uid] = array_column($stmt->fetchAll(), 'slug');
    return $cache[$uid];
}

function can(string $permission, ?array $user = null): bool
{
    $perms = user_permissions($user);
    return in_array($permission, $perms, true) || in_array('*', $perms, true);
}

function require_permission(string $permission): void
{
    require_login();
    if (!can($permission)) {
        http_response_code(403);
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || str_starts_with($_SERVER['SCRIPT_NAME'] ?? '', '/admin/api')) {
            json_response(['error' => 'Sem permissão'], 403);
        }
        exit('Acesso negado');
    }
}

function require_any_permission(array $permissions): void
{
    require_login();
    foreach ($permissions as $p) {
        if (can($p)) {
            return;
        }
    }
    http_response_code(403);
    exit('Acesso negado');
}
