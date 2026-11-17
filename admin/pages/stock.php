<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
require_permission('stock.read');

$pdo = db();
$scope = unit_scope_sql('p.unit_id', ':uid');
$stmt = $pdo->prepare(
    "SELECT p.*, u.name AS unit_name FROM products p
     INNER JOIN units u ON u.id = p.unit_id
     WHERE p.active = 1 {$scope['sql']}
     ORDER BY p.name"
);
$stmt->execute($scope['params']);
$products = $stmt->fetchAll();
$units = $pdo->query('SELECT id, name FROM units WHERE active = 1 ORDER BY name')->fetchAll();

layout_header('Estoque', 'stock');
?>
<section class="split-2">
  <div class="panel">
    <h2>Produtos</h2>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Produto</th><th>SKU</th><th>Estoque</th><th>Mín.</th><th>Preço</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($products as $p): ?>
          <?php $low = (int)$p['stock_qty'] <= (int)$p['min_qty']; ?>
          <tr>
            <td><?= e($p['name']) ?><br><span class="muted"><?= e($p['unit_name']) ?></span></td>
            <td><?= e($p['sku'] ?: '—') ?></td>
            <td class="<?= $low ? 'danger-text' : '' ?>"><?= (int)$p['stock_qty'] ?><?= $low ? ' ⚠' : '' ?></td>
            <td><?= (int)$p['min_qty'] ?></td>
            <td><?= e(money_br((int)$p['price_cents'])) ?></td>
            <td>
              <?php if (can('stock.write')): ?>
              <form method="post" action="<?= e(admin_url('api/stock.php')) ?>" style="display:flex;gap:.35rem">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="move">
                <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                <input type="number" name="qty" placeholder="+/-" required style="width:5rem">
                <button class="btn btn-sm" type="submit">Mov.</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if (can('stock.write')): ?>
  <div class="panel">
    <h2>Novo produto</h2>
    <form method="post" action="<?= e(admin_url('api/stock.php')) ?>" class="stack-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <label>Nome<input name="name" required></label>
      <label>SKU<input name="sku"></label>
      <label>Preço<input name="price" placeholder="0,00"></label>
      <label>Estoque inicial<input type="number" name="stock_qty" value="0"></label>
      <label>Mínimo<input type="number" name="min_qty" value="5"></label>
      <label>Unidade
        <select name="unit_id" required>
          <?php foreach ($units as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= (int)(current_unit_id() ?? 0) === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button class="btn btn-primary" type="submit">Cadastrar</button>
    </form>
  </div>
  <?php endif; ?>
</section>
<?php layout_footer(); ?>
