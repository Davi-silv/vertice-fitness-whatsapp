<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_login();

if (!is_post()) {
    redirect(admin_url('index.php'));
}
verify_csrf();

$raw = $_POST['unit_id'] ?? '';
if ($raw === '' || $raw === null) {
    if (can('units.write') || (current_user()['unit_id'] ?? null) === null) {
        set_unit_filter(null);
    }
} else {
    $unitId = (int) $raw;
    $user = current_user();
    if (!can('units.write') && (int) ($user['unit_id'] ?? 0) !== $unitId) {
        flash('error', 'Unidade não permitida.');
        redirect($_SERVER['HTTP_REFERER'] ?? admin_url('index.php'));
    }
    set_unit_filter($unitId);
}

$back = $_SERVER['HTTP_REFERER'] ?? admin_url('index.php');
redirect($back);
