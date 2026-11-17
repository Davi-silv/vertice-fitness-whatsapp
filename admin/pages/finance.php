<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
require_permission('finance.read');

$pdo = db();
$scope = unit_scope_sql('i.unit_id', ':uid');
$status = $_GET['status'] ?? 'open';
$params = $scope['params'];
$sql = "SELECT i.*, m.name AS member_name
        FROM invoices i
        INNER JOIN members m ON m.id = i.member_id
        WHERE 1=1 {$scope['sql']}";
if (in_array($status, ['open', 'paid', 'cancelled'], true)) {
    $sql .= ' AND i.status = :st';
    $params[':st'] = $status;
}
$sql .= ' ORDER BY i.due_date DESC LIMIT 150';
$inv = $pdo->prepare($sql);
$inv->execute($params);
$invoices = $inv->fetchAll();

$expScope = unit_scope_sql('e.unit_id', ':uid');
$exp = $pdo->prepare(
    "SELECT e.*, c.name AS category_name, u.name AS unit_name
     FROM expenses e
     LEFT JOIN expense_categories c ON c.id = e.category_id
     INNER JOIN units u ON u.id = e.unit_id
     WHERE 1=1 {$expScope['sql']}
     ORDER BY e.expense_date DESC LIMIT 50"
);
$exp->execute($expScope['params']);
$expenses = $exp->fetchAll();

$categories = $pdo->query('SELECT id, name FROM expense_categories ORDER BY name')->fetchAll();
$units = $pdo->query('SELECT id, name FROM units WHERE active = 1 ORDER BY name')->fetchAll();

layout_header('Financeiro', 'finance');
?>
<div class="section-hero">
  <div class="section-hero__copy">
    <h2>Caixa e cobranças</h2>
    <p>Baixe mensalidades, acompanhe inadimplência e lance despesas da unidade.</p>
  </div>
</div>

<section class="panel">
  <div class="panel-head">
    <h2>Faturas</h2>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap">
      <?php foreach (['open' => 'Abertas', 'paid' => 'Pagas', 'cancelled' => 'Canceladas', 'all' => 'Todas'] as $k => $label): ?>
        <a class="btn btn-sm<?= $status === $k ? ' btn-primary' : ' btn-ghost' ?>" href="?status=<?= e($k) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
      <?php if (can('finance.write')): ?>
      <form method="post" action="<?= e(admin_url('api/finance.php')) ?>" style="display:inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="generate_dues">
        <button class="btn btn-sm btn-primary" type="submit" data-confirm="Gerar mensalidades do ciclo atual?">Gerar mensalidades</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Aluno</th><th>Descrição</th><th>Venc.</th><th>Valor</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($invoices as $i): ?>
        <tr>
          <td><?= e($i['member_name']) ?></td>
          <td><?= e($i['description']) ?></td>
          <td><?= e($i['due_date']) ?></td>
          <td><?= e(money_br((int)$i['amount_cents'])) ?></td>
          <?php
            $invStatus = $i['status'];
            if ($invStatus === 'open' && $i['due_date'] < date('Y-m-d')) {
                $invStatus = 'overdue';
            }
          ?>
          <td><span class="badge <?= e(status_badge_class($invStatus)) ?>"><?= e(status_label($invStatus)) ?></span></td>
          <td>
            <?php if (can('finance.write') && $i['status'] === 'open'): ?>
            <form method="post" action="<?= e(admin_url('api/finance.php')) ?>" class="form-grid" style="grid-template-columns:1fr 1fr auto;min-width:280px">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="pay">
              <input type="hidden" name="invoice_id" value="<?= (int)$i['id'] ?>">
              <select name="method" required>
                <option value="pix">PIX</option>
                <option value="card">Cartão</option>
                <option value="cash">Dinheiro</option>
              </select>
              <input name="notes" placeholder="Obs.">
              <button class="btn btn-sm btn-primary" type="submit">Baixar</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$invoices): ?><tr><td colspan="6" class="muted">Sem faturas.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="split-2">
  <div class="panel">
    <h2>Despesas</h2>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Data</th><th>Descrição</th><th>Categoria</th><th>Valor</th></tr></thead>
        <tbody>
        <?php foreach ($expenses as $e): ?>
          <tr>
            <td><?= e($e['expense_date']) ?></td>
            <td><?= e($e['description']) ?><?php if ($e['receipt_path']): ?> · <a href="/<?= e(ltrim($e['receipt_path'], '/')) ?>" target="_blank">comprovante</a><?php endif; ?></td>
            <td><?= e($e['category_name'] ?: '—') ?></td>
            <td><?= e(money_br((int)$e['amount_cents'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if (can('finance.write')): ?>
  <div class="panel">
    <h2>Nova despesa</h2>
    <form method="post" action="<?= e(admin_url('api/finance.php')) ?>" enctype="multipart/form-data" class="stack-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="expense">
      <label>Descrição<input name="description" required></label>
      <label>Valor (R$)<input name="amount" required placeholder="0,00"></label>
      <label>Data<input type="date" name="expense_date" required value="<?= e(date('Y-m-d')) ?>"></label>
      <label>Categoria
        <select name="category_id">
          <option value="">—</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
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
      <label>Comprovante<input type="file" name="receipt" accept="image/*,application/pdf"></label>
      <button class="btn btn-primary" type="submit">Registrar</button>
    </form>
  </div>
  <?php endif; ?>
</section>
<?php layout_footer(); ?>
