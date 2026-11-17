<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_permission('teachers.write');

if (!is_post()) {
    redirect(admin_url('pages/teachers.php'));
}
verify_csrf();

if (($_POST['action'] ?? '') === 'create') {
    db()->prepare(
        'INSERT INTO teachers (unit_id, name, phone, specialty, commission_percent, active)
         VALUES (?, ?, ?, ?, ?, 1)'
    )->execute([
        (int) $_POST['unit_id'],
        trim((string) $_POST['name']),
        trim((string) ($_POST['phone'] ?? '')) ?: null,
        trim((string) ($_POST['specialty'] ?? '')) ?: null,
        (float) ($_POST['commission_percent'] ?? 0),
    ]);
    audit('teacher.create', 'teachers', (int) db()->lastInsertId());
    flash('ok', 'Professor cadastrado.');
}

redirect(admin_url('pages/teachers.php'));
