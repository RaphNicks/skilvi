<?php
declare(strict_types=1);

namespace App\Core;

final class RateLimit
{
    /** @return array{ok: bool, wait: int} */
    public static function hit(string $bucket, int $max, int $windowSec): array
    {
        $now = time();
        $row = Db::fetch('SELECT hits, reset_at FROM rate_limits WHERE bucket = ?', [$bucket]);

        if ($row !== null && (int) $row['reset_at'] > $now) {
            $hits = (int) $row['hits'];
            if ($hits >= $max) {
                return ['ok' => false, 'wait' => (int) $row['reset_at'] - $now];
            }
            Db::run('UPDATE rate_limits SET hits = hits + 1 WHERE bucket = ?', [$bucket]);
            return ['ok' => true, 'wait' => 0];
        }

        try {
            Db::run(
                'INSERT INTO rate_limits (bucket, hits, reset_at) VALUES (?, 1, ?)',
                [$bucket, $now + $windowSec]
            );
        } catch (\Throwable $e) {
            Db::run(
                'UPDATE rate_limits SET hits = 1, reset_at = ? WHERE bucket = ?',
                [$now + $windowSec, $bucket]
            );
        }
        return ['ok' => true, 'wait' => 0];
    }
}
