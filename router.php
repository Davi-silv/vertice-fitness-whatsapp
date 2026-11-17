<?php
declare(strict_types=1);

/**
 * Router do PHP built-in server:
 *   php -S 0.0.0.0:8080 router.php
 *
 * "/" e "/index.html" sempre caem em index.php (evita POST 405 em HTML estático).
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$file = __DIR__ . $uri;

// Landing: nunca servir index.html cru (GET/POST).
if ($uri === '/' || $uri === '' || $uri === '/index.html' || $uri === '/index.php') {
    require __DIR__ . '/index.php';
    return true;
}

// Arquivos estáticos reais (css, js, imagens…).
if ($uri !== '/' && is_file($file) && !str_ends_with(strtolower($file), '.php')) {
    return false;
}

// Scripts PHP sob demanda.
if (is_file($file) && str_ends_with(strtolower($file), '.php')) {
    require $file;
    return true;
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo '404';
return true;
