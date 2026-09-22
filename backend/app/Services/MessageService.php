<?php
declare(strict_types=1);

namespace App\Services;

use App\AppError;
use App\Core\Db;
use App\Models\User;

final class MessageService
{
    public static function list(int $userId): array
    {
        $rows = Db::fetchAll(
            "SELECT c.*,
                    (SELECT body FROM messages WHERE conversation_id = c.id ORDER BY id DESC LIMIT 1) AS last_body,
                    (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY id DESC LIMIT 1) AS last_at,
                    o.code AS order_code, o.title AS order_title, o.amount_kobo,
                    cu.full_name AS client_name, wu.full_name AS worker_name,
                    cp.tone AS client_tone, wp.tone AS worker_tone, wp.verified AS worker_verified
             FROM conversations c
             LEFT JOIN orders o ON o.id = c.order_id
             LEFT JOIN users cu ON cu.id = COALESCE(c.client_id, o.client_id)
             LEFT JOIN users wu ON wu.id = COALESCE(c.worker_id, o.worker_id)
             LEFT JOIN profiles cp ON cp.user_id = cu.id
             LEFT JOIN profiles wp ON wp.user_id = wu.id
             WHERE c.client_id = ? OR c.worker_id = ? OR o.client_id = ? OR o.worker_id = ?
             ORDER BY COALESCE(c.last_at, c.created_at) DESC",
            [$userId, $userId, $userId, $userId]
        );
        $out = [];
        $seen = [];
        foreach ($rows as $r) {
            if (isset($seen[$r['id']])) {
                continue;
            }
            $seen[$r['id']] = true;
            $out[] = self::threadCard($r, $userId, false);
        }
        return $out;
    }

    public static function get(int $userId, string $key): array
    {
        $row = self::row($key);
        self::mustParty($row, $userId);
        Db::run(
            'INSERT OR REPLACE INTO conversation_reads (conversation_id, user_id, last_read_at) VALUES (?,?,?)',
            [$row['id'], $userId, now_iso()]
        );
        $msgs = Db::fetchAll(
            'SELECT m.*, u.full_name FROM messages m JOIN users u ON u.id = m.sender_id
             WHERE m.conversation_id = ? ORDER BY m.id ASC',
            [$row['id']]
        );
        $card = self::threadCard($row, $userId, true);
        $card['messages'] = array_map(static function ($m) use ($userId) {
            $t = strtotime($m['created_at']) ?: time();
            return [
                'id'      => (int) $m['id'],
                'mine'    => (int) $m['sender_id'] === $userId,
                'name'    => $m['full_name'],
                'body'    => $m['body'],
                'time'    => date('H:i', $t),
                'date'    => date('M j', $t),
            ];
        }, $msgs);
        return $card;
    }

    public static function send(int $userId, string $key, string $body): array
    {
        $row = self::row($key);
        self::mustParty($row, $userId);
        $body = trim($body);
        if ($body === '') {
            throw new AppError('invalid', 'Write a message.', 422, ['body' => 'Write a message.']);
        }
        if (mb_strlen($body) > 4000) {
            throw new AppError('invalid', 'That message is too long.', 422);
        }
        $now = now_iso();
        Db::run(
            'INSERT INTO messages (conversation_id, sender_id, body, created_at) VALUES (?,?,?,?)',
            [$row['id'], $userId, $body, $now]
        );
        Db::run('UPDATE conversations SET last_at = ? WHERE id = ?', [$now, $row['id']]);
        $other = (int) ($row['client_id'] ?? 0) === $userId
            ? (int) ($row['worker_id'] ?? 0)
            : (int) ($row['client_id'] ?? 0);
        if ($other) {
            $me = User::find($userId);
            NotificationService::push(
                $other,
                'message',
                'New message from ' . ($me['full_name'] ?? 'Skilvi'),
                mb_substr($body, 0, 120),
                'messages.html?id=' . $row['id']
            );
        }
        return self::get($userId, (string) $row['id']);
    }

    public static function forOrder(int $userId, string $orderKey): array
    {
        $conv = self::ensureForOrder($orderKey);
        return self::get($userId, (string) $conv['id']);
    }

