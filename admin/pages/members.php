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
$plans = $pdo->query('SELECT id, name, price_cents, description FROM plans WHERE active = 1 ORDER BY price_cents')->fetchAll();
$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$edit = null;
if ($editId) {
    $st = $pdo->prepare('SELECT * FROM members WHERE id = ?');
    $st->execute([$editId]);
    $edit = $st->fetch() ?: null;
}
$showForm = can('members.write') && (isset($_GET['new']) || $edit || !$rows);

layout_header('Alunos', 'members');
?>
<div class="section-hero">
  <div class="section-hero__copy">
    <h2>Cadastro e carteira de alunos</h2>
    <p>Gerencie fichas, status e plano inicial com a mesma experiência VIP da marca Vértice.</p>
  </div>
  <div class="section-hero__actions">
    <form method="get" class="search-bar">
      <input type="search" name="q" placeholder="Buscar nome, e-mail, telefone…" value="<?= e($q) ?>">
      <button class="btn btn-sm btn-ghost" type="submit">Buscar</button>
    </form>
    <?php if (can('members.write')): ?>
      <a class="btn btn-primary" href="<?= e(admin_url('pages/members.php?new=1')) ?>">Novo cadastro</a>
    <?php endif; ?>
  </div>
</div>

<section class="split-2">
  <div class="panel">
    <div class="panel-head">
      <h2>Alunos</h2>
      <span class="badge"><?= count($rows) ?> registro(s)</span>
    </div>
    <?php if (!$rows): ?>
      <div class="empty-state">
        <strong>Nenhum aluno encontrado</strong>
        Use o formulário ao lado para criar o primeiro cadastro.
      </div>
    <?php else: ?>
      <div class="member-list">
        <?php foreach ($rows as $r): ?>
          <article class="member-row">
            <div>
              <div class="person">
                <span class="avatar" aria-hidden="true"><?= e(initials($r['name'])) ?></span>
                <div>
                  <strong><?= e($r['name']) ?></strong>
                  <span class="muted"><?= e($r['phone'] ?: $r['email'] ?: 'Sem contato') ?></span>
                </div>
              </div>
              <div class="member-row__meta">
                <span><?= e($r['unit_name']) ?></span>
                <span><?= e($r['plan_name'] ?: 'Sem plano') ?></span>
                <span class="badge <?= e(status_badge_class($r['status'])) ?>"><?= e(status_label($r['status'])) ?></span>
              </div>
            </div>
            <div class="member-row__actions">
              <?php if (can('members.write')): ?>
                <a class="btn btn-sm btn-ghost" href="<?= e(admin_url('pages/members.php?id=' . (int) $r['id'])) ?>">Editar ficha</a>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if (can('members.write') && $showForm): ?>
  <aside class="form-card">
    <p class="form-card__eyebrow"><?= $edit ? 'Atualização' : 'Novo aluno' ?></p>
    <h3 class="form-card__title"><?= $edit ? 'Editar cadastro' : 'Cadastro VIP' ?></h3>
    <p class="form-card__lead">
      <?= $edit
        ? 'Revise os dados da ficha e mantenha status e unidade alinhados ao atendimento.'
        : 'Preencha a ficha completa. Você pode já vincular um plano e gerar a primeira mensalidade.' ?>
    </p>

    <form method="post" action="<?= e(admin_url('api/members.php')) ?>" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
      <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>

      <label class="full">Nome completo
        <input name="name" required autocomplete="name" placeholder="Ex.: Ashiley Costa" value="<?= e($edit['name'] ?? '') ?>">
      </label>
      <label>E-mail
        <input type="email" name="email" autocomplete="email" placeholder="aluno@email.com" value="<?= e($edit['email'] ?? '') ?>">
      </label>
      <label>WhatsApp
        <input name="phone" inputmode="tel" placeholder="21 99999-9999" value="<?= e($edit['phone'] ?? '') ?>">
      </label>
      <label>Documento
        <input name="document" placeholder="CPF" value="<?= e($edit['document'] ?? '') ?>">
      </label>
      <label>Nascimento
        <input type="date" name="birth_date" value="<?= e($edit['birth_date'] ?? '') ?>">
      </label>
      <label>Unidade
        <select name="unit_id" required>
          <?php foreach ($units as $u): ?>
            <option value="<?= (int) $u['id'] ?>" <?= (int) ($edit['unit_id'] ?? current_unit_id() ?? 0) === (int) $u['id'] ? 'selected' : '' ?>>
              <?= e($u['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Status
        <select name="status">
          <?php foreach (['active' => 'Ativo', 'late' => 'Em atraso', 'inactive' => 'Inativo'] as $st => $label): ?>
            <option value="<?= e($st) ?>" <?= ($edit['status'] ?? 'active') === $st ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="full">Observações
        <textarea name="notes" rows="3" placeholder="Preferências, restrições, observações da recepção…"><?= e($edit['notes'] ?? '') ?></textarea>
      </label>

      <?php if (!$edit): ?>
        <div class="full">
          <p class="form-card__eyebrow" style="margin-bottom:.55rem">Plano inicial</p>
          <div class="plan-chips">
            <label class="plan-chip">
              <input type="radio" name="plan_id" value="" checked>
              <span>
                <strong>Sem plano agora</strong>
                <span>Só cadastra a ficha</span>
              </span>
              <em>—</em>
            </label>
            <?php foreach ($plans as $p): ?>
              <label class="plan-chip">
                <input type="radio" name="plan_id" value="<?= (int) $p['id'] ?>">
                <span>
                  <strong><?= e($p['name']) ?></strong>
                  <span><?= e($p['description'] ?: 'Plano Vértice') ?></span>
                </span>
                <em><?= e(money_br((int) $p['price_cents'])) ?></em>
              </label>
            <?php endforeach; ?>
          </div>
          <p class="field-hint" style="margin-top:.65rem">Ao escolher um plano, a assinatura e a fatura inicial são geradas automaticamente.</p>
        </div>
      <?php endif; ?>

      <div class="form-actions full">
        <button class="btn btn-primary" type="submit"><?= $edit ? 'Salvar alterações' : 'Concluir cadastro' ?></button>
        <?php if ($edit || isset($_GET['new'])): ?>
          <a class="btn btn-ghost" href="<?= e(admin_url('pages/members.php')) ?>">Cancelar</a>
        <?php endif; ?>
      </div>
    </form>
  </aside>
  <?php elseif (can('members.write')): ?>
  <aside class="form-card">
    <p class="form-card__eyebrow">Atalho</p>
    <h3 class="form-card__title">Novo cadastro</h3>
    <p class="form-card__lead">Abra o formulário completo para registrar um aluno e, se quiser, já vincular o plano.</p>
    <a class="btn btn-primary" href="<?= e(admin_url('pages/members.php?new=1')) ?>">Abrir cadastro</a>
  </aside>
  <?php endif; ?>
</section>
<?php layout_footer(); ?>
