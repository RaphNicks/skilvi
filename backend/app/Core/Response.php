<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    private function __construct()
    {
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): never
    {
        $body = json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        $etag = '"' . sha1((string) $body) . '"';
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($status === 200 && $method === 'GET') {
            $headers = ['ETag' => $etag, 'Cache-Control' => 'private, no-cache'] + $headers;
            $inm = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
            if ($inm !== '' && hash_equals($etag, $inm)) {
                self::send(304, $headers, '');
            }
        }
        self::send($status, ['Content-Type' => 'application/json; charset=utf-8'] + $headers, (string) $body);
    }

    public static function error(string $code, string $message, int $status = 422, ?array $fields = null, array $headers = []): never
    {
        $err = ['code' => $code, 'message' => $message];
        if ($fields !== null) {
            $err['fields'] = $fields;
        }
        if ($status === 429 && !isset($headers['Retry-After'])) {
            $headers['Retry-After'] = (string) (int) ($fields['retry'] ?? 60);
        }
        self::send($status, ['Content-Type' => 'application/json; charset=utf-8'] + $headers,
            json_encode(['ok' => false, 'error' => $err], JSON_UNESCAPED_UNICODE));
    }

    public static function html(string $html, int $status = 200): never
    {
        self::send($status, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'private, no-cache'], $html);
    }

    public static function csv(string $filename, string $body): never
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?: 'export.csv';
        self::send(200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $safe . '"',
            'Cache-Control'       => 'no-store',
        ], $body);
    }

    public static function redirect(string $to, int $status = 302): never
    {
        if (!str_starts_with($to, '/') || str_starts_with($to, '//')) {
            $to = '/';
        }
        self::send($status, ['Location' => $to], '');
    }

    public static function notFound(string $html): never
    {
        self::send(404, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    public static function empty(int $status = 204, array $headers = []): never
    {
        self::send($status, $headers, '');
    }

    /** @return array<string,string> */
    public static function securityHeaders(): array
    {
        $dev = Config::isDev();
        $csp = "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; img-src 'self' data: blob:; media-src 'self'; connect-src 'self'; base-uri 'self'; form-action 'self'";
        if (!$dev) {
            $csp .= "; frame-ancestors 'none'";
        }
        $h = [
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy'        => 'strict-origin-when-cross-origin',
            'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=()',
            'X-DNS-Prefetch-Control' => 'off',
            'Content-Security-Policy'=> $csp,
        ];
        if (!$dev) {
            $h['X-Frame-Options'] = 'DENY';
            $h['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
        return $h;
    }

    private static function send(int $status, array $headers, string $body): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code($status);
            foreach (self::securityHeaders() + $headers as $k => $v) {
                header($k . ': ' . $v);
            }
        }
        echo $body;
        exit;
    }
}
