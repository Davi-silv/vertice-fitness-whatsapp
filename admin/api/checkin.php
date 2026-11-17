<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_permission('checkins.write');

if (!is_post()) {
    redirect(admin_url('pages/checkin.php'));
}
verify_csrf();

$pdo = db();
$action = $_POST['action'] ?? '';

if ($action === 'search') {
    $q = trim((string) ($_POST['q'] ?? ''));
    $scope = unit_scope_sql('m.unit_id', ':uid');
    $params = $scope['params'];
    $sql = "SELECT m.* FROM members m WHERE 1=1 {$scope['sql']} AND (
              m.name LIKE :q OR m.phone LIKE :q OR m.document LIKE :q OR cast(m.id AS TEXT) = :qid
            ) ORDER BY m.name LIMIT 10";
    $params[':q'] = '%' . $q . '%';
    $params[':qid'] = $q;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $matches = [];
    $blockOverdue = setting('block_overdue_checkin', '1') === '1';
    $grace = (int) setting('overdue_grace_days', '3');

    foreach ($stmt->fetchAll() as $m) {
        $plan = $pdo->prepare(
            "SELECT p.name FROM subscriptions s
             INNER JOIN plans p ON p.id = s.plan_id
             WHERE s.member_id = ? AND s.status = 'active'
             ORDER BY s.id DESC LIMIT 1"
        );
        $plan->execute([(int) $m['id']]);
        $planName = $plan->fetchColumn() ?: null;

        $over = $pdo->prepare(
            "SELECT COALESCE(SUM(amount_cents),0) FROM invoices
             WHERE member_id = ? AND status = 'open'
             AND due_date < date('now', ?)"
        );
        $over->execute([(int) $m['id'], '-' . $grace . ' days']);
        $overdue = (int) $over->fetchColumn();

        $matches[] = [
            'id' => (int) $m['id'],
            'name' => $m['name'],
            'phone' => $m['phone'],
            'status' => $m['status'],
            'plan_name' => $planName,
            'overdue_cents' => $overdue,
            'blocked' => $blockOverdue && $overdue > 0,
        ];
    }
    $_SESSION['_checkin_result'] = ['matches' => $matches];
    redirect(admin_url('pages/checkin.php'));
}

if ($action === 'checkin') {
    $memberId = (int) $_POST['member_id'];
    $st = $pdo->prepare('SELECT * FROM members WHERE id = ?');
    $st->execute([$memberId]);
    $m = $st->fetch();
    if (!$m) {
        flash('error', 'Aluno não encontrado.');
        redirect(admin_url('pages/checkin.php'));
    }

    $blockOverdue = setting('block_overdue_checkin', '1') === '1';
    $grace = (int) setting('overdue_grace_days', '3');
    $over = $pdo->prepare(
        "SELECT COALESCE(SUM(amount_cents),0) FROM invoices
         WHERE member_id = ? AND status = 'open' AND due_date < date('now', ?)"
    );
    $over->execute([$memberId, '-' . $grace . ' days']);
    if ($blockOverdue && (int) $over->fetchColumn() > 0) {
        flash('error', 'Check-in bloqueado: aluno inadimplente.');
        redirect(admin_url('pages/checkin.php'));
    }

    $unitId = current_unit_id() ?? (int) $m['unit_id'];
    $pdo->prepare(
        'INSERT INTO checkins (member_id, unit_id, method, created_by) VALUES (?, ?, ?, ?)'
    )->execute([$memberId, $unitId, 'manual', current_user()['id'] ?? null]);
    audit('checkin', 'members', $memberId);
    flash('ok', 'Check-in liberado para ' . $m['name'] . '.');
}

redirect(admin_url('pages/checkin.php'));
