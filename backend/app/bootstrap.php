<?php
declare(strict_types=1);

error_reporting(E_ALL);
// Never print warnings into JSON/HTML — that breaks login cookies and OTP.
ini_set('display_errors', '0');
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
    $p = preg_replace('/\D/', '', $p) ?? $p;
    if (strlen($p) < 13) {
        return $p === '' ? '' : '+' . $p;
    }
    return '+' . substr($p, 0, 3) . ' ' . substr($p, 3, 3) . ' ••• •' . substr($p, -3);
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
