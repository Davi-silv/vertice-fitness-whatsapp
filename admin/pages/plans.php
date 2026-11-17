<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
require_permission('plans.read');

$pdo = db();
$plans = $pdo->query('SELECT * FROM plans ORDER BY price_cents')->fetchAll();

$scope = unit_scope_sql('s.unit_id', ':uid');
$subsStmt = $pdo->prepare(
    "SELECT s.*, m.name AS member_name, p.name AS plan_name
     FROM subscriptions s
     INNER JOIN members m ON m.id = s.member_id
     INNER JOIN plans p ON p.id = s.plan_id
     WHERE 1=1 {$scope['sql']}
     ORDER BY s.id DESC LIMIT 50"
);
$subsStmt->execute($scope['params']);
$subs = $subsStmt->fetchAll();

$members = $pdo->prepare(
    'SELECT id, name FROM members WHERE status != "inactive" '
    . (current_unit_id() ? ' AND unit_id = ' . (int) current_unit_id() : '')
    . ' ORDER BY name LIMIT 300'
);
$members->execute();
$memberRows = $members->fetchAll();

$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$edit = null;
if ($editId) {
    $st = $pdo->prepare('SELECT * FROM plans WHERE id = ?');
    $st->execute([$editId]);
    $edit = $st->fetch() ?: null;
}

layout_header('Planos e assinaturas', 'plans');
?>
<div class="section-hero">
  <div class="section-hero__copy">
    <h2>Catálogo e assinaturas</h2>
    <p>Black, Gold e Platinum com ciclo, preço e ativação rápida para a recepção.</p>
  </div>
</div>

<div class="price-cards">
  <?php foreach ($plans as $p): if (!(int)$p['active']) continue; ?>
    <article class="price-card">
      <h3><?= e($p['name']) ?></h3>
      <div class="amount"><?= e(money_br((int)$p['price_cents'])) ?></div>
      <p><?= e($p['description'] ?: ((int)$p['billing_days'] . ' dias de ciclo')) ?></p>
    </article>
  <?php endforeach; ?>
</div>

<section class="split-2">
  <div class="panel">
    <div class="panel-head"><h2>Planos</h2></div>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Nome</th><th>Preço</th><th>Ciclo</th><th>Ativo</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($plans as $p): ?>
          <tr>
            <td><strong><?= e($p['name']) ?></strong></td>
            <td><?= e(money_br((int)$p['price_cents'])) ?></td>
            <td><?= (int)$p['billing_days'] ?> dias</td>
            <td><span class="badge <?= (int)$p['active'] ? 'badge--ok' : 'badge--danger' ?>"><?= (int)$p['active'] ? 'Ativo' : 'Inativo' ?></span></td>
            <td><?php if (can('plans.write')): ?><a class="btn btn-sm btn-ghost" href="?id=<?= (int)$p['id'] ?>">Editar</a><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if (can('plans.write')): ?>
    <div class="form-card" style="margin-top:1.15rem">
      <p class="form-card__eyebrow"><?= $edit ? 'Edição' : 'Novo' ?></p>
      <h3 class="form-card__title"><?= $edit ? 'Editar plano' : 'Criar plano' ?></h3>
      <form method="post" action="<?= e(admin_url('api/plans.php')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
        <label>Nome<input name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
        <label>Slug<input name="slug" required value="<?= e($edit['slug'] ?? '') ?>" placeholder="gold"></label>
        <label>Preço (R$)<input name="price" required value="<?= $edit ? number_format(((int)$edit['price_cents'])/100, 2, ',', '.') : '' ?>"></label>
        <label>Ciclo (dias)<input type="number" name="billing_days" min="1" value="<?= (int)($edit['billing_days'] ?? 30) ?>"></label>
        <label class="full">Descrição<input name="description" value="<?= e($edit['description'] ?? '') ?>"></label>
        <label>Ativo
          <select name="active">
            <option value="1" <?= (int)($edit['active'] ?? 1) === 1 ? 'selected' : '' ?>>Sim</option>
            <option value="0" <?= isset($edit) && !(int)$edit['active'] ? 'selected' : '' ?>>Não</option>
          </select>
        </label>
        <div class="form-actions full"><button class="btn btn-primary" type="submit">Salvar plano</button></div>
      </form>
    </div>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h2>Assinaturas</h2>
    <p class="panel-sub">Ative ou renove planos e acompanhe a vigência.</p>
    <?php if (can('plans.write')): ?>
    <form method="post" action="<?= e(admin_url('api/subscriptions.php')) ?>" class="stack-form" style="margin-bottom:1rem">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="activate">
      <label>Aluno
        <select name="member_id" required>
          <option value="">Selecione</option>
          <?php foreach ($memberRows as $m): ?>
            <option value="<?= (int)$m['id'] ?>"><?= e($m['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Plano
        <select name="plan_id" required>
          <?php foreach ($plans as $p): if (!(int)$p['active']) continue; ?>
            <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button class="btn btn-primary" type="submit">Ativar / renovar</button>
    </form>
    <?php endif; ?>

    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Aluno</th><th>Plano</th><th>Status</th><th>Vigência</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($subs as $s): ?>
          <tr>
            <td><?= e($s['member_name']) ?></td>
            <td><?= e($s['plan_name']) ?></td>
            <td><span class="badge <?= e(status_badge_class($s['status'])) ?>"><?= e(status_label($s['status'])) ?></span></td>
            <td><?= e($s['starts_at']) ?> → <?= e($s['ends_at'] ?: '—') ?></td>
            <td>
              <?php if (can('plans.write') && $s['status'] === 'active'): ?>
              <form method="post" action="<?= e(admin_url('api/subscriptions.php')) ?>" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <button class="btn btn-sm btn-danger" data-confirm="Cancelar assinatura?" type="submit">Cancelar</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
<?php layout_footer(); ?>
