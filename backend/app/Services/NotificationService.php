<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Db;

final class NotificationService
{
    public static function push(int $userId, string $kind, string $title, string $body, ?string $href = null): void
    {
        Db::run(
            'INSERT INTO notifications (user_id, kind, title, body, href, created_at) VALUES (?,?,?,?,?,?)',
            [$userId, $kind, $title, $body, $href, now_iso()]
        );
    }

    public static function list(int $userId): array
    {
        $rows = Db::fetchAll(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 50',
            [$userId]
        );
        $items = array_map([self::class, 'card'], $rows);
        $unread = (int) (Db::fetch(
            'SELECT COUNT(*) c FROM notifications WHERE user_id = ? AND read_at IS NULL',
            [$userId]
        )['c'] ?? 0);
        return ['items' => $items, 'unread' => $unread];
    }

    public static function readAll(int $userId): array
    {
        Db::run(
            'UPDATE notifications SET read_at = COALESCE(read_at, ?) WHERE user_id = ? AND read_at IS NULL',
            [now_iso(), $userId]
        );
        return self::list($userId);
    }

    public static function badges(int $userId): array
    {
        $n = (int) (Db::fetch(
            'SELECT COUNT(*) c FROM notifications WHERE user_id = ? AND read_at IS NULL',
            [$userId]
        )['c'] ?? 0);
        $m = (int) (Db::fetch(
            "SELECT COUNT(*) c FROM messages m
             JOIN conversations c ON c.id = m.conversation_id
             LEFT JOIN conversation_reads r ON r.conversation_id = c.id AND r.user_id = ?
             WHERE (c.client_id = ? OR c.worker_id = ?)
               AND m.sender_id <> ?
               AND (r.last_read_at IS NULL OR m.created_at > r.last_read_at)",
            [$userId, $userId, $userId, $userId]
        )['c'] ?? 0);
        $d = (int) (Db::fetch(
            "SELECT COUNT(*) c FROM disputes d
             JOIN orders o ON o.id = d.order_id
             WHERE d.status = 'open' AND (o.client_id = ? OR o.worker_id = ?)",
            [$userId, $userId]
        )['c'] ?? 0);
        return [
            'notifications' => $n,
            'messages'      => $m,
            'disputes'      => $d,
        ];
    }

    /** @param array<string,mixed> $n */
    private static function card(array $n): array
    {
        $kind = (string) $n['kind'];
        $cat = match (true) {
            str_contains($kind, 'payout') || str_contains($kind, 'wallet') || $kind === 'money' => 'money',
            str_contains($kind, 'message') || $kind === 'msgs' => 'msgs',
            str_contains($kind, 'dispute') => 'orders',
            default => 'orders',
        };
        $href = $n['href'] ?? null;
        if (!$href) {
            $href = match ($cat) {
                'money' => 'worker-wallet.html',
                'msgs' => 'messages.html',
                default => 'notifications.html',
            };
        }
        return [
            'id'      => (int) $n['id'],
            'kind'    => $kind,
            'cat'     => $cat,
            'title'   => $n['title'],
            'body'    => $n['body'],
            'href'    => $href,
            'unread'  => $n['read_at'] === null,
            'time'    => ago($n['created_at']),
            'date'    => $n['created_at'],
        ];
    }
}
