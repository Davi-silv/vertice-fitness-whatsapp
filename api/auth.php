<?php
declare(strict_types=1);

/**
 * API de autenticação da landing → painel PHP.
 * GET  → { csrf, units, user, adminUrl }
 * POST → login | register (JSON ou form-urlencoded)
 */

header('Cache-Control: no-store');

function auth_payload(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    if (str_contains(strtolower($contentType), 'application/json')) {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
    return $_POST;
}

function wants_json(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    return str_contains(strtolower($accept), 'application/json')
        || str_contains(strtolower($contentType), 'application/json')
        || isset($_GET['json']);
}

function auth_reply(array $data, int $status = 200): never
{
    if (wants_json()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!empty($data['ok']) && !empty($data['redirect'])) {
        header('Location: ' . $data['redirect']);
        exit;
    }

    $tab = (($data['tab'] ?? '') === 'register') ? 'register' : 'login';
    $err = rawurlencode((string) ($data['error'] ?? 'Falha na autenticação'));
    header('Location: /?login=1&tab=' . $tab . '&msg=' . $err);
    exit;
}

try {
    require_once dirname(__DIR__) . '/admin/includes/bootstrap.php';
} catch (Throwable $e) {
    auth_reply([
        'ok' => false,
        'error' => 'Backend indisponível. Rode: php database/seed.php && php -S 0.0.0.0:8080 router.php',
    ], 503);
}

function auth_boot(): array
{
    $units = db()->query('SELECT id, name FROM units WHERE active = 1 ORDER BY name')->fetchAll();
    $user = current_user();
    return [
        'ok' => true,
        'csrf' => csrf_token(),
        'units' => array_map(static fn(array $u): array => [
            'id' => (int) $u['id'],
            'name' => (string) $u['name'],
        ], $units),
        'user' => $user ? [
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
            'role' => (string) $user['role_slug'],
        ] : null,
        'adminUrl' => admin_url('index.php'),
        'php' => true,
    ];
}

if (request_method() === 'GET' || request_method() === 'HEAD') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(auth_boot(), JSON_UNESCAPED_UNICODE);
    exit;
}

if (!is_post()) {
    auth_reply(['ok' => false, 'error' => 'Método não permitido'], 405);
}

$payload = auth_payload();
$token = (string) ($payload['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($token === '' || !hash_equals(csrf_token(), $token)) {
    auth_reply(['ok' => false, 'error' => 'Sessão expirada. Recarregue e tente de novo.'], 419);
}

$action = (string) ($payload['action'] ?? $payload['form'] ?? '');

try {
    if ($action === 'login' || $action === 'staff_login') {
        $email = trim((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');
        if ($email === '' || $password === '') {
            auth_reply(['ok' => false, 'error' => 'Informe e-mail e senha.', 'tab' => 'login'], 422);
        }
        if (!attempt_login($email, $password)) {
            auth_reply(['ok' => false, 'error' => 'E-mail ou senha inválidos.', 'tab' => 'login'], 401);
        }
        auth_reply([
            'ok' => true,
            'redirect' => admin_url('index.php'),
            'user' => [
                'name' => (string) (current_user()['name'] ?? ''),
            ],
        ]);
    }

    if ($action === 'register' || $action === 'staff_register') {
        $name = trim((string) ($payload['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($payload['email'] ?? '')));
        $password = (string) ($payload['password'] ?? '');
        $password2 = (string) ($payload['password_confirm'] ?? '');
        $unitId = (int) ($payload['unit_id'] ?? 0);
        $units = db()->query('SELECT id, name FROM units WHERE active = 1 ORDER BY name')->fetchAll();

        if ($name === '' || $email === '' || $password === '') {
            auth_reply(['ok' => false, 'error' => 'Preencha nome, e-mail e senha.', 'tab' => 'register'], 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            auth_reply(['ok' => false, 'error' => 'Informe um e-mail válido.', 'tab' => 'register'], 422);
        }
        if (strlen($password) < 8) {
            auth_reply(['ok' => false, 'error' => 'A senha deve ter pelo menos 8 caracteres.', 'tab' => 'register'], 422);
        }
        if ($password !== $password2) {
            auth_reply(['ok' => false, 'error' => 'As senhas não coincidem.', 'tab' => 'register'], 422);
        }

        $exists = db()->prepare('SELECT id FROM users WHERE lower(email) = lower(?) LIMIT 1');
        $exists->execute([$email]);
        if ($exists->fetch()) {
            auth_reply(['ok' => false, 'error' => 'Já existe uma conta com este e-mail. Faça login.', 'tab' => 'login'], 409);
        }

        $role = db()->query("SELECT id FROM roles WHERE slug = 'recepcao' LIMIT 1")->fetch();
        if (!$role) {
            auth_reply(['ok' => false, 'error' => 'Sistema sem perfis. Execute: php database/seed.php', 'tab' => 'register'], 503);
        }

        if ($unitId <= 0 && $units) {
            $unitId = (int) $units[0]['id'];
        }

        db()->prepare(
            'INSERT INTO users (unit_id, role_id, name, email, password_hash, active)
             VALUES (?, ?, ?, ?, ?, 1)'
        )->execute([
            $unitId > 0 ? $unitId : null,
            (int) $role['id'],
            $name,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
        ]);
        $newId = (int) db()->lastInsertId();
        audit('user.register', 'users', $newId, ['source' => 'api']);

        if (!attempt_login($email, $password)) {
            auth_reply([
                'ok' => true,
                'redirect' => '/?login=1&tab=login',
                'message' => 'Conta criada. Faça login para continuar.',
            ]);
        }

        auth_reply([
            'ok' => true,
            'redirect' => admin_url('index.php'),
            'user' => ['name' => $name],
        ]);
    }

    auth_reply(['ok' => false, 'error' => 'Ação inválida.'], 400);
} catch (Throwable $e) {
    error_log('auth api: ' . $e->getMessage());
    auth_reply(['ok' => false, 'error' => 'Erro interno no login.'], 500);
}
