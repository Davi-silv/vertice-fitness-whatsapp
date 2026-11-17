<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
require_permission('members.read');

$pdo = db();
$scope = unit_scope_sql('m.unit_id', ':uid');
$q = trim((string) ($_GET['q'] ?? ''));
$params = $scope['params'];
$sql = "SELECT m.*, u.name AS unit_name,
        (SELECT p.name FROM subscriptions s
          INNER JOIN plans p ON p.id = s.plan_id
          WHERE s.member_id = m.id AND s.status = 'active'
          ORDER BY s.id DESC LIMIT 1) AS plan_name
        FROM members m
        INNER JOIN units u ON u.id = m.unit_id
        WHERE 1=1 {$scope['sql']}";
if ($q !== '') {
    $sql .= ' AND (m.name LIKE :q OR m.email LIKE :q OR m.phone LIKE :q OR m.document LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
$sql .= ' ORDER BY m.name ASC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$units = $pdo->query('SELECT id, name FROM units WHERE active = 1 ORDER BY name')->fetchAll();
$plans = $pdo->query('SELECT id, name, price_cents FROM plans WHERE active = 1 ORDER BY price_cents')->fetchAll();
$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$edit = null;
if ($editId) {
    $st = $pdo->prepare('SELECT * FROM members WHERE id = ?');
    $st->execute([$editId]);
    $edit = $st->fetch() ?: null;
}

layout_header('Alunos', 'members');
?>
<section class="split-2">
  <div class="panel">
    <div class="panel-head">
      <h2>Lista</h2>
      <form method="get" style="display:flex;gap:.5rem">
        <input type="search" name="q" placeholder="Buscar..." value="<?= e($q) ?>">
        <button class="btn btn-sm" type="submit">Filtrar</button>
      </form>
    </div>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Nome</th><th>Unidade</th><th>Plano</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td>
              <strong><?= e($r['name']) ?></strong><br>
              <span class="muted"><?= e($r['phone'] ?: $r['email']) ?></span>
            </td>
            <td><?= e($r['unit_name']) ?></td>
            <td><?= e($r['plan_name'] ?: '—') ?></td>
            <td><span class="badge"><?= e($r['status']) ?></span></td>
            <td><a class="btn btn-sm" href="?id=<?= (int)$r['id'] ?>">Editar</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="muted">Nenhum aluno.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if (can('members.write')): ?>
  <div class="panel">
    <h2><?= $edit ? 'Editar aluno' : 'Novo aluno' ?></h2>
    <form method="post" action="<?= e(admin_url('api/members.php')) ?>" class="stack-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
      <label>Nome<input name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
      <label>E-mail<input type="email" name="email" value="<?= e($edit['email'] ?? '') ?>"></label>
      <label>Telefone<input name="phone" value="<?= e($edit['phone'] ?? '') ?>"></label>
      <label>Documento<input name="document" value="<?= e($edit['document'] ?? '') ?>"></label>
      <label>Nascimento<input type="date" name="birth_date" value="<?= e($edit['birth_date'] ?? '') ?>"></label>
      <label>Unidade
        <select name="unit_id" required>
          <?php foreach ($units as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= (int)($edit['unit_id'] ?? current_unit_id() ?? 0) === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Status
        <select name="status">
          <?php foreach (['active','inactive','overdue'] as $st): ?>
            <option value="<?= $st ?>" <?= ($edit['status'] ?? 'active') === $st ? 'selected' : '' ?>><?= $st ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Observações<textarea name="notes" rows="3"><?= e($edit['notes'] ?? '') ?></textarea></label>
      <?php if (!$edit): ?>
      <label>Plano inicial (opcional)
        <select name="plan_id">
          <option value="">— Sem plano —</option>
          <?php foreach ($plans as $p): ?>
            <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?> (<?= e(money_br((int)$p['price_cents'])) ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>
      <?php endif; ?>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit">Salvar</button>
        <?php if ($edit): ?><a class="btn" href="<?= e(admin_url('pages/members.php')) ?>">Cancelar</a><?php endif; ?>
      </div>
    </form>
  </div>
  <?php endif; ?>
</section>
<?php layout_footer(); ?>
