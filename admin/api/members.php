<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_permission('members.write');

if (!is_post()) {
    redirect(admin_url('pages/members.php'));
}
verify_csrf();

$action = $_POST['action'] ?? '';
$pdo = db();

try {
    if ($action === 'create') {
        $pdo->prepare(
            'INSERT INTO members (unit_id, name, email, phone, document, birth_date, status, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            (int) $_POST['unit_id'],
            trim((string) $_POST['name']),
            trim((string) ($_POST['email'] ?? '')) ?: null,
            trim((string) ($_POST['phone'] ?? '')) ?: null,
            trim((string) ($_POST['document'] ?? '')) ?: null,
            trim((string) ($_POST['birth_date'] ?? '')) ?: null,
            $_POST['status'] ?? 'active',
            trim((string) ($_POST['notes'] ?? '')) ?: null,
        ]);
        $memberId = (int) $pdo->lastInsertId();
        $planId = (int) ($_POST['plan_id'] ?? 0);
        if ($planId > 0) {
            activate_subscription($memberId, $planId, (int) $_POST['unit_id']);
        }
        audit('member.create', 'members', $memberId);
        flash('ok', 'Aluno cadastrado.');
    } elseif ($action === 'update') {
        $id = (int) $_POST['id'];
        $pdo->prepare(
            'UPDATE members SET unit_id=?, name=?, email=?, phone=?, document=?, birth_date=?, status=?, notes=?
             WHERE id=?'
        )->execute([
            (int) $_POST['unit_id'],
            trim((string) $_POST['name']),
            trim((string) ($_POST['email'] ?? '')) ?: null,
            trim((string) ($_POST['phone'] ?? '')) ?: null,
            trim((string) ($_POST['document'] ?? '')) ?: null,
            trim((string) ($_POST['birth_date'] ?? '')) ?: null,
            $_POST['status'] ?? 'active',
            trim((string) ($_POST['notes'] ?? '')) ?: null,
            $id,
        ]);
        audit('member.update', 'members', $id);
        flash('ok', 'Aluno atualizado.');
        redirect(admin_url('pages/members.php?id=' . $id));
    } else {
        flash('error', 'Ação inválida.');
    }
} catch (Throwable $e) {
    flash('error', 'Erro ao salvar aluno.');
}

redirect(admin_url('pages/members.php'));

function activate_subscription(int $memberId, int $planId, int $unitId): void
{
    $pdo = db();
    $plan = $pdo->prepare('SELECT * FROM plans WHERE id = ? AND active = 1');
    $plan->execute([$planId]);
    $p = $plan->fetch();
    if (!$p) {
        return;
    }
    $pdo->prepare(
        "UPDATE subscriptions SET status = 'cancelled', ends_at = date('now')
         WHERE member_id = ? AND status = 'active'"
    )->execute([$memberId]);

    $days = (int) $p['billing_days'];
    $pdo->prepare(
        'INSERT INTO subscriptions (member_id, plan_id, unit_id, seller_user_id, status, starts_at, ends_at)
         VALUES (?, ?, ?, ?, ?, date("now"), date("now", ?))'
    )->execute([
        $memberId,
        $planId,
        $unitId,
        current_user()['id'] ?? null,
        'active',
        '+' . $days . ' days',
    ]);
    $subId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        'INSERT INTO invoices (member_id, subscription_id, unit_id, description, amount_cents, due_date, status)
         VALUES (?, ?, ?, ?, ?, date("now", "+5 days"), ?)'
    )->execute([
        $memberId,
        $subId,
        $unitId,
        'Mensalidade ' . $p['name'],
        (int) $p['price_cents'],
        'open',
    ]);
}
