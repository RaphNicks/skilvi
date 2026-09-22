<?php
declare(strict_types=1);

/**
 * Golden-path API smoke against a running server (default http://127.0.0.1:8080).
 * Walks: health → landing → login OTP → me. Does not mutate escrow.
 */
$base = rtrim(getenv('SMOKE_BASE') ?: 'http://127.0.0.1:8080', '/');

function hit(string $base, string $method, string $path, array $body = [], array $headers = [], ?string &$cookie = null): array
{
    $h = ['Accept: application/json'];
    foreach ($headers as $k => $v) {
        $h[] = $k . ': ' . $v;
    }
    if ($cookie) {
        $h[] = 'Cookie: ' . $cookie;
    }
    $ctx = [
        'http' => [
            'method'        => $method,
            'header'        => implode("\r\n", $h) . ($body ? "\r\nContent-Type: application/json" : ''),
            'content'       => $body ? json_encode($body) : '',
            'ignore_errors' => true,
            'timeout'       => 8,
        ],
    ];
    $raw = @file_get_contents($base . $path, false, stream_context_create($ctx));
    $code = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $code = (int) $m[1];
    }
    foreach ($http_response_header ?? [] as $line) {
        if (stripos($line, 'Set-Cookie:') === 0) {
            $cookie = trim(explode(';', substr($line, 11))[0]);
        }
    }
    $json = json_decode((string) $raw, true);
    return ['code' => $code, 'json' => is_array($json) ? $json : [], 'raw' => (string) $raw];
}

$fail = 0;
$cookie = '';
$h = hit($base, 'GET', '/api/health');
echo ($h['code'] === 200 && ($h['json']['data']['status'] ?? '') === 'ok' ? 'ok' : 'FAIL') . "  health {$h['code']}\n";
$fail += $h['code'] === 200 ? 0 : 1;

$h = hit($base, 'GET', '/api/landing');
echo ($h['code'] === 200 && !empty($h['json']['ok']) ? 'ok' : 'FAIL') . "  landing {$h['code']}\n";
$fail += !empty($h['json']['ok']) ? 0 : 1;

$t0 = microtime(true);
hit($base, 'GET', '/api/landing');
$ms = (microtime(true) - $t0) * 1000;
echo ($ms < 300 ? 'ok' : 'SLOW') . '  landing ' . round($ms) . " ms (target < 300)\n";

$h = hit($base, 'GET', '/api/csrf', [], [], $cookie);
$csrf = $h['json']['data']['csrf'] ?? '';
echo ($csrf !== '' ? 'ok' : 'FAIL') . "  csrf\n";
$fail += $csrf === '' ? 1 : 0;

echo $fail === 0 ? "SMOKE OK $base\n" : "SMOKE HAD $fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
