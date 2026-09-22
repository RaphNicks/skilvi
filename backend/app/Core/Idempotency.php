<?php
declare(strict_types=1);

namespace App\Core;

final class Idempotency
{
    public static function get(int $userId, string $key): ?array
    {
        $key = self::norm($key);
        if ($key === '') {
            return null;
        }
        $row = Db::fetch(
            'SELECT body, created_at FROM idempotency_keys WHERE user_id = ? AND idem_key = ?',
            [$userId, $key]
        );
        if ($row === null) {
            return null;
        }
        if ((time() - strtotime((string) $row['created_at'])) > 86400) {
            Db::run('DELETE FROM idempotency_keys WHERE user_id = ? AND idem_key = ?', [$userId, $key]);
            return null;
        }
        $data = json_decode((string) $row['body'], true);
        return is_array($data) ? $data : null;
    }

    public static function put(int $userId, string $key, array $body): void
    {
        $key = self::norm($key);
        if ($key === '') {
            return;
        }
        Db::run(
            'INSERT OR REPLACE INTO idempotency_keys (idem_key, user_id, body, created_at) VALUES (?,?,?,?)',
            [$key, $userId, json_encode($body), now_iso()]
        );
    }

    public static function header(): string
    {
        return trim((string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
    }

    private static function norm(string $key): string
    {
        $key = trim($key);
        return strlen($key) > 120 ? substr($key, 0, 120) : $key;
    }
}
