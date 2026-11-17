<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_permission('dashboard.read');

$pdo = db();
$scopeSub = unit_scope_sql('s.unit_id', ':uid');
$scopeInv = unit_scope_sql('unit_id', ':uid');
$scopePay = unit_scope_sql('i.unit_id', ':uid');
$params = $scopeSub['params'];

function dash_sum(PDO $pdo, string $sql, array $params): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

$mrr = dash_sum(
    $pdo,
    "SELECT COALESCE(SUM(p.price_cents), 0)
     FROM subscriptions s
     INNER JOIN plans p ON p.id = s.plan_id
     WHERE s.status = 'active' {$scopeSub['sql']}",
    $scopeSub['params']
);

$overdue = dash_sum(
    $pdo,
    "SELECT COALESCE(SUM(amount_cents), 0)
     FROM invoices
     WHERE status = 'open' AND due_date < date('now') {$scopeInv['sql']}",
    $scopeInv['params']
);

$revenueMonth = dash_sum(
    $pdo,
    "SELECT COALESCE(SUM(pay.amount_cents), 0)
     FROM payments pay
     INNER JOIN invoices i ON i.id = pay.invoice_id
     WHERE strftime('%Y-%m', pay.paid_at) = strftime('%Y-%m', 'now') {$scopePay['sql']}",
    $scopePay['params']
);

$expenseMonth = dash_sum(
    $pdo,
    "SELECT COALESCE(SUM(amount_cents), 0)
     FROM expenses
     WHERE strftime('%Y-%m', expense_date) = strftime('%Y-%m', 'now') {$scopeInv['sql']}",
    $scopeInv['params']
);

$checkinsToday = dash_sum(
    $pdo,
    "SELECT COUNT(*) FROM checkins
     WHERE date(checked_in_at) = date('now') {$scopeInv['sql']}",
    $scopeInv['params']
);

$lowStockSql = "SELECT COUNT(*) FROM products WHERE active = 1 AND stock_qty <= min_qty {$scopeInv['sql']}";
$lowStock = dash_sum($pdo, $lowStockSql, $scopeInv['params']);

$membersActive = dash_sum(
    $pdo,
    "SELECT COUNT(*) FROM members WHERE status = 'active' {$scopeInv['sql']}",
    $scopeInv['params']
);

// Last 6 months revenue
$labels = [];
$revData = [];
$expData = [];
for ($i = 5; $i >= 0; $i--) {
    $ym = (new DateTimeImmutable("first day of -{$i} months"))->format('Y-m');
    $labels[] = $ym;
    $revData[] = dash_sum(
        $pdo,
        "SELECT COALESCE(SUM(pay.amount_cents), 0)
         FROM payments pay
         INNER JOIN invoices i ON i.id = pay.invoice_id
         WHERE strftime('%Y-%m', pay.paid_at) = :ym {$scopePay['sql']}",
        array_merge($scopePay['params'], [':ym' => $ym])
    ) / 100;
    $expData[] = dash_sum(
        $pdo,
        "SELECT COALESCE(SUM(amount_cents), 0)
         FROM expenses
         WHERE strftime('%Y-%m', expense_date) = :ym {$scopeInv['sql']}",
        array_merge($scopeInv['params'], [':ym' => $ym])
    ) / 100;
}

$openScope = unit_scope_sql('i.unit_id', ':uid');
$openInvoices = $pdo->prepare(
    "SELECT i.*, m.name AS member_name
     FROM invoices i
     INNER JOIN members m ON m.id = i.member_id
     WHERE i.status = 'open' {$openScope['sql']}
     ORDER BY i.due_date ASC LIMIT 8"
);
$openInvoices->execute($openScope['params']);
$openRows = $openInvoices->fetchAll();

$chartCash = json_encode([
    'type' => 'line',
    'data' => [
        'labels' => $labels,
        'datasets' => [
            [
                'label' => 'Receitas',
                'data' => $revData,
                'borderColor' => '#e0c56a',
                'backgroundColor' => 'rgba(224,197,106,0.15)',
                'tension' => 0.35,
                'fill' => true,
            ],
            [
                'label' => 'Despesas',
                'data' => $expData,
                'borderColor' => '#c45c5c',
                'backgroundColor' => 'rgba(196,92,92,0.12)',
                'tension' => 0.35,
                'fill' => true,
            ],
        ],
    ],
], JSON_UNESCAPED_UNICODE);

layout_header('Dashboard', 'dashboard');
?>
<section class="grid-kpi">
  <article class="kpi"><p class="kpi__label">MRR</p><p class="kpi__value"><?= e(money_br($mrr)) ?></p></article>
  <article class="kpi"><p class="kpi__label">Inadimplência</p><p class="kpi__value"><?= e(money_br($overdue)) ?></p></article>
  <article class="kpi"><p class="kpi__label">Receita do mês</p><p class="kpi__value"><?= e(money_br($revenueMonth)) ?></p></article>
  <article class="kpi"><p class="kpi__label">Despesas do mês</p><p class="kpi__value"><?= e(money_br($expenseMonth)) ?></p></article>
  <article class="kpi"><p class="kpi__label">Check-ins hoje</p><p class="kpi__value"><?= (int) $checkinsToday ?></p></article>
  <article class="kpi"><p class="kpi__label">Estoque baixo</p><p class="kpi__value"><?= (int) $lowStock ?></p></article>
  <article class="kpi"><p class="kpi__label">Alunos ativos</p><p class="kpi__value"><?= (int) $membersActive ?></p></article>
</section>

<section class="charts">
  <div class="panel">
    <h2>Fluxo (6 meses)</h2>
    <div class="chart-box">
      <canvas data-chart='<?= e($chartCash) ?>'></canvas>
    </div>
  </div>
</section>

<section class="panel" style="margin-top:1rem">
  <div class="panel-head">
    <h2>Faturas em aberto</h2>
    <?php if (can('finance.read')): ?>
      <a class="btn btn-sm" href="<?= e(admin_url('pages/finance.php')) ?>">Ver financeiro</a>
    <?php endif; ?>
  </div>
  <div class="table-wrap">
    <table class="data">
      <thead>
        <tr><th>Aluno</th><th>Descrição</th><th>Vencimento</th><th>Valor</th><th>Status</th></tr>
      </thead>
      <tbody>
      <?php if (!$openRows): ?>
        <tr><td colspan="5" class="muted">Nenhuma fatura em aberto.</td></tr>
      <?php endif; ?>
      <?php foreach ($openRows as $row): ?>
        <?php $late = $row['due_date'] < date('Y-m-d'); ?>
        <tr>
          <td><?= e($row['member_name']) ?></td>
          <td><?= e($row['description']) ?></td>
          <td><?= e($row['due_date']) ?></td>
          <td><?= e(money_br((int) $row['amount_cents'])) ?></td>
          <td><span class="badge <?= $late ? 'badge--danger' : 'badge--warn' ?>"><?= $late ? 'Atrasada' : 'Aberta' ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php layout_footer(); ?>
