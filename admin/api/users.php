<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_permission('users.write');

if (!is_post()) {
    redirect(admin_url('pages/users.php'));
}
verify_csrf();

if (($_POST['action'] ?? '') === 'create') {
    try {
        db()->prepare(
            'INSERT INTO users (unit_id, role_id, name, email, password_hash, active)
             VALUES (?, ?, ?, ?, ?, 1)'
        )->execute([
            ($_POST['unit_id'] ?? '') !== '' ? (int) $_POST['unit_id'] : null,
            (int) $_POST['role_id'],
            trim((string) $_POST['name']),
            trim((string) $_POST['email']),
            password_hash((string) $_POST['password'], PASSWORD_DEFAULT),
        ]);
        audit('user.create', 'users', (int) db()->lastInsertId());
        flash('ok', 'Usuário criado.');
    } catch (Throwable $e) {
        flash('error', 'Não foi possível criar (e-mail único?).');
    }
}

redirect(admin_url('pages/users.php'));
