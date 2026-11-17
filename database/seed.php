<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/admin/includes/bootstrap.php';

$pdo = db();

echo "Seeding Vértice Fitness...\n";

foreach ([
    'audit_logs', 'commissions', 'commission_rules', 'stock_moves', 'products', 'checkins',
    'class_bookings', 'class_sessions', 'classes', 'teachers', 'payments', 'invoices',
    'subscriptions', 'members', 'plans', 'expenses', 'expense_categories', 'role_permissions',
    'users', 'permissions', 'roles', 'units', 'settings',
] as $table) {
    $pdo->exec("DELETE FROM {$table}");
}

$roles = [
    ['admin', 'Administrador'],
    ['financeiro', 'Financeiro'],
    ['recepcao', 'Recepção'],
    ['professor', 'Professor'],
];
$roleIds = [];
$stmt = $pdo->prepare('INSERT INTO roles (slug, name) VALUES (?, ?)');
foreach ($roles as [$slug, $name]) {
    $stmt->execute([$slug, $name]);
    $roleIds[$slug] = (int) $pdo->lastInsertId();
}

$permissions = [
    ['*', 'Acesso total'],
    ['dashboard.read', 'Ver dashboard'],
    ['members.read', 'Ver alunos'],
    ['members.write', 'Editar alunos'],
    ['plans.read', 'Ver planos'],
    ['plans.write', 'Editar planos'],
    ['finance.read', 'Ver financeiro'],
    ['finance.write', 'Editar financeiro'],
    ['checkins.write', 'Fazer check-in'],
    ['schedule.read', 'Ver agenda'],
    ['schedule.write', 'Editar agenda'],
    ['teachers.read', 'Ver professores'],
    ['teachers.write', 'Editar professores'],
    ['stock.read', 'Ver estoque'],
    ['stock.write', 'Editar estoque'],
    ['commissions.read', 'Ver comissões'],
    ['commissions.write', 'Editar comissões'],
    ['reports.read', 'Ver relatórios'],
    ['units.write', 'Gerenciar unidades'],
    ['users.write', 'Gerenciar usuários'],
];
$permIds = [];
$stmt = $pdo->prepare('INSERT INTO permissions (slug, name) VALUES (?, ?)');
foreach ($permissions as [$slug, $name]) {
    $stmt->execute([$slug, $name]);
    $permIds[$slug] = (int) $pdo->lastInsertId();
}

$map = [
    'admin' => ['*'],
    'financeiro' => [
        'dashboard.read', 'finance.read', 'finance.write', 'members.read', 'plans.read',
        'commissions.read', 'commissions.write', 'reports.read', 'stock.read',
    ],
    'recepcao' => [
        'dashboard.read', 'members.read', 'members.write', 'plans.read', 'plans.write',
        'finance.read', 'finance.write', 'checkins.write', 'schedule.read', 'schedule.write',
        'stock.read',
    ],
    'professor' => [
        'dashboard.read', 'schedule.read', 'checkins.write', 'members.read', 'teachers.read',
    ],
];
$link = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
foreach ($map as $role => $perms) {
    foreach ($perms as $p) {
        $link->execute([$roleIds[$role], $permIds[$p]]);
    }
}

$pdo->prepare('INSERT INTO units (name, slug, address, phone) VALUES (?, ?, ?, ?)')->execute([
    'Vértice Copacabana', 'copacabana',
    'Av. Atlântica, 1900 — Copacabana, Rio de Janeiro', '21999998888',
]);
$unit1 = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO units (name, slug, address, phone) VALUES (?, ?, ?, ?)')->execute([
    'Vértice Ipanema', 'ipanema',
    'Rua Visconde de Pirajá, 500 — Ipanema, Rio de Janeiro', '21988887777',
]);
$unit2 = (int) $pdo->lastInsertId();

