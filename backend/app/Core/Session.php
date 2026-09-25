<?php
declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        $cfg = Config::get('session') ?? [];
        $name = (string) ($cfg['name'] ?? 'skilvi_sid');
        $life = max(3600, (int) ($cfg['lifetime'] ?? 30 * 86400));
        $secure = (bool) ($cfg['secure'] ?? false);

        if (session_status() === PHP_SESSION_ACTIVE && session_name() === $name) {
            if (empty($_SESSION['csrf'])) {
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
            }
            self::emitCsrf($life, $secure);
            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $save = dirname(__DIR__, 2) . '/storage/sessions';
        if (!is_dir($save)) {
            @mkdir($save, 0775, true);
        }
        if (is_dir($save) && is_writable($save)) {
            session_save_path($save);
        }
        @ini_set('session.gc_maxlifetime', (string) $life);
        @ini_set('session.use_strict_mode', '1');
        @ini_set('session.use_only_cookies', '1');
        @ini_set('session.use_trans_sid', '0');
        @ini_set('session.cookie_httponly', '1');
        @ini_set('session.cookie_samesite', 'Lax');

        if (!headers_sent()) {
            session_name($name);
            session_set_cookie_params([
                'lifetime' => $life,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        if (PHP_SAPI === 'cli' && !headers_sent()) {
            @ini_set('session.use_cookies', '0');
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        self::emitCsrf($life, $secure);
    }

    private static function emitCsrf(int $life, bool $secure): void
    {
        if (headers_sent()) {
            return;
        }
        setcookie('skilvi_csrf', (string) ($_SESSION['csrf'] ?? ''), [
            'expires'  => time() + $life,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
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

    /** Drop the signed-in user without killing CSRF / pending OTP. */
    public static function forgetUser(): void
    {
        unset($_SESSION['user_id'], $_SESSION['login_at']);
    }

    public static function login(int $userId): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }
        self::forgetUser();
        self::set('user_id', $userId);
        self::set('login_at', time());
        self::remove('pending_login_id');
        self::remove('pending_register');
        self::clearClaim();
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (!headers_sent() && ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'] ?: '/',
                'domain'   => $p['domain'] ?? '',
                'secure'   => (bool) $p['secure'],
                'httponly' => (bool) $p['httponly'],
                'samesite' => 'Lax',
            ]);
            setcookie('skilvi_csrf', '', time() - 42000, '/');
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        self::start();
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
