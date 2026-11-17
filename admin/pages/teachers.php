<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
require_permission('teachers.read');

$pdo = db();
$scope = unit_scope_sql('t.unit_id', ':uid');
$stmt = $pdo->prepare(
    "SELECT t.*, u.name AS unit_name
     FROM teachers t
     INNER JOIN units u ON u.id = t.unit_id
     WHERE 1=1 {$scope['sql']}
     ORDER BY t.name"
);
$stmt->execute($scope['params']);
$rows = $stmt->fetchAll();
$units = $pdo->query('SELECT id, name FROM units WHERE active = 1 ORDER BY name')->fetchAll();

layout_header('Professores', 'teachers');
?>
<section class="split-2">
  <div class="panel">
    <h2>Equipe</h2>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Nome</th><th>Especialidade</th><th>Comissão %</th><th>Unidade</th><th>Ativo</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= e($r['name']) ?><br><span class="muted"><?= e($r['phone']) ?></span></td>
            <td><?= e($r['specialty'] ?: '—') ?></td>
            <td><?= e((string)$r['commission_percent']) ?>%</td>
            <td><?= e($r['unit_name']) ?></td>
            <td><?= (int)$r['active'] ? 'Sim' : 'Não' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if (can('teachers.write')): ?>
  <div class="panel">
    <h2>Novo professor</h2>
    <form method="post" action="<?= e(admin_url('api/teachers.php')) ?>" class="stack-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <label>Nome<input name="name" required></label>
      <label>Telefone<input name="phone"></label>
      <label>Especialidade<input name="specialty"></label>
      <label>Comissão %<input type="number" step="0.1" name="commission_percent" value="0"></label>
      <label>Unidade
        <select name="unit_id" required>
          <?php foreach ($units as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= (int)(current_unit_id() ?? 0) === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button class="btn btn-primary" type="submit">Salvar</button>
    </form>
  </div>
  <?php endif; ?>
</section>
<?php layout_footer(); ?>
