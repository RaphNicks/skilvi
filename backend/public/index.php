<?php
declare(strict_types=1);

$uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$file = __DIR__ . $uri;
if ($uri !== '/' && is_file($file) && !str_ends_with($file, '.php')) {
    return false;
}

require dirname(__DIR__) . '/app/bootstrap.php';
ob_start();
try {
    App\Core\Router::run();
} catch (Throwable $e) {
    $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    error_log('SKILVI ' . $e->getMessage());
    if (str_starts_with($path, '/api/')) {
        App\Core\Response::error('server', 'Something went wrong. Try again.', 500);
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine();
    exit;
}
