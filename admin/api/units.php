<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_permission('units.write');

if (!is_post()) {
    redirect(admin_url('pages/units.php'));
}
verify_csrf();

$pdo = db();
$action = $_POST['action'] ?? '';
$name = trim((string) $_POST['name']);
$slug = strtolower(trim((string) $_POST['slug']));
$address = trim((string) ($_POST['address'] ?? '')) ?: null;
$phone = trim((string) ($_POST['phone'] ?? '')) ?: null;
$active = (int) ($_POST['active'] ?? 1);

try {
    if ($action === 'create') {
        $pdo->prepare(
            'INSERT INTO units (name, slug, address, phone, active) VALUES (?, ?, ?, ?, ?)'
        )->execute([$name, $slug, $address, $phone, $active]);
        audit('unit.create', 'units', (int) $pdo->lastInsertId());
        flash('ok', 'Unidade criada.');
    } elseif ($action === 'update') {
        $id = (int) $_POST['id'];
        $pdo->prepare(
            'UPDATE units SET name=?, slug=?, address=?, phone=?, active=? WHERE id=?'
        )->execute([$name, $slug, $address, $phone, $active, $id]);
        audit('unit.update', 'units', $id);
        flash('ok', 'Unidade atualizada.');
    }
} catch (Throwable $e) {
    flash('error', 'Não foi possível salvar (slug único?).');
}

redirect(admin_url('pages/units.php'));
