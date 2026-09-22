<?php
declare(strict_types=1);

namespace App\Core;

use App\AppError;

final class Router
{
    /** @var list<array{method:string,pattern:string,regex:string,keys:list<string>,handler:callable|array}> */
    private static array $routes = [];

    public static function get(string $path, callable|array $handler): void
    {
        self::add('GET', $path, $handler);
    }

    public static function post(string $path, callable|array $handler): void
    {
        self::add('POST', $path, $handler);
    }

    public static function patch(string $path, callable|array $handler): void
    {
        self::add('PATCH', $path, $handler);
    }

    public static function delete(string $path, callable|array $handler): void
    {
        self::add('DELETE', $path, $handler);
    }

    public static function add(string $method, string $path, callable|array $handler): void
    {
        $keys = [];
        $regex = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', function ($m) use (&$keys) {
            $keys[] = $m[1];
            return '([^/]+)';
        }, rtrim($path, '/') ?: '/');
        self::$routes[] = [
            'method'  => strtoupper($method),
            'pattern' => $path,
            'regex'   => '#^' . $regex . '$#',
            'keys'    => $keys,
            'handler' => $handler,
        ];
    }

    public static function run(): void
    {
        require dirname(__DIR__) . '/routes.php';

        $req = new Request();
        $path = rtrim($req->path, '/') ?: '/';

        if ($req->method === 'OPTIONS') {
            Response::empty(204, ['Allow' => 'GET, POST, PATCH, DELETE, OPTIONS']);
        }

        if (strlen($req->raw) > 1_048_576) {
            Response::error('too_large', 'That request is too large.', 413);
        }

        try {
            self::maintenance($req, $path);
            self::throttle($req, $path);
        } catch (\Throwable $e) {
            self::logError($e);
        }

        foreach (self::$routes as $route) {
            if ($route['method'] !== $req->method) {
                continue;
            }
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }
            $params = [];
            foreach ($route['keys'] as $i => $k) {
                $params[$k] = rawurldecode($m[$i + 1]);
            }

            if (str_starts_with($path, '/api/') && !in_array($req->method, ['GET', 'HEAD', 'OPTIONS'], true)
                && !str_starts_with($path, '/api/webhooks/')) {
                $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($req->input['csrf'] ?? null);
                if (!Session::csrfValid(is_string($token) ? $token : null)) {
                    Response::error('csrf', 'Your session expired. Refresh the page and try again.', 403);
                }
            }

            try {
                $handler = $route['handler'];
                if (is_array($handler)) {
                    [$class, $method] = $handler;
                    $class::$method($req, $params);
                } else {
                    $handler($req, $params);
                }
            } catch (AppError $e) {
                $headers = [];
                if ($e->http === 429) {
                    $headers['Retry-After'] = (string) (int) (($e->fields['retry'] ?? 60));
                }
                Response::error($e->errorCode, $e->getMessage(), $e->http, $e->fields, $headers);
            } catch (\Throwable $e) {
                self::logError($e);
                if (Config::isDev()) {
                    Response::error('server', $e->getMessage(), 500, ['file' => $e->getFile(), 'line' => $e->getLine()]);
                }
                Response::error('server', 'Something went wrong. Try again.', 500);
            }
            return;
        }

        if ($req->method === 'GET') {
            \App\Controllers\PagesController::serve($req);
            return;
        }

        Response::error('not_found', 'Not found', 404);
    }

    private static function maintenance(Request $req, string $path): void
    {
        if ($path === '/api/health') {
            return;
        }
        try {
            $row = Db::fetch("SELECT value FROM settings WHERE key = 'maintenance'");
        } catch (\Throwable $e) {
            return;
        }
        if (!$row || (string) $row['value'] !== '1') {
            return;
        }
        $uid = Session::userId();
        if ($uid) {
            $u = Db::fetch('SELECT roles FROM users WHERE id = ?', [$uid]);
            if ($u && str_contains((string) $u['roles'], 'admin')) {
                return;
            }
        }
        if (str_starts_with($path, '/api/')) {
            Response::error('maintenance', 'Skilvi is down for a short maintenance. Try again shortly.', 503);
        }
        $html = '<!doctype html><meta charset="utf-8"><title>Skilvi — back shortly</title>'
            . '<body style="font-family:Inter,system-ui,sans-serif;padding:48px;max-width:40rem">'
            . '<h1>Skilvi is paused for maintenance</h1>'
            . '<p>Escrow, wallets and orders are frozen safely. We’ll be back shortly.</p></body>';
        Response::html($html, 503);
    }

    private static function throttle(Request $req, string $path): void
    {
        if (!str_starts_with($path, '/api/') || $path === '/api/health') {
            return;
        }
        $ip = $req->ip();
        if (str_starts_with($path, '/api/auth/') || $path === '/api/auth') {
            $hit = RateLimit::hit('auth:' . $ip, 20, 60);
            if (!$hit['ok']) {
                Response::error('rate_limited', 'Too many sign-in tries. Wait a minute.', 429, ['retry' => $hit['wait']]);
            }
            return;
        }
        if (str_starts_with($path, '/api/payments') || str_starts_with($path, '/api/withdrawals')) {
            $hit = RateLimit::hit('money:' . $ip, 30, 60);
            if (!$hit['ok']) {
                Response::error('rate_limited', 'Slow down on payments.', 429, ['retry' => $hit['wait']]);
            }
            return;
        }
        $max = in_array($req->method, ['GET', 'HEAD'], true) ? 180 : 60;
        $hit = RateLimit::hit('api:' . $ip, $max, 60);
        if (!$hit['ok']) {
            Response::error('rate_limited', 'Too many requests. Wait a moment.', 429, ['retry' => $hit['wait']]);
        }
    }

    private static function logError(\Throwable $e): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = sprintf("[%s] %s %s:%d %s\n", gmdate('c'), $e::class, $e->getFile(), $e->getLine(), $e->getMessage());
        @file_put_contents($dir . '/error.log', $line, FILE_APPEND);
        error_log('SKILVI ' . trim($line));
    }
}