    /** @return array<string,mixed> */
    public static function ensureForOrder(string $orderKey): array
    {
        $o = Db::fetch('SELECT * FROM orders WHERE code = ? OR id = ?', [$orderKey, $orderKey]);
        if ($o === null) {
            throw new AppError('not_found', 'Order not found.', 404);
        }
        $c = Db::fetch('SELECT * FROM conversations WHERE order_id = ? AND dispute_id IS NULL', [$o['id']]);
        if ($c) {
            return $c;
        }
        $now = now_iso();
        Db::run(
            'INSERT INTO conversations (order_id, client_id, worker_id, created_at, last_at) VALUES (?,?,?,?,?)',
            [$o['id'], $o['client_id'], $o['worker_id'], $now, $now]
        );
        return Db::fetch('SELECT * FROM conversations WHERE id = ?', [Db::lastInsertId()]) ?: [];
    }

    /** @return array<string,mixed> */
    private static function row(string $key): array
    {
        $row = Db::fetch(
            "SELECT c.*,
                    o.code AS order_code, o.title AS order_title, o.amount_kobo,
                    cu.full_name AS client_name, wu.full_name AS worker_name,
                    cp.tone AS client_tone, wp.tone AS worker_tone, wp.verified AS worker_verified
             FROM conversations c
             LEFT JOIN orders o ON o.id = c.order_id
             LEFT JOIN users cu ON cu.id = COALESCE(c.client_id, o.client_id)
             LEFT JOIN users wu ON wu.id = COALESCE(c.worker_id, o.worker_id)
             LEFT JOIN profiles cp ON cp.user_id = cu.id
             LEFT JOIN profiles wp ON wp.user_id = wu.id
             WHERE c.id = ?",
            [$key]
        );
        if ($row === null) {
            throw new AppError('not_found', 'Conversation not found.', 404);
        }
        return $row;
    }

    private static function mustParty(array $row, int $uid): void
    {
        $ok = (int) ($row['client_id'] ?? 0) === $uid || (int) ($row['worker_id'] ?? 0) === $uid;
        if (!$ok) {
            throw new AppError('forbidden', 'This thread is not yours.', 403);
        }
    }

    /** @param array<string,mixed> $r */
    private static function threadCard(array $r, int $uid, bool $full): array
    {
        $otherName = (int) ($r['client_id'] ?? 0) === $uid ? ($r['worker_name'] ?? 'Worker') : ($r['client_name'] ?? 'Client');
        $otherTone = (int) ($r['client_id'] ?? 0) === $uid ? ($r['worker_tone'] ?? 'a1') : ($r['client_tone'] ?? 'a2');
        $read = Db::fetch(
            'SELECT last_read_at FROM conversation_reads WHERE conversation_id = ? AND user_id = ?',
            [$r['id'], $uid]
        );
        if ($read && !empty($read['last_read_at'])) {
            $unread = (int) (Db::fetch(
                'SELECT COUNT(*) c FROM messages WHERE conversation_id = ? AND sender_id <> ? AND created_at > ?',
                [$r['id'], $uid, $read['last_read_at']]
            )['c'] ?? 0);
        } else {
            $unread = (int) (Db::fetch(
                'SELECT COUNT(*) c FROM messages WHERE conversation_id = ? AND sender_id <> ?',
                [$r['id'], $uid]
            )['c'] ?? 0);
        }
        $lastAt = $r['last_at'] ?? $r['created_at'];
        $ts = strtotime((string) $lastAt) ?: time();
        $time = date('Y-m-d') === date('Y-m-d', $ts) ? date('H:i', $ts) : date('M j', $ts);
        return [
            'id'          => (int) $r['id'],
            'order'       => $r['order_code'] ?? null,
            'title'       => $r['order_title'] ?? 'Conversation',
            'amount_label'=> isset($r['amount_kobo']) ? ngn_fmt((int) $r['amount_kobo']) : null,
            'other'       => $otherName,
            'initials'    => initials($otherName),
            'tone'        => $otherTone ?: 'a2',
            'verified'    => (int) ($r['worker_verified'] ?? 0) === 1,
            'preview'     => $r['last_body'] ?? '',
            'time'        => $time,
            'unread'      => $unread,
        ];
    }
}