$pass = password_hash('Vertice@2026', PASSWORD_DEFAULT);
$insUser = $pdo->prepare(
    'INSERT INTO users (unit_id, role_id, name, email, password_hash) VALUES (?, ?, ?, ?, ?)'
);
$insUser->execute([$unit1, $roleIds['admin'], 'Admin Vértice', 'admin@vertice.local', $pass]);
$adminId = (int) $pdo->lastInsertId();
$passTeste = password_hash('Teste@2026', PASSWORD_DEFAULT);
$insUser->execute([$unit1, $roleIds['admin'], 'Usuário Teste', 'teste@vertice.local', $passTeste]);
$insUser->execute([$unit1, $roleIds['financeiro'], 'Ana Financeiro', 'financeiro@vertice.local', $pass]);
$insUser->execute([$unit1, $roleIds['recepcao'], 'Bruno Recepção', 'recepcao@vertice.local', $pass]);
$insUser->execute([$unit1, $roleIds['professor'], 'Carla Professor', 'professor@vertice.local', $pass]);
$profUserId = (int) $pdo->lastInsertId();

$plans = [
    ['Black', 'black', 44900, 'Musculação, bike, aulas coletivas, app'],
    ['Gold', 'gold', 78900, 'Black + personal 4x, spa, nutri'],
    ['Platinum', 'platinum', 129000, 'Gold + personal ilimitado, concierge VIP'],
];
$planIds = [];
$insPlan = $pdo->prepare('INSERT INTO plans (name, slug, price_cents, description) VALUES (?, ?, ?, ?)');
foreach ($plans as [$n, $s, $p, $d]) {
    $insPlan->execute([$n, $s, $p, $d]);
    $planIds[$s] = (int) $pdo->lastInsertId();
}

foreach (['Aluguel', 'Folha', 'Utilities', 'Marketing', 'Manutenção', 'Suprimentos'] as $cat) {
    $pdo->prepare('INSERT INTO expense_categories (name) VALUES (?)')->execute([$cat]);
}

$pdo->prepare(
    'INSERT INTO teachers (user_id, unit_id, name, phone, specialty, commission_percent) VALUES (?, ?, ?, ?, ?, ?)'
)->execute([$profUserId, $unit1, 'Carla Professor', '21977776666', 'Functional / Bike', 10]);
$teacherId = (int) $pdo->lastInsertId();
$pdo->prepare(
    'INSERT INTO teachers (unit_id, name, phone, specialty, commission_percent) VALUES (?, ?, ?, ?, ?)'
)->execute([$unit1, 'Diego Personal', '21966665555', 'Musculação', 15]);

$members = [
    ['Ashiley Costa', 'ashiley@email.com', '21911112222', 'active'],
    ['João Mendes', 'joao@email.com', '21922223333', 'active'],
    ['Marina Souza', 'marina@email.com', '21933334444', 'late'],
    ['Pedro Lima', 'pedro@email.com', '21944445555', 'inactive'],
];
$memberIds = [];
$insM = $pdo->prepare('INSERT INTO members (unit_id, name, email, phone, status) VALUES (?, ?, ?, ?, ?)');
foreach ($members as $i => [$name, $email, $phone, $status]) {
    $insM->execute([$i % 2 === 0 ? $unit1 : $unit2, $name, $email, $phone, $status]);
    $memberIds[] = (int) $pdo->lastInsertId();
}

$insSub = $pdo->prepare(
    'INSERT INTO subscriptions (member_id, plan_id, unit_id, seller_user_id, status, starts_at, ends_at)
     VALUES (?, ?, ?, ?, ?, date("now","-10 day"), date("now","+20 day"))'
);
$insSub->execute([$memberIds[0], $planIds['gold'], $unit1, $adminId, 'active']);
$sub1 = (int) $pdo->lastInsertId();
$insSub->execute([$memberIds[1], $planIds['black'], $unit2, $adminId, 'active']);
$sub2 = (int) $pdo->lastInsertId();
$insSub->execute([$memberIds[2], $planIds['platinum'], $unit1, $adminId, 'active']);
$sub3 = (int) $pdo->lastInsertId();

