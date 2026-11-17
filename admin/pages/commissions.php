<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
require_permission('commissions.read');

$pdo = db();
$period = $_GET['period'] ?? date('Y-m');
$scope = unit_scope_sql('c.unit_id', ':uid');
$params = array_merge($scope['params'], [':period' => $period]);
$stmt = $pdo->prepare(
    "SELECT c.*, u.name AS user_name, t.name AS teacher_name
     FROM commissions c
     LEFT JOIN users u ON u.id = c.user_id
     LEFT JOIN teachers t ON t.id = c.teacher_id
     WHERE c.period = :period {$scope['sql']}
     ORDER BY c.id DESC"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();
$rules = $pdo->query('SELECT * FROM commission_rules ORDER BY id')->fetchAll();

layout_header('Comissões', 'commissions');
?>
<section class="panel">
  <div class="panel-head">
    <h2>Apuração</h2>
    <form method="get" style="display:flex;gap:.5rem;align-items:end">
      <label>Período
        <input type="month" name="period" value="<?= e($period) ?>">
      </label>
      <button class="btn btn-sm" type="submit">Filtrar</button>
    </form>
  </div>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Beneficiário</th><th>Base</th><th>%</th><th>Valor</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= e($r['user_name'] ?: $r['teacher_name'] ?: '—') ?></td>
          <td><?= e(money_br((int)$r['base_cents'])) ?></td>
          <td><?= e((string)$r['percent']) ?>%</td>
          <td><?= e(money_br((int)$r['amount_cents'])) ?></td>
          <td><span class="badge <?= $r['status'] === 'paid' ? 'badge--ok' : 'badge--warn' ?>"><?= e($r['status']) ?></span></td>
          <td>
            <?php if (can('commissions.write') && $r['status'] === 'pending'): ?>
            <form method="post" action="<?= e(admin_url('api/commissions.php')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="pay">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input type="hidden" name="period" value="<?= e($period) ?>">
              <button class="btn btn-sm btn-primary" type="submit">Marcar pago</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6" class="muted">Sem comissões neste período.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="panel">
  <h2>Regras</h2>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Nome</th><th>%</th><th>Aplica-se a</th><th>Ativa</th></tr></thead>
      <tbody>
      <?php foreach ($rules as $rule): ?>
        <tr>
          <td><?= e($rule['name']) ?></td>
          <td><?= e((string)$rule['percent']) ?>%</td>
          <td><?= e($rule['applies_to']) ?></td>
          <td><?= (int)$rule['active'] ? 'Sim' : 'Não' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if (can('commissions.write')): ?>
  <form method="post" action="<?= e(admin_url('api/commissions.php')) ?>" class="form-grid" style="margin-top:1rem">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="rule">
    <label>Nome<input name="name" required></label>
    <label>%<input type="number" step="0.1" name="percent" required></label>
    <label>Aplica-se a
      <select name="applies_to">
        <option value="plan_sale">Venda de plano</option>
        <option value="product_sale">Venda de produto</option>
      </select>
    </label>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Adicionar regra</button></div>
  </form>
  <?php endif; ?>
</section>
<?php layout_footer(); ?>
