<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_permission('stock.write');

if (!is_post()) {
    redirect(admin_url('pages/stock.php'));
}
verify_csrf();

$pdo = db();
$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $qty = (int) ($_POST['stock_qty'] ?? 0);
    $pdo->prepare(
        'INSERT INTO products (unit_id, name, sku, price_cents, stock_qty, min_qty, active)
         VALUES (?, ?, ?, ?, ?, ?, 1)'
    )->execute([
        (int) $_POST['unit_id'],
        trim((string) $_POST['name']),
        trim((string) ($_POST['sku'] ?? '')) ?: null,
        parse_money_to_cents((string) ($_POST['price'] ?? '0')),
        $qty,
        max(0, (int) ($_POST['min_qty'] ?? 5)),
    ]);
    $pid = (int) $pdo->lastInsertId();
    if ($qty !== 0) {
        $pdo->prepare(
            'INSERT INTO stock_moves (product_id, qty, type, notes, created_by) VALUES (?, ?, ?, ?, ?)'
        )->execute([$pid, $qty, 'in', 'Estoque inicial', current_user()['id'] ?? null]);
    }
    audit('product.create', 'products', $pid);
    flash('ok', 'Produto cadastrado.');
}

if ($action === 'move') {
    $productId = (int) $_POST['product_id'];
    $qty = (int) $_POST['qty'];
    if ($qty === 0) {
        flash('error', 'Quantidade inválida.');
        redirect(admin_url('pages/stock.php'));
    }
    $pdo->prepare('UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?')->execute([$qty, $productId]);
    $pdo->prepare(
        'INSERT INTO stock_moves (product_id, qty, type, notes, created_by) VALUES (?, ?, ?, ?, ?)'
    )->execute([
        $productId,
        $qty,
        $qty > 0 ? 'in' : 'out',
        trim((string) ($_POST['notes'] ?? '')) ?: null,
        current_user()['id'] ?? null,
    ]);
    audit('stock.move', 'products', $productId, ['qty' => $qty]);
    flash('ok', 'Movimentação registrada.');
}

redirect(admin_url('pages/stock.php'));
