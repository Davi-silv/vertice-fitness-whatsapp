<?php
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__, 2));
define('ADMIN_PATH', dirname(__DIR__));
define('DATA_PATH', ROOT_PATH . '/data');
define('DB_PATH', DATA_PATH . '/vertice.db');
define('UPLOAD_PATH', DATA_PATH . '/uploads');
define('SESSION_PATH', DATA_PATH . '/sessions');

if (!is_dir(DATA_PATH)) {
    mkdir(DATA_PATH, 0755, true);
}
if (!is_dir(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}
if (!is_dir(SESSION_PATH)) {
    mkdir(SESSION_PATH, 0755, true);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_save_path(SESSION_PATH);
    session_start();
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/auth.php';

db();
