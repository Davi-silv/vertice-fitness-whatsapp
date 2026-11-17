<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
require_permission('units.write');

$pdo = db();
$units = $pdo->query('SELECT * FROM units ORDER BY name')->fetchAll();
$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$edit = null;
if ($editId) {
    $st = $pdo->prepare('SELECT * FROM units WHERE id = ?');
    $st->execute([$editId]);
    $edit = $st->fetch() ?: null;
}

layout_header('Unidades', 'units');
?>
<section class="split-2">
  <div class="panel">
    <h2>Unidades</h2>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Nome</th><th>Slug</th><th>Telefone</th><th>Ativa</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($units as $u): ?>
          <tr>
            <td><?= e($u['name']) ?><br><span class="muted"><?= e($u['address']) ?></span></td>
            <td><?= e($u['slug']) ?></td>
            <td><?= e($u['phone']) ?></td>
            <td><?= (int)$u['active'] ? 'Sim' : 'Não' ?></td>
            <td><a class="btn btn-sm" href="?id=<?= (int)$u['id'] ?>">Editar</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="panel">
    <h2><?= $edit ? 'Editar unidade' : 'Nova unidade' ?></h2>
    <form method="post" action="<?= e(admin_url('api/units.php')) ?>" class="stack-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
      <label>Nome<input name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
      <label>Slug<input name="slug" required value="<?= e($edit['slug'] ?? '') ?>"></label>
      <label>Endereço<input name="address" value="<?= e($edit['address'] ?? '') ?>"></label>
      <label>Telefone<input name="phone" value="<?= e($edit['phone'] ?? '') ?>"></label>
      <label>Ativa
        <select name="active">
          <option value="1" <?= (int)($edit['active'] ?? 1) === 1 ? 'selected' : '' ?>>Sim</option>
          <option value="0" <?= isset($edit) && !(int)$edit['active'] ? 'selected' : '' ?>>Não</option>
        </select>
      </label>
      <button class="btn btn-primary" type="submit">Salvar</button>
    </form>
  </div>
</section>
<?php layout_footer(); ?>
