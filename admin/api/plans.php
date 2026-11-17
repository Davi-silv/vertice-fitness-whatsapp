<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_permission('plans.write');

if (!is_post()) {
    redirect(admin_url('pages/plans.php'));
}
verify_csrf();

$action = $_POST['action'] ?? '';
$pdo = db();
$name = trim((string) ($_POST['name'] ?? ''));
$slug = strtolower(trim((string) ($_POST['slug'] ?? '')));
$price = parse_money_to_cents((string) ($_POST['price'] ?? '0'));
$days = max(1, (int) ($_POST['billing_days'] ?? 30));
$desc = trim((string) ($_POST['description'] ?? '')) ?: null;
$active = (int) ($_POST['active'] ?? 1);

try {
    if ($action === 'create') {
        $pdo->prepare(
            'INSERT INTO plans (name, slug, price_cents, billing_days, description, active)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$name, $slug, $price, $days, $desc, $active]);
        audit('plan.create', 'plans', (int) $pdo->lastInsertId());
        flash('ok', 'Plano criado.');
    } elseif ($action === 'update') {
        $id = (int) $_POST['id'];
        $pdo->prepare(
            'UPDATE plans SET name=?, slug=?, price_cents=?, billing_days=?, description=?, active=? WHERE id=?'
        )->execute([$name, $slug, $price, $days, $desc, $active, $id]);
        audit('plan.update', 'plans', $id);
        flash('ok', 'Plano atualizado.');
    }
} catch (Throwable $e) {
    flash('error', 'Não foi possível salvar o plano (slug único?).');
}

redirect(admin_url('pages/plans.php'));
