<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_login();
redirect(admin_url('pages/stock.php'));
