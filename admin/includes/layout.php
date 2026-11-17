<?php
declare(strict_types=1);

function nav_items(): array
{
    return [
        ['href' => 'index.php', 'label' => 'Dashboard', 'perm' => 'dashboard.read'],
        ['href' => 'pages/members.php', 'label' => 'Alunos', 'perm' => 'members.read'],
        ['href' => 'pages/plans.php', 'label' => 'Planos', 'perm' => 'plans.read'],
        ['href' => 'pages/finance.php', 'label' => 'Financeiro', 'perm' => 'finance.read'],
        ['href' => 'pages/checkin.php', 'label' => 'Check-in', 'perm' => 'checkins.write'],
        ['href' => 'pages/schedule.php', 'label' => 'Agenda', 'perm' => 'schedule.read'],
        ['href' => 'pages/teachers.php', 'label' => 'Professores', 'perm' => 'teachers.read'],
        ['href' => 'pages/stock.php', 'label' => 'Estoque', 'perm' => 'stock.read'],
        ['href' => 'pages/commissions.php', 'label' => 'Comissões', 'perm' => 'commissions.read'],
        ['href' => 'pages/reports.php', 'label' => 'Relatórios', 'perm' => 'reports.read'],
        ['href' => 'pages/units.php', 'label' => 'Unidades', 'perm' => 'units.write'],
        ['href' => 'pages/users.php', 'label' => 'Usuários', 'perm' => 'users.write'],
    ];
}

function layout_header(string $title, string $active = ''): void
{
    $user = current_user();
    $units = db()->query('SELECT id, name FROM units WHERE active = 1 ORDER BY name')->fetchAll();
    $unitFilter = $_SESSION['unit_filter'] ?? '';
    $flashes = consume_flashes();
    $assetBase = admin_url('assets');
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · Vértice Gestão</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Sora:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e($assetBase) ?>/admin.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
  <script src="<?= e($assetBase) ?>/admin.js" defer></script>
</head>
<body class="admin-body">
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <a class="admin-brand" href="<?= e(admin_url('index.php')) ?>">
        <img class="admin-brand__logo" src="<?= e(admin_url('../assets/logo-icon.png')) ?>" alt="" width="40" height="40" />
        <span class="admin-brand__text">
          <strong>Vértice</strong>
          <small>Gestão</small>
        </span>
      </a>
      <nav class="admin-nav">
        <?php foreach (nav_items() as $item): ?>
          <?php if (!can($item['perm'])) continue; ?>
          <?php
            $key = basename($item['href'], '.php');
            $isActive = $active === $key || ($active === 'dashboard' && $key === 'index');
          ?>
          <a class="admin-nav__link<?= $isActive ? ' is-active' : '' ?>" href="<?= e(admin_url($item['href'])) ?>">
            <?= e($item['label']) ?>
          </a>
        <?php endforeach; ?>
      </nav>
      <div class="admin-sidebar__foot">
        <div class="admin-user">
          <strong><?= e($user['name'] ?? '') ?></strong>
          <span><?= e($user['role_name'] ?? '') ?></span>
        </div>
        <a class="admin-logout" href="<?= e(admin_url('logout.php')) ?>">Sair</a>
      </div>
    </aside>
    <div class="admin-main">
      <header class="admin-topbar">
        <div>
          <p class="admin-eyebrow">Painel</p>
          <h1><?= e($title) ?></h1>
        </div>
        <form class="unit-filter" method="post" action="<?= e(admin_url('api/unit_filter.php')) ?>">
          <?= csrf_field() ?>
          <label for="unit_filter">Unidade</label>
          <select name="unit_id" id="unit_filter" onchange="this.form.submit()">
            <?php if (can('units.write') || ($user['unit_id'] ?? null) === null): ?>
              <option value="">Todas</option>
            <?php endif; ?>
            <?php foreach ($units as $u): ?>
              <?php if (!can('units.write') && (int)($user['unit_id'] ?? 0) !== (int)$u['id']) continue; ?>
              <option value="<?= (int)$u['id'] ?>" <?= (string)$unitFilter === (string)$u['id'] ? 'selected' : '' ?>>
                <?= e($u['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </form>
      </header>
      <?php if ($flashes): ?>
        <div class="flash-stack">
          <?php foreach ($flashes as $f): ?>
            <div class="flash flash--<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <main class="admin-content">
<?php
}

function layout_footer(): void
{
    ?>
      </main>
    </div>
  </div>
</body>
</html>
<?php
}

/** Aliases for pages still using the earlier API names */
function render_header(string $title, string $active = ''): void
{
    layout_header($title, $active);
}

function render_footer(): void
{
    layout_footer();
}