$insInv = $pdo->prepare(
    'INSERT INTO invoices (member_id, subscription_id, unit_id, description, amount_cents, due_date, status)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$insInv->execute([$memberIds[0], $sub1, $unit1, 'Mensalidade Gold', 78900, date('Y-m-d', strtotime('+5 days')), 'open']);
$insInv->execute([$memberIds[1], $sub2, $unit2, 'Mensalidade Black', 44900, date('Y-m-d', strtotime('-2 days')), 'paid']);
$invPaid = (int) $pdo->lastInsertId();
$insInv->execute([$memberIds[2], $sub3, $unit1, 'Mensalidade Platinum', 129000, date('Y-m-d', strtotime('-8 days')), 'open']);

$pdo->prepare(
    'INSERT INTO payments (invoice_id, amount_cents, method, paid_at, created_by) VALUES (?, ?, ?, datetime("now","-1 day"), ?)'
)->execute([$invPaid, 44900, 'pix', $adminId]);

$pdo->prepare(
    'INSERT INTO expenses (unit_id, category_id, description, amount_cents, expense_date, created_by)
     VALUES (?, 1, "Aluguel Copacabana", 2500000, date("now","start of month"), ?)'
)->execute([$unit1, $adminId]);
$pdo->prepare(
    'INSERT INTO expenses (unit_id, category_id, description, amount_cents, expense_date, created_by)
     VALUES (?, 5, "Manutenção esteiras", 185000, date("now","-3 day"), ?)'
)->execute([$unit1, $adminId]);

$pdo->prepare(
    'INSERT INTO classes (unit_id, teacher_id, name, capacity, duration_min) VALUES (?, ?, ?, ?, ?)'
)->execute([$unit1, $teacherId, 'Functional VIP', 16, 50]);
$classId = (int) $pdo->lastInsertId();
$pdo->prepare(
    'INSERT INTO class_sessions (class_id, starts_at, ends_at) VALUES (?, datetime("now","+1 day","8 hours"), datetime("now","+1 day","9 hours"))'
)->execute([$classId]);
$sessionId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO class_bookings (session_id, member_id) VALUES (?, ?)')->execute([$sessionId, $memberIds[0]]);

$pdo->prepare('INSERT INTO checkins (member_id, unit_id, method, created_by) VALUES (?, ?, ?, ?)')
    ->execute([$memberIds[0], $unit1, 'manual', $adminId]);
$pdo->prepare('INSERT INTO checkins (member_id, unit_id, method, created_by) VALUES (?, ?, ?, ?)')
    ->execute([$memberIds[1], $unit2, 'manual', $adminId]);

$pdo->prepare(
    'INSERT INTO products (unit_id, name, sku, price_cents, stock_qty, min_qty) VALUES (?, ?, ?, ?, ?, ?)'
)->execute([$unit1, 'Toalha Premium', 'TOA-01', 4500, 80, 20]);
$pdo->prepare(
    'INSERT INTO products (unit_id, name, sku, price_cents, stock_qty, min_qty) VALUES (?, ?, ?, ?, ?, ?)'
)->execute([$unit1, 'Whey 900g', 'WHEY-01', 18900, 4, 5]);
$prodLow = (int) $pdo->lastInsertId();
$pdo->prepare(
    'INSERT INTO stock_moves (product_id, qty, type, notes, created_by) VALUES (?, ?, ?, ?, ?)'
)->execute([$prodLow, -2, 'out', 'Venda balcão', $adminId]);

$pdo->prepare('INSERT INTO commission_rules (name, percent, applies_to) VALUES (?, ?, ?)')
    ->execute(['Venda de plano', 5, 'plan_sale']);
$pdo->prepare(
    'INSERT INTO commissions (user_id, unit_id, reference_type, reference_id, base_cents, percent, amount_cents, period, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
)->execute([
    $adminId, $unit1, 'subscription', $sub1, 78900, 5, (int) round(78900 * 0.05),
    date('Y-m'), 'pending',
]);

$pdo->prepare('INSERT INTO settings (key, value) VALUES (?, ?)')->execute(['block_late_checkin', '1']);
$pdo->prepare('INSERT INTO settings (key, value) VALUES (?, ?)')->execute(['academy_name', 'Vértice Fitness']);

echo "OK\n";
echo "Login admin: admin@vertice.local / Vertice@2026\n";
echo "Login teste: teste@vertice.local / Teste@2026\n";
echo "DB: " . DB_PATH . "\n";
