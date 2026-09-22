<?php
declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $cfg = Config::get('session');
        if (!headers_sent()) {
            session_set_cookie_params([
                'lifetime' => $cfg['lifetime'],
                'path'     => '/',
                'domain'   => '',
                'secure'   => (bool) $cfg['secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_name($cfg['name']);
        }
        if (PHP_SAPI === 'cli') {
            if (!headers_sent()) {
                @ini_set('session.use_cookies', '0');
            }
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        if (!headers_sent()) {
            setcookie('skilvi_csrf', (string) $_SESSION['csrf'], [
                'expires'  => time() + (int) $cfg['lifetime'],
                'path'     => '/',
                'secure'   => (bool) $cfg['secure'],
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function csrf(): string
    {
        return (string) self::get('csrf');
    }

    public static function csrfValid(?string $token): bool
    {
        $known = self::get('csrf');
        return is_string($token) && $token !== '' && is_string($known)
            && hash_equals($known, $token);
    }

    public static function userId(): ?int
    {
        $id = self::get('user_id');
        return $id === null ? null : (int) $id;
    }

    public static function login(int $userId): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }
        self::set('user_id', $userId);
        self::set('login_at', time());
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (!headers_sent() && ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
            setcookie('skilvi_csrf', '', time() - 42000, '/');
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function claim(string $phone, string $purpose): void
    {
        self::set('otp_claim', ['phone' => $phone, 'purpose' => $purpose, 'at' => time()]);
    }

    public static function claimMatches(string $phone, string $purpose): bool
    {
        $c = self::get('otp_claim');
        if (!is_array($c)) {
            return false;
        }
        $ttl = (int) Config::get('otp.claim_ttl');
        return ($c['phone'] ?? '') === $phone
            && ($c['purpose'] ?? '') === $purpose
            && (time() - (int) ($c['at'] ?? 0)) <= $ttl;
    }

    public static function clearClaim(): void
    {
        self::remove('otp_claim');
    }
}
