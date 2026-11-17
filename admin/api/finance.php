<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_permission('finance.write');

if (!is_post()) {
    redirect(admin_url('pages/finance.php'));
}
verify_csrf();

$pdo = db();
$action = $_POST['action'] ?? '';

if ($action === 'pay') {
    $invoiceId = (int) $_POST['invoice_id'];
    $method = $_POST['method'] ?? 'pix';
    if (!in_array($method, ['pix', 'card', 'cash'], true)) {
        $method = 'pix';
    }
    $inv = $pdo->prepare('SELECT * FROM invoices WHERE id = ? AND status = ?');
    $inv->execute([$invoiceId, 'open']);
    $invoice = $inv->fetch();
    if (!$invoice) {
        flash('error', 'Fatura não encontrada.');
        redirect(admin_url('pages/finance.php'));
    }
    $pdo->prepare(
        'INSERT INTO payments (invoice_id, amount_cents, method, paid_at, notes, created_by)
         VALUES (?, ?, ?, datetime("now"), ?, ?)'
    )->execute([
        $invoiceId,
        (int) $invoice['amount_cents'],
        $method,
        trim((string) ($_POST['notes'] ?? '')) ?: null,
        current_user()['id'] ?? null,
    ]);
    $pdo->prepare("UPDATE invoices SET status = 'paid' WHERE id = ?")->execute([$invoiceId]);
    $pdo->prepare("UPDATE members SET status = 'active' WHERE id = ?")->execute([(int) $invoice['member_id']]);
    audit('invoice.pay', 'invoices', $invoiceId, ['method' => $method]);
    flash('ok', 'Pagamento registrado.');
    redirect(admin_url('pages/finance.php?status=open'));
}

if ($action === 'expense') {
    $receiptPath = null;
    if (!empty($_FILES['receipt']['tmp_name'])) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['receipt']['tmp_name']);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
        ];
        if (!isset($allowed[$mime])) {
            flash('error', 'Comprovante inválido (use JPG, PNG, WEBP ou PDF).');
            redirect(admin_url('pages/finance.php'));
        }
        $name = 'receipt_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
        $dest = UPLOAD_PATH . '/' . $name;
        if (!move_uploaded_file($_FILES['receipt']['tmp_name'], $dest)) {
            flash('error', 'Falha no upload.');
            redirect(admin_url('pages/finance.php'));
        }
        $receiptPath = 'data/uploads/' . $name;
    }

    $pdo->prepare(
        'INSERT INTO expenses (unit_id, category_id, description, amount_cents, expense_date, receipt_path, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        (int) $_POST['unit_id'],
        ($_POST['category_id'] ?? '') !== '' ? (int) $_POST['category_id'] : null,
        trim((string) $_POST['description']),
        parse_money_to_cents((string) $_POST['amount']),
        $_POST['expense_date'],
        $receiptPath,
        current_user()['id'] ?? null,
    ]);
    audit('expense.create', 'expenses', (int) $pdo->lastInsertId());
    flash('ok', 'Despesa registrada.');
    redirect(admin_url('pages/finance.php'));
}

if ($action === 'generate_dues') {
    $scope = unit_scope_sql('s.unit_id', ':uid');
    $stmt = $pdo->prepare(
        "SELECT s.*, p.name AS plan_name, p.price_cents, p.billing_days
         FROM subscriptions s
         INNER JOIN plans p ON p.id = s.plan_id
         WHERE s.status = 'active' {$scope['sql']}"
    );
    $stmt->execute($scope['params']);
    $created = 0;
    foreach ($stmt->fetchAll() as $s) {
        $check = $pdo->prepare(
            "SELECT COUNT(*) FROM invoices
             WHERE subscription_id = ? AND status IN ('open','paid')
             AND strftime('%Y-%m', due_date) = strftime('%Y-%m', 'now')"
        );
        $check->execute([(int) $s['id']]);
        if ((int) $check->fetchColumn() > 0) {
            continue;
        }
        $pdo->prepare(
            'INSERT INTO invoices (member_id, subscription_id, unit_id, description, amount_cents, due_date, status)
             VALUES (?, ?, ?, ?, ?, date("now", "+5 days"), ?)'
        )->execute([
            (int) $s['member_id'],
            (int) $s['id'],
            (int) $s['unit_id'],
            'Mensalidade ' . $s['plan_name'],
            (int) $s['price_cents'],
            'open',
        ]);
        $created++;
    }
    audit('invoices.generate', 'invoices', null, ['count' => $created]);
    flash('ok', "Mensalidades geradas: {$created}");
    redirect(admin_url('pages/finance.php?status=open'));
}

flash('error', 'Ação inválida.');
redirect(admin_url('pages/finance.php'));
