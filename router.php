<?php
declare(strict_types=1);

/**
 * Router do PHP built-in server:
 *   php -S 0.0.0.0:8080 router.php
 *
 * Garante que "/" sirva a landing (index.php) e que arquivos
 * estáticos existentes continuem sendo entregues normalmente.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$file = __DIR__ . $uri;

if ($uri !== '/' && is_file($file)) {
    return false; // deixa o servidor servir o arquivo estático
}

if ($uri === '/' || $uri === '' || $uri === '/index.html') {
    require __DIR__ . '/index.php';
    return true;
}

if (is_file($file) && str_ends_with($file, '.php')) {
    require $file;
    return true;
}

http_response_code(404);
echo '404';
return true;
