<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
require_permission('schedule.read');

$pdo = db();
$scope = unit_scope_sql('cl.unit_id', ':uid');
$sessions = $pdo->prepare(
    "SELECT cs.*, cl.name AS class_name, cl.capacity, t.name AS teacher_name,
            (SELECT COUNT(*) FROM class_bookings b WHERE b.session_id = cs.id AND b.status = 'booked') AS booked
     FROM class_sessions cs
     INNER JOIN classes cl ON cl.id = cs.class_id
     LEFT JOIN teachers t ON t.id = cl.teacher_id
     WHERE cs.starts_at >= datetime('now', '-1 day') {$scope['sql']}
     ORDER BY cs.starts_at ASC LIMIT 80"
);
$sessions->execute($scope['params']);
$rows = $sessions->fetchAll();

$classes = $pdo->prepare(
    'SELECT c.*, t.name AS teacher_name FROM classes c
     LEFT JOIN teachers t ON t.id = c.teacher_id
     WHERE c.active = 1 '
    . (current_unit_id() ? ' AND c.unit_id = ' . (int) current_unit_id() : '')
    . ' ORDER BY c.name'
);
$classes->execute();
$classRows = $classes->fetchAll();

$teachers = $pdo->query(
    'SELECT id, name FROM teachers WHERE active = 1'
    . (current_unit_id() ? ' AND unit_id = ' . (int) current_unit_id() : '')
    . ' ORDER BY name'
)->fetchAll();

$units = $pdo->query('SELECT id, name FROM units WHERE active = 1 ORDER BY name')->fetchAll();
$members = $pdo->query(
    'SELECT id, name FROM members WHERE status = "active"'
    . (current_unit_id() ? ' AND unit_id = ' . (int) current_unit_id() : '')
    . ' ORDER BY name LIMIT 300'
)->fetchAll();

layout_header('Agenda', 'schedule');
?>
<section class="split-2">
  <div class="panel">
    <h2>Próximas sessões</h2>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Quando</th><th>Aula</th><th>Professor</th><th>Vagas</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= e($r['starts_at']) ?></td>
            <td><?= e($r['class_name']) ?></td>
            <td><?= e($r['teacher_name'] ?: '—') ?></td>
            <td><?= (int)$r['booked'] ?>/<?= (int)$r['capacity'] ?></td>
            <td>
              <?php if (can('schedule.write')): ?>
              <details>
                <summary class="btn btn-sm">Reservar</summary>
                <form method="post" action="<?= e(admin_url('api/schedule.php')) ?>" style="margin-top:.5rem">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="book">
                  <input type="hidden" name="session_id" value="<?= (int)$r['id'] ?>">
                  <select name="member_id" required>
                    <?php foreach ($members as $m): ?>
                      <option value="<?= (int)$m['id'] ?>"><?= e($m['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn-sm btn-primary" type="submit">Confirmar</button>
                </form>
              </details>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="muted">Sem sessões.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if (can('schedule.write')): ?>
  <div class="panel">
    <h2>Nova aula / sessão</h2>
    <form method="post" action="<?= e(admin_url('api/schedule.php')) ?>" class="stack-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_class">
      <label>Nome da aula<input name="name" required></label>
      <label>Capacidade<input type="number" name="capacity" value="20" min="1"></label>
      <label>Duração (min)<input type="number" name="duration_min" value="60" min="15"></label>
      <label>Professor
        <select name="teacher_id">
          <option value="">—</option>
          <?php foreach ($teachers as $t): ?>
            <option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Unidade
        <select name="unit_id" required>
          <?php foreach ($units as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= (int)(current_unit_id() ?? 0) === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button class="btn btn-primary" type="submit">Criar aula</button>
    </form>

    <h2 style="margin-top:1.5rem">Agendar sessão</h2>
    <form method="post" action="<?= e(admin_url('api/schedule.php')) ?>" class="stack-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_session">
      <label>Aula
        <select name="class_id" required>
          <?php foreach ($classRows as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Início<input type="datetime-local" name="starts_at" required></label>
      <button class="btn btn-primary" type="submit">Agendar</button>
    </form>
  </div>
  <?php endif; ?>
</section>
<?php layout_footer(); ?>
