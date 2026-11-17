<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
require_permission('checkins.write');

$pdo = db();
$scope = unit_scope_sql('c.unit_id', ':uid');
$recent = $pdo->prepare(
    "SELECT c.*, m.name AS member_name
     FROM checkins c
     INNER JOIN members m ON m.id = c.member_id
     WHERE 1=1 {$scope['sql']}
     ORDER BY c.checked_in_at DESC LIMIT 20"
);
$recent->execute($scope['params']);
$rows = $recent->fetchAll();

$result = $_SESSION['_checkin_result'] ?? null;
unset($_SESSION['_checkin_result']);

layout_header('Check-in', 'checkin');
?>
<div class="section-hero">
  <div class="section-hero__copy">
    <h2>Recepção premium</h2>
    <p>Localize o aluno em segundos e libere a entrada com validação de inadimplência.</p>
  </div>
</div>

<section class="split-2">
  <div class="form-card">
    <p class="form-card__eyebrow">Acesso</p>
    <h3 class="form-card__title">Busca rápida</h3>
    <p class="form-card__lead">Digite nome, telefone, documento ou ID da ficha.</p>
    <form method="post" action="<?= e(admin_url('api/checkin.php')) ?>" class="checkin-search stack-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="search">
      <label>Aluno
        <input name="q" required autofocus placeholder="Ex.: Ashiley ou 2199…">
      </label>
      <button class="btn btn-primary" type="submit">Buscar aluno</button>
    </form>

    <?php if ($result): ?>
      <?php foreach ($result['matches'] as $m): ?>
        <div class="checkin-result">
          <div class="person" style="margin-bottom:.65rem">
            <span class="avatar"><?= e(initials($m['name'])) ?></span>
            <div>
              <h3 style="margin:0"><?= e($m['name']) ?></h3>
              <p class="muted" style="margin:.2rem 0 0">#<?= (int)$m['id'] ?> · <?= e($m['phone'] ?: 'sem telefone') ?></p>
            </div>
          </div>
          <p><span class="badge <?= e(status_badge_class($m['status'])) ?>"><?= e(status_label($m['status'])) ?></span></p>
          <p>Plano: <strong><?= e($m['plan_name'] ?: 'Sem plano ativo') ?></strong></p>
          <?php if (!empty($m['overdue_cents'])): ?>
            <p class="danger-text">Inadimplente: <?= e(money_br((int)$m['overdue_cents'])) ?></p>
          <?php endif; ?>
          <?php if ($m['blocked']): ?>
            <p class="danger-text">Check-in bloqueado por inadimplência.</p>
          <?php else: ?>
            <form method="post" action="<?= e(admin_url('api/checkin.php')) ?>" style="margin-top:.75rem">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="checkin">
              <input type="hidden" name="member_id" value="<?= (int)$m['id'] ?>">
              <button class="btn btn-primary" type="submit">Liberar entrada</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if (!$result['matches']): ?>
        <div class="empty-state" style="margin-top:1rem"><strong>Sem resultados</strong>Nenhum aluno encontrado para essa busca.</div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h2>Últimos check-ins</h2>
    <p class="panel-sub">Movimento recente da unidade filtrada.</p>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Quando</th><th>Aluno</th><th>Método</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= e($r['checked_in_at']) ?></td>
            <td><?= e($r['member_name']) ?></td>
            <td><?= e($r['method']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
<?php layout_footer(); ?>
