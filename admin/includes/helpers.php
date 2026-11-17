<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function consume_flashes(): array
{
    $items = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return is_array($items) ? $items : [];
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('CSRF inválido');
    }
}

function money_br(int $cents): string
{
    return 'R$ ' . number_format($cents / 100, 2, ',', '.');
}

function parse_money_to_cents(string $value): int
{
    $clean = preg_replace('/[^\d,.-]/', '', $value) ?? '0';
    $clean = str_replace('.', '', $clean);
    $clean = str_replace(',', '.', $clean);
    return (int) round(((float) $clean) * 100);
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function is_post(): bool
{
    return request_method() === 'POST';
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function current_unit_id(): ?int
{
    if (array_key_exists('unit_filter', $_SESSION)) {
        if ($_SESSION['unit_filter'] === '' || $_SESSION['unit_filter'] === null) {
            return null;
        }
        return (int) $_SESSION['unit_filter'];
    }
    $user = current_user();
    if (!$user) {
        return null;
    }
    return $user['unit_id'] !== null ? (int) $user['unit_id'] : null;
}

function set_unit_filter(?int $unitId): void
{
    $_SESSION['unit_filter'] = $unitId;
}

function setting(string $key, ?string $default = null): ?string
{
    $stmt = db()->prepare('SELECT value FROM settings WHERE key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? (string) $row['value'] : $default;
}

function audit(string $action, ?string $entity = null, ?int $entityId = null, array $meta = []): void
{
    $user = current_user();
    $stmt = db()->prepare(
        'INSERT INTO audit_logs (user_id, action, entity, entity_id, meta) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $user['id'] ?? null,
        $action,
        $entity,
        $entityId,
        $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
    ]);
}

function admin_url(string $path = ''): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/admin/index.php');
    if (preg_match('#^(.*?/admin)(?:/|$)#', $script, $m)) {
        $base = $m[1];
    } else {
        $base = '/admin';
    }
    $path = ltrim($path, '/');
    return $path === '' ? $base . '/' : $base . '/' . $path;
}

function unit_scope_sql(string $column = 'unit_id', string $param = ':unit_id'): array
{
    $unitId = current_unit_id();
    if ($unitId === null) {
        return ['sql' => '', 'params' => []];
    }
    return ['sql' => " AND {$column} = {$param}", 'params' => [$param => $unitId]];
}

function page_url(string $file): string
{
    return admin_url('pages/' . ltrim($file, '/'));
}

function admin_base(): string
{
    return rtrim(admin_url(''), '/');
}
