<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
require_login();
if (is_post()) {
    verify_csrf();
    $unitId = trim((string) ($_POST['unit_id'] ?? ''));
    set_unit_filter($unitId === '' ? null : (int) $unitId);
}
$ref = $_SERVER['HTTP_REFERER'] ?? (admin_base() . '/index.php');
redirect($ref);
