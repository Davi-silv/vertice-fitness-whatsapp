<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
require_permission('reports.read');

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$type = $_GET['type'] ?? 'cashflow';

layout_header('Relatórios', 'reports');
?>
<section class="panel">
  <h2>Exportar</h2>
  <form method="get" action="<?= e(admin_url('api/reports.php')) ?>" class="form-grid">
    <label>Tipo
      <select name="type">
        <option value="cashflow" <?= $type === 'cashflow' ? 'selected' : '' ?>>Fluxo de caixa</option>
        <option value="members" <?= $type === 'members' ? 'selected' : '' ?>>Alunos</option>
        <option value="invoices" <?= $type === 'invoices' ? 'selected' : '' ?>>Faturas</option>
        <option value="checkins" <?= $type === 'checkins' ? 'selected' : '' ?>>Check-ins</option>
      </select>
    </label>
    <label>De<input type="date" name="from" value="<?= e($from) ?>"></label>
    <label>Até<input type="date" name="to" value="<?= e($to) ?>"></label>
    <label>Formato
      <select name="format">
        <option value="csv">CSV</option>
        <option value="print">PDF (imprimir)</option>
      </select>
    </label>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit">Gerar</button>
    </div>
  </form>
  <p class="muted" style="margin-top:1rem">CSV abre no Excel. Para PDF, use o modo impressão do navegador.</p>
</section>
<?php layout_footer(); ?>
