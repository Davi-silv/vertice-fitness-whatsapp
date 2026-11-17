<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_permission('commissions.write');

if (!is_post()) {
    redirect(admin_url('pages/commissions.php'));
}
verify_csrf();

$pdo = db();
$action = $_POST['action'] ?? '';

if ($action === 'pay') {
    $id = (int) $_POST['id'];
    $pdo->prepare("UPDATE commissions SET status = 'paid' WHERE id = ?")->execute([$id]);
    audit('commission.pay', 'commissions', $id);
    flash('ok', 'Comissão marcada como paga.');
    $period = $_POST['period'] ?? date('Y-m');
    redirect(admin_url('pages/commissions.php?period=' . urlencode($period)));
}

if ($action === 'rule') {
    $pdo->prepare(
        'INSERT INTO commission_rules (name, percent, applies_to, active) VALUES (?, ?, ?, 1)'
    )->execute([
        trim((string) $_POST['name']),
        (float) $_POST['percent'],
        $_POST['applies_to'] ?? 'plan_sale',
    ]);
    audit('commission_rule.create', 'commission_rules', (int) $pdo->lastInsertId());
    flash('ok', 'Regra adicionada.');
}

redirect(admin_url('pages/commissions.php'));
