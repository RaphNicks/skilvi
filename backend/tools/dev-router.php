<?php
declare(strict_types=1);

/**
 * Local router for `php -S`. Serves frontend assets + backend /js, then the app.
 *
 * From the GitHub clone:
 *   set FRONTEND_ROOT to the repo root (folder with index.html)
 *   cd backend
 *   php tools/install.php
 *   php -S 127.0.0.1:8080 tools/dev-router.php
 */
$uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$backend = dirname(__DIR__);
$public = $backend . '/public';
$parent = dirname($backend);
$frontend = getenv('FRONTEND_ROOT') ?: (
    is_file($parent . DIRECTORY_SEPARATOR . 'index.html')
        ? $parent
        : $parent . DIRECTORY_SEPARATOR . 'skilvi-frontend'
);

$ext = strtolower(pathinfo($uri, PATHINFO_EXTENSION));
$static = [
    'css' => 'text/css; charset=utf-8',
    'js' => 'application/javascript; charset=utf-8',
    'mjs' => 'application/javascript; charset=utf-8',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'svg' => 'image/svg+xml',
    'ico' => 'image/x-icon',
    'mp4' => 'video/mp4',
    'webm' => 'video/webm',
    'woff' => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf' => 'font/ttf',
    'map' => 'application/json',
    'txt' => 'text/plain; charset=utf-8',
];

if ($uri !== '/' && isset($static[$ext])) {
    foreach ([$public . $uri, $frontend . $uri] as $file) {
        $real = realpath($file);
        if ($real === false || !is_file($real)) {
            continue;
        }
        $allowed = [realpath($public), realpath($frontend)];
        $ok = false;
        foreach ($allowed as $base) {
            if ($base && str_starts_with($real, $base)) {
                $ok = true;
                break;
            }
        }
        if (!$ok) {
            continue;
        }
        header('Content-Type: ' . $static[$ext]);
        header('Cache-Control: public, max-age=60');
        readfile($real);
        return true;
    }
}

require $public . '/index.php';
