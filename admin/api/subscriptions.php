<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_permission('plans.write');

if (!is_post()) {
    redirect(admin_url('pages/plans.php'));
}
verify_csrf();

$pdo = db();
$action = $_POST['action'] ?? '';

if ($action === 'cancel') {
    $id = (int) $_POST['id'];
    $pdo->prepare(
        "UPDATE subscriptions SET status = 'cancelled', ends_at = date('now') WHERE id = ?"
    )->execute([$id]);
    audit('subscription.cancel', 'subscriptions', $id);
    flash('ok', 'Assinatura cancelada.');
    redirect(admin_url('pages/plans.php'));
}

if ($action === 'activate') {
    $memberId = (int) $_POST['member_id'];
    $planId = (int) $_POST['plan_id'];
    $member = $pdo->prepare('SELECT * FROM members WHERE id = ?');
    $member->execute([$memberId]);
    $m = $member->fetch();
    $plan = $pdo->prepare('SELECT * FROM plans WHERE id = ? AND active = 1');
    $plan->execute([$planId]);
    $p = $plan->fetch();
    if (!$m || !$p) {
        flash('error', 'Aluno ou plano inválido.');
        redirect(admin_url('pages/plans.php'));
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
        (int) $m['unit_id'],
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
        (int) $m['unit_id'],
        'Mensalidade ' . $p['name'],
        (int) $p['price_cents'],
        'open',
    ]);

    // Commission on plan sale
    $rule = $pdo->query(
        "SELECT * FROM commission_rules WHERE active = 1 AND applies_to = 'plan_sale' LIMIT 1"
    )->fetch();
    if ($rule) {
        $pct = (float) $rule['percent'];
        $base = (int) $p['price_cents'];
        $amount = (int) round($base * $pct / 100);
        $pdo->prepare(
            'INSERT INTO commissions (user_id, unit_id, reference_type, reference_id, base_cents, percent, amount_cents, period, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            current_user()['id'] ?? null,
            (int) $m['unit_id'],
            'subscription',
            $subId,
            $base,
            $pct,
            $amount,
            date('Y-m'),
            'pending',
        ]);
    }

    $pdo->prepare("UPDATE members SET status = 'active' WHERE id = ?")->execute([$memberId]);
    audit('subscription.activate', 'subscriptions', $subId, ['plan_id' => $planId]);
    flash('ok', 'Assinatura ativada e fatura gerada.');
}

redirect(admin_url('pages/plans.php'));
