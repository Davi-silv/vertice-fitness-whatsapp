<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
require_permission('users.write');

$pdo = db();
$users = $pdo->query(
    'SELECT u.*, r.name AS role_name, un.name AS unit_name
     FROM users u
     INNER JOIN roles r ON r.id = u.role_id
     LEFT JOIN units un ON un.id = u.unit_id
     ORDER BY u.name'
)->fetchAll();
$roles = $pdo->query('SELECT id, name, slug FROM roles ORDER BY name')->fetchAll();
$units = $pdo->query('SELECT id, name FROM units WHERE active = 1 ORDER BY name')->fetchAll();

layout_header('Usuários', 'users');
?>
<section class="split-2">
  <div class="panel">
    <h2>Staff</h2>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Nome</th><th>E-mail</th><th>Cargo</th><th>Unidade</th><th>Ativo</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><?= e($u['name']) ?></td>
            <td><?= e($u['email']) ?></td>
            <td><?= e($u['role_name']) ?></td>
            <td><?= e($u['unit_name'] ?: 'Todas') ?></td>
            <td><?= (int)$u['active'] ? 'Sim' : 'Não' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="panel">
    <h2>Novo usuário</h2>
    <form method="post" action="<?= e(admin_url('api/users.php')) ?>" class="stack-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <label>Nome<input name="name" required></label>
      <label>E-mail<input type="email" name="email" required></label>
      <label>Senha<input type="password" name="password" required minlength="8"></label>
      <label>Cargo
        <select name="role_id" required>
          <?php foreach ($roles as $r): ?>
            <option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Unidade
        <select name="unit_id">
          <option value="">Todas / sem vínculo</option>
          <?php foreach ($units as $un): ?>
            <option value="<?= (int)$un['id'] ?>"><?= e($un['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button class="btn btn-primary" type="submit">Criar</button>
    </form>
  </div>
</section>
<?php layout_footer(); ?>
