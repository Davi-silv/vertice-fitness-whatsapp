<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_permission('schedule.write');

if (!is_post()) {
    redirect(admin_url('pages/schedule.php'));
}
verify_csrf();

$pdo = db();
$action = $_POST['action'] ?? '';

if ($action === 'create_class') {
    $pdo->prepare(
        'INSERT INTO classes (unit_id, teacher_id, name, capacity, duration_min, active)
         VALUES (?, ?, ?, ?, ?, 1)'
    )->execute([
        (int) $_POST['unit_id'],
        ($_POST['teacher_id'] ?? '') !== '' ? (int) $_POST['teacher_id'] : null,
        trim((string) $_POST['name']),
        max(1, (int) ($_POST['capacity'] ?? 20)),
        max(15, (int) ($_POST['duration_min'] ?? 60)),
    ]);
    audit('class.create', 'classes', (int) $pdo->lastInsertId());
    flash('ok', 'Aula criada.');
}

if ($action === 'create_session') {
    $classId = (int) $_POST['class_id'];
    $st = $pdo->prepare('SELECT * FROM classes WHERE id = ?');
    $st->execute([$classId]);
    $class = $st->fetch();
    if (!$class) {
        flash('error', 'Aula inválida.');
        redirect(admin_url('pages/schedule.php'));
    }
    $starts = str_replace('T', ' ', (string) $_POST['starts_at']);
    if (strlen($starts) === 16) {
        $starts .= ':00';
    }
    $ends = date('Y-m-d H:i:s', strtotime($starts . ' +' . (int) $class['duration_min'] . ' minutes'));
    $pdo->prepare(
        'INSERT INTO class_sessions (class_id, starts_at, ends_at, status) VALUES (?, ?, ?, ?)'
    )->execute([$classId, $starts, $ends, 'scheduled']);
    audit('session.create', 'class_sessions', (int) $pdo->lastInsertId());
    flash('ok', 'Sessão agendada.');
}

if ($action === 'book') {
    $sessionId = (int) $_POST['session_id'];
    $memberId = (int) $_POST['member_id'];
    $info = $pdo->prepare(
        'SELECT cs.id, cl.capacity,
                (SELECT COUNT(*) FROM class_bookings b WHERE b.session_id = cs.id AND b.status = "booked") AS booked
         FROM class_sessions cs
         INNER JOIN classes cl ON cl.id = cs.class_id
         WHERE cs.id = ?'
    );
    $info->execute([$sessionId]);
    $row = $info->fetch();
    if (!$row) {
        flash('error', 'Sessão inválida.');
        redirect(admin_url('pages/schedule.php'));
    }
    if ((int) $row['booked'] >= (int) $row['capacity']) {
        flash('error', 'Turma lotada.');
        redirect(admin_url('pages/schedule.php'));
    }
    try {
        $pdo->prepare(
            'INSERT INTO class_bookings (session_id, member_id, status) VALUES (?, ?, ?)'
        )->execute([$sessionId, $memberId, 'booked']);
        audit('booking.create', 'class_bookings', (int) $pdo->lastInsertId());
        flash('ok', 'Reserva confirmada.');
    } catch (Throwable $e) {
        flash('error', 'Aluno já reservado nesta sessão.');
    }
}

redirect(admin_url('pages/schedule.php'));
