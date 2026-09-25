<?php
declare(strict_types=1);

namespace App\Core;

use App\AppError;
use App\Models\User;

final class Auth
{
    private static bool $resolved = false;
    private static ?int $id = null;
    private static ?string $token = null;

    public static function id(): int
    {
        $id = self::resolvedId();
        if ($id === null) {
            throw new AppError('unauth', 'Log in to continue.', 401);
        }
        return $id;
    }

    public static function resolvedId(): ?int
    {
        if (!self::$resolved) {
            self::$resolved = true;
            // Tab token only. The cookie is shared across tabs and must not
            // impersonate another account on refresh.
            self::$id = self::idFromBearer();
        }
        return self::$id;
    }

    public static function user(): array
    {
        $u = User::find(self::id());
        if ($u === null) {
            throw new AppError('unauth', 'Log in to continue.', 401);
        }
        if (($u['status'] ?? 'active') !== 'active') {
            $msg = ($u['status'] ?? '') === 'banned'
                ? 'This account is banned and cannot be used.'
                : 'This account is not active. Contact support.';
            throw new AppError('suspended', $msg, 403);
        }
        return $u;
    }

    public static function requireRole(string $role): int
    {
        $u = self::user();
        if (!str_contains((string) $u['roles'], $role)) {
            throw new AppError('forbidden', 'This action needs a ' . $role . ' account.', 403);
        }
        return (int) $u['id'];
    }

    public static function issueToken(int $userId): string
    {
        self::ensureTable();
        $token = bin2hex(random_bytes(32));
        $now = time();
        Db::run(
            'INSERT INTO auth_tokens (token, user_id, expires_at, created_at) VALUES (?,?,?,?)',
            [$token, $userId, $now + 30 * 86400, $now]
        );
        return $token;
    }

    public static function revokeCurrent(): void
    {
        $t = self::rawToken();
        if ($t === null) {
            return;
        }
        self::ensureTable();
        Db::run('DELETE FROM auth_tokens WHERE token=?', [$t]);
    }

    public static function revokeUser(int $userId): void
    {
        self::ensureTable();
        Db::run('DELETE FROM auth_tokens WHERE user_id=?', [$userId]);
    }

    private static function idFromBearer(): ?int
    {
        $t = self::rawToken();
        if ($t === null) {
            return null;
        }
        self::ensureTable();
        $row = Db::fetch('SELECT user_id, expires_at FROM auth_tokens WHERE token=?', [$t]);
        if ($row === null || (int) $row['expires_at'] < time()) {
            if ($row) {
                Db::run('DELETE FROM auth_tokens WHERE token=?', [$t]);
            }
            return null;
        }
        self::$token = $t;
        return (int) $row['user_id'];
    }

    private static function rawToken(): ?string
    {
        $hdr = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if ($hdr === '' && function_exists('apache_request_headers')) {
            $h = apache_request_headers();
            if (is_array($h)) {
                $hdr = (string) ($h['Authorization'] ?? $h['authorization'] ?? '');
            }
        }
        if (preg_match('/Bearer\s+([a-f0-9]{64})/i', $hdr, $m)) {
            return strtolower($m[1]);
        }
        $x = (string) ($_SERVER['HTTP_X_SKILVI_TOKEN'] ?? '');
        if (preg_match('/^[a-f0-9]{64}$/i', $x)) {
            return strtolower($x);
        }
        return null;
    }

    private static function ensureTable(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $sql = 'CREATE TABLE IF NOT EXISTS auth_tokens (
            token VARCHAR(64) NOT NULL PRIMARY KEY,
            user_id INT NOT NULL,
            expires_at INT NOT NULL,
            created_at INT NOT NULL
        )';
        if (!Db::isMysql()) {
            $sql = 'CREATE TABLE IF NOT EXISTS auth_tokens (
                token TEXT NOT NULL PRIMARY KEY,
                user_id INTEGER NOT NULL,
                expires_at INTEGER NOT NULL,
                created_at INTEGER NOT NULL
            )';
        } else {
            $sql .= ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
        }
        try {
            Db::exec($sql);
        } catch (\Throwable $e) {
            error_log('SKILVI auth_tokens ' . $e->getMessage());
        }
    }
}
