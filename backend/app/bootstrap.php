<?php
declare(strict_types=1);

error_reporting(E_ALL);
// Never print warnings into JSON/HTML — that breaks login cookies and OTP.
ini_set('display_errors', '0');

(function (): void {
    $files = [
        dirname(__DIR__) . '/.env',
        dirname(__DIR__, 2) . '/.env',
    ];
    foreach ($files as $file) {
        if (!is_file($file) || !is_readable($file)) {
            continue;
        }
        $raw = file_get_contents($file);
        if ($raw === false) {
            continue;
        }
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }
        foreach (preg_split("/\r\n|\n|\r/", $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            if ($key === '' || !preg_match('/^[A-Z_][A-Z0-9_]*$/', $key)) {
                continue;
            }
            $existing = $_ENV[$key] ?? getenv($key);
            if (is_string($existing) && $existing !== '') {
                continue;
            }
            $value = trim($value);
            if ($value !== '' && (str_starts_with($value, '"') || str_starts_with($value, "'"))) {
                $quote = $value[0];
                if (str_ends_with($value, $quote) && strlen($value) >= 2) {
                    $value = substr($value, 1, -1);
                }
            }
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
})();

/** Env value from .env (Windows getenv() often misses putenv). */
function skilvi_env(string $key, string $default = ''): string
{
    foreach ([$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)] as $v) {
        if (is_string($v) && $v !== '') {
            return $v;
        }
    }
    return $default;
}
ini_set('log_errors', '1');
$logDir = dirname(__DIR__) . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
ini_set('error_log', $logDir . '/php.ini-errors.log');

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (str_starts_with($class, $prefix)) {
        $rel = str_replace('\\', '/', substr($class, strlen($prefix)));
        $file = __DIR__ . '/' . $rel . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** Digits with 234 prefix, or '' if not a valid NG mobile. */
function normalize_phone(string $raw): string
{
    $p = preg_replace('/[^\d+]/', '', $raw) ?? '';
    if (str_starts_with($p, '+')) {
        $p = substr($p, 1);
    }
    if (str_starts_with($p, '00')) {
        $p = substr($p, 2);
    }
    if (str_starts_with($p, '0') && strlen($p) === 11) {
        $p = '234' . substr($p, 1);
    }
    return preg_match('/^234[789]\d{9}$/', $p) ? $p : '';
}

function mask_phone(string $p): string
{
    if (str_contains($p, '@') || str_starts_with($p, 'e:')) {
        return mask_dest($p);
    }
    $p = preg_replace('/\D/', '', $p) ?? $p;
    if (strlen($p) < 13) {
        return $p === '' ? '' : '+' . $p;
    }
    return '+' . substr($p, 0, 3) . ' ' . substr($p, 3, 3) . ' ••• •' . substr($p, -3);
}

function mask_dest(string $d): string
{
    if (str_starts_with($d, 'e:')) {
        $d = substr($d, 2);
    }
    if (str_contains($d, '@')) {
        [$u, $h] = explode('@', $d, 2);
        $keep = mb_substr($u, 0, 1);
        return $keep . '•••@' . $h;
    }
    return mask_phone($d);
}

function otp_channel(string $dest): string
{
    return (str_contains($dest, '@') || str_starts_with($dest, 'e:')) ? 'email' : 'sms';
}

function format_phone(string $p): string
{
    $p = preg_replace('/\D/', '', $p) ?? $p;
    if (strlen($p) !== 13) {
        return $p;
    }
    return '+' . substr($p, 0, 3) . ' ' . substr($p, 3, 3) . ' ' . substr($p, 6, 3) . ' ' . substr($p, 9);
}

function now_iso(): string
{
    return gmdate('Y-m-d H:i:s');
}

function kobo_naira(?int $kobo): ?int
{
    return $kobo === null ? null : (int) round($kobo / 100);
}

function ngn_fmt(?int $kobo, string $budgetType = 'fixed'): string
{
    if ($budgetType === 'negotiable' || $kobo === null) {
        return 'Negotiable';
    }
    return '₦' . number_format(kobo_naira($kobo) ?? 0, 0, '.', ',');
}

function ago(?string $iso): string
{
    if (!$iso) {
        return '';
    }
    $t = strtotime($iso . ' UTC');
    if ($t === false) {
        $t = strtotime($iso);
    }
    if ($t === false) {
        return '';
    }
    $d = max(0, time() - $t);
    if ($d < 60) {
        return 'just now';
    }
    if ($d < 3600) {
        $n = (int) floor($d / 60);
        return $n . ($n === 1 ? ' min ago' : ' mins ago');
    }
    if ($d < 86400) {
        $n = (int) floor($d / 3600);
        return $n . ($n === 1 ? ' hr ago' : ' hrs ago');
    }
    $n = (int) floor($d / 86400);
    return $n === 1 ? '1 day ago' : $n . ' days ago';
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $out = '';
    foreach ($parts as $p) {
        if ($p !== '') {
            $out .= mb_strtoupper(mb_substr($p, 0, 1));
        }
        if (strlen($out) >= 2) {
            break;
        }
    }
    return $out !== '' ? $out : 'SK';
}

if (PHP_SAPI !== 'cli') {
    App\Core\Session::start();
}
