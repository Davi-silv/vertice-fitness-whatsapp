<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_permission('reports.read');

$pdo = db();
$type = $_GET['type'] ?? 'cashflow';
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$format = $_GET['format'] ?? 'csv';
$unitId = current_unit_id();

$rows = [];
$headers = [];

if ($type === 'members') {
    $headers = ['ID', 'Nome', 'Email', 'Telefone', 'Status', 'Unidade'];
    $sql = 'SELECT m.id, m.name, m.email, m.phone, m.status, u.name AS unit_name
            FROM members m INNER JOIN units u ON u.id = m.unit_id WHERE 1=1';
    $params = [];
    if ($unitId) {
        $sql .= ' AND m.unit_id = ?';
        $params[] = $unitId;
    }
    $sql .= ' ORDER BY m.name';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    foreach ($st->fetchAll() as $r) {
        $rows[] = [$r['id'], $r['name'], $r['email'], $r['phone'], $r['status'], $r['unit_name']];
    }
} elseif ($type === 'invoices') {
    $headers = ['ID', 'Aluno', 'Descrição', 'Valor', 'Vencimento', 'Status'];
    $sql = 'SELECT i.*, m.name AS member_name FROM invoices i
            INNER JOIN members m ON m.id = i.member_id
            WHERE i.due_date BETWEEN ? AND ?';
    $params = [$from, $to];
    if ($unitId) {
        $sql .= ' AND i.unit_id = ?';
        $params[] = $unitId;
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    foreach ($st->fetchAll() as $r) {
        $rows[] = [$r['id'], $r['member_name'], $r['description'], number_format($r['amount_cents']/100, 2, '.', ''), $r['due_date'], $r['status']];
    }
} elseif ($type === 'checkins') {
    $headers = ['Quando', 'Aluno', 'Unidade', 'Método'];
    $sql = 'SELECT c.checked_in_at, m.name AS member_name, u.name AS unit_name, c.method
            FROM checkins c
            INNER JOIN members m ON m.id = c.member_id
            INNER JOIN units u ON u.id = c.unit_id
            WHERE date(c.checked_in_at) BETWEEN ? AND ?';
    $params = [$from, $to];
    if ($unitId) {
        $sql .= ' AND c.unit_id = ?';
        $params[] = $unitId;
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    foreach ($st->fetchAll() as $r) {
        $rows[] = [$r['checked_in_at'], $r['member_name'], $r['unit_name'], $r['method']];
    }
} else {
    $headers = ['Data', 'Tipo', 'Descrição', 'Valor'];
    $sqlPay = 'SELECT pay.paid_at AS dt, "receita" AS kind, i.description, pay.amount_cents
               FROM payments pay INNER JOIN invoices i ON i.id = pay.invoice_id
               WHERE date(pay.paid_at) BETWEEN ? AND ?';
    $params = [$from, $to];
    if ($unitId) {
        $sqlPay .= ' AND i.unit_id = ?';
        $params[] = $unitId;
    }
    $st = $pdo->prepare($sqlPay);
    $st->execute($params);
    foreach ($st->fetchAll() as $r) {
        $rows[] = [$r['dt'], $r['kind'], $r['description'], number_format($r['amount_cents']/100, 2, '.', '')];
    }
    $sqlExp = 'SELECT expense_date AS dt, "despesa" AS kind, description, amount_cents
               FROM expenses WHERE expense_date BETWEEN ? AND ?';
    $params2 = [$from, $to];
    if ($unitId) {
        $sqlExp .= ' AND unit_id = ?';
        $params2[] = $unitId;
    }
    $st2 = $pdo->prepare($sqlExp);
    $st2->execute($params2);
    foreach ($st2->fetchAll() as $r) {
        $rows[] = [$r['dt'], $r['kind'], $r['description'], number_format(-$r['amount_cents']/100, 2, '.', '')];
    }
    usort($rows, static fn($a, $b) => strcmp((string)$a[0], (string)$b[0]));
}

audit('report.export', 'reports', null, ['type' => $type, 'format' => $format]);

if ($format === 'print') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Relatório</title>';
    echo '<style>body{font-family:sans-serif;padding:24px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #ccc;padding:8px;text-align:left}h1{font-size:20px}</style>';
    echo '</head><body onload="window.print()">';
    echo '<h1>Vértice · Relatório ' . htmlspecialchars($type) . ' (' . htmlspecialchars($from) . ' — ' . htmlspecialchars($to) . ')</h1>';
    echo '<table><thead><tr>';
    foreach ($headers as $h) {
        echo '<th>' . htmlspecialchars($h) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . htmlspecialchars((string) $cell) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></body></html>';
    exit;
}

$filename = 'vertice_' . $type . '_' . $from . '_' . $to . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
fputcsv($out, $headers, ';');
foreach ($rows as $row) {
    fputcsv($out, $row, ';');
}
fclose($out);
exit;
