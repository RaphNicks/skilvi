<?php
declare(strict_types=1);

namespace App\Services;

use App\AppError;
use App\Core\Db;
use App\Models\User;

final class DisputeService
{
    public static function open(int $userId, string $orderKey, string $reason, string $description): array
    {
        $o = Db::fetch('SELECT * FROM orders WHERE code = ? OR id = ?', [$orderKey, $orderKey]);
        if ($o === null) {
            throw new AppError('not_found', 'Order not found.', 404);
        }
        if ((int) $o['client_id'] !== $userId && (int) $o['worker_id'] !== $userId) {
            throw new AppError('forbidden', 'This order is not yours.', 403);
        }
        $openable = [OrderService::FUNDED, OrderService::IN_PROGRESS, OrderService::DELIVERED];
        if (!in_array($o['status'], $openable, true)) {
            throw new AppError('conflict', 'A dispute can only be opened while escrow is holding the money.', 409);
        }
        if (Db::fetch("SELECT id FROM disputes WHERE order_id = ? AND status = 'open'", [$o['id']])) {
            throw new AppError('exists', 'This order already has an open dispute.', 409);
        }
        $reason = trim($reason);
        $description = trim($description);
        $fields = [];
        if ($reason === '') {
            $fields['reason'] = 'Pick a reason.';
        }
        if (mb_strlen($description) < 20) {
            $fields['description'] = 'Tell us what happened — 20 characters at least.';
        }
        if ($fields) {
            throw new AppError('invalid', 'Please fix the highlighted fields.', 422, $fields);
        }
        $now = now_iso();
        $code = self::nextCode();
        Db::pdo()->beginTransaction();
        try {
            Db::run('UPDATE orders SET status=?, updated_at=? WHERE id=?', [OrderService::DISPUTED, $now, $o['id']]);
            Db::run(
                'INSERT INTO conversations (order_id, client_id, worker_id, created_at, last_at) VALUES (?,?,?,?,?)',
                [$o['id'], $o['client_id'], $o['worker_id'], $now, $now]
            );
            $cid = Db::lastInsertId();
            Db::run(
                "INSERT INTO disputes (order_id, opened_by, reason, reason_type, status, created_at, updated_at, public_code, conversation_id)
                 VALUES (?,?,?,?, 'open', ?, ?, ?, ?)",
                [$o['id'], $userId, $description, $reason, $now, $now, $code, $cid]
            );
            $did = Db::lastInsertId();
            Db::run('UPDATE conversations SET dispute_id = ? WHERE id = ?', [$did, $cid]);
            Db::run(
                'INSERT INTO messages (conversation_id, sender_id, body, created_at) VALUES (?,?,?,?)',
                [$cid, $userId, $reason . "\n\n" . $description, $now]
            );
            Db::pdo()->commit();
        } catch (\Throwable $e) {
            Db::pdo()->rollBack();
            throw $e;
        }
        $other = (int) $o['client_id'] === $userId ? (int) $o['worker_id'] : (int) $o['client_id'];
        $who = User::find($userId);
        NotificationService::push(
            $other,
            'dispute',
            'Dispute opened on ' . $o['code'],
            ($who['full_name'] ?? 'The other party') . ' opened a case. Escrow is frozen.',
            'dispute-detail.html?id=' . $code
        );
        NotificationService::push(
            $userId,
            'dispute',
            'We have your dispute ' . $code,
            'Escrow stays frozen. Target resolution is 5 business days.',
            'dispute-detail.html?id=' . $code
        );
        return self::get($userId, $code);
    }

    public static function list(int $userId): array
    {
        $rows = Db::fetchAll(
            "SELECT d.*, o.code AS order_code, o.title, o.amount_kobo, o.client_id, o.worker_id,
                    ou.full_name AS opener
             FROM disputes d
             JOIN orders o ON o.id = d.order_id
             JOIN users ou ON ou.id = d.opened_by
             WHERE o.client_id = ? OR o.worker_id = ?
             ORDER BY d.id DESC",
            [$userId, $userId]
        );
        return array_map(fn ($r) => self::card($r, $userId), $rows);
    }

    public static function adminList(): array
    {
        $rows = Db::fetchAll(
            "SELECT d.*, o.code AS order_code, o.title, o.amount_kobo, o.client_id, o.worker_id,
                    ou.full_name AS opener
             FROM disputes d
             JOIN orders o ON o.id = d.order_id
             JOIN users ou ON ou.id = d.opened_by
             ORDER BY CASE d.status WHEN 'open' THEN 0 ELSE 1 END, d.id DESC"
        );
        return array_map(fn ($r) => self::card($r, 0), $rows);
    }

    public static function get(int $userId, string $key, bool $admin = false): array
    {
        $row = self::row($key);
        if (!$admin) {
            self::mustParty($row, $userId);
        }
        $card = self::card($row, $userId);
        $card['description'] = $row['reason'];
        $card['timeline'] = self::timeline($row);
        $client = User::find((int) $row['client_id']);
        $worker = User::find((int) $row['worker_id']);
        $card['parties'] = [
            'client' => ['name' => $client['full_name'] ?? '', 'initials' => initials($client['full_name'] ?? '?'), 'tone' => 'a2'],
            'worker' => ['name' => $worker['full_name'] ?? '', 'initials' => initials($worker['full_name'] ?? '?'), 'tone' => 'a1'],
        ];
        $cid = (int) ($row['conversation_id'] ?? 0);
        $msgs = [];
        if ($cid) {
            foreach (Db::fetchAll(
                'SELECT m.*, u.full_name FROM messages m JOIN users u ON u.id = m.sender_id
                 WHERE m.conversation_id = ? ORDER BY m.id ASC',
                [$cid]
            ) as $m) {
                $msgs[] = [
                    'id'   => (int) $m['id'],
                    'mine' => (int) $m['sender_id'] === $userId,
                    'name' => $m['full_name'],
                    'body' => $m['body'],
                    'time' => date('M j, H:i', strtotime($m['created_at']) ?: time()),
                ];
            }
        }
        $card['messages'] = $msgs;
        $card['conversation_id'] = $cid ?: null;
        $card['can_reply'] = $row['status'] === 'open';
        $card['can_resolve'] = $admin && $row['status'] === 'open';
        return $card;
    }

    public static function reply(int $userId, string $key, string $body): array
    {
        $row = self::row($key);
        self::mustParty($row, $userId);
        if ($row['status'] !== 'open') {
            throw new AppError('conflict', 'This case is closed.', 409);
        }
        $body = trim($body);
        if (mb_strlen($body) < 2) {
            throw new AppError('invalid', 'Write a reply.', 422);
        }
        $cid = (int) ($row['conversation_id'] ?? 0);
        if (!$cid) {
            $c = MessageService::ensureForOrder((string) $row['order_code']);
            $cid = (int) $c['id'];
            Db::run('UPDATE disputes SET conversation_id = ? WHERE id = ?', [$cid, $row['id']]);
        }
        $now = now_iso();
        Db::run(
            'INSERT INTO messages (conversation_id, sender_id, body, created_at) VALUES (?,?,?,?)',
            [$cid, $userId, $body, $now]
        );
        Db::run('UPDATE conversations SET last_at = ? WHERE id = ?', [$now, $cid]);
        $other = (int) $row['client_id'] === $userId ? (int) $row['worker_id'] : (int) $row['client_id'];
        NotificationService::push(
            $other,
            'dispute',
            'New reply on ' . $row['public_code'],
            mb_substr($body, 0, 120),
            'dispute-detail.html?id=' . $row['public_code']
        );
        return self::get($userId, (string) $row['public_code']);
    }

    public static function resolve(int $adminId, string $key, string $decision, int $workerPct, string $note): array
    {
        $row = self::row($key);
        if ($row['status'] !== 'open') {
            throw new AppError('conflict', 'This case is already closed.', 409);
        }
        $decision = strtolower(trim($decision));
        if (!in_array($decision, ['release', 'refund', 'split'], true)) {
            throw new AppError('invalid', 'Choose release, refund, or split.', 422);
        }
        if ($decision === 'split' && ($workerPct < 0 || $workerPct > 100)) {
            throw new AppError('invalid', 'Split needs a worker share between 0 and 100.', 422);
        }
        if ($decision === 'release') {
            $workerPct = 100;
        }
        if ($decision === 'refund') {
            $workerPct = 0;
        }
        $note = trim($note);
        if (mb_strlen($note) < 10) {
            throw new AppError('invalid', 'Write a short reason both parties will see.', 422, ['note' => '10 characters at least.']);
        }
        $amount = (int) $row['amount_kobo'];
        $feePct = (int) (Db::fetch("SELECT value FROM settings WHERE key='fee_percent'")['value'] ?? 10);
        $workerGross = (int) round($amount * $workerPct / 100);
        $fee = $workerGross > 0 ? (int) round($workerGross * $feePct / 100) : 0;
        $workerNet = $workerGross - $fee;
        $now = now_iso();
        $orderStatus = $workerNet > 0 ? OrderService::RELEASED : OrderService::CANCELLED;
        Db::pdo()->beginTransaction();
        try {
            Db::run(
                "UPDATE disputes SET status='resolved', decision=?, worker_pct=?, resolution_note=?, resolved_at=?, updated_at=? WHERE id=?",
                [$decision, $workerPct, $note, $now, $now, $row['id']]
            );
            Db::run(
                'UPDATE orders SET status=?, released_at=?, updated_at=? WHERE id=?',
                [$orderStatus, $workerNet > 0 ? $now : null, $now, $row['order_id']]
            );
            WalletService::ensure((int) $row['worker_id']);
            Db::run(
                'UPDATE wallets SET pending_kobo = MAX(0, pending_kobo - ?), available_kobo = available_kobo + ?, updated_at=? WHERE user_id=?',
                [$amount, $workerNet, $now, $row['worker_id']]
            );
            self::ledger((int) $row['order_id'], 'escrow', (int) $row['client_id'], $amount, 0, 'Dispute ' . $row['public_code'] . ' closed');
            if ($workerNet > 0) {
                self::ledger((int) $row['order_id'], 'worker', (int) $row['worker_id'], 0, $workerNet, 'Dispute settlement ' . $workerPct . '%');
            }
            if ($fee > 0) {
                self::ledger((int) $row['order_id'], 'platform', null, 0, $fee, 'Commission (dispute)');
            }
            Db::run(
                'INSERT INTO admin_audit (admin_id, action, target, meta, created_at) VALUES (?,?,?,?,?)',
                [$adminId, 'dispute.resolve', 'dispute:' . $row['id'], json_encode(['decision' => $decision, 'worker_pct' => $workerPct]), $now]
            );
            Db::pdo()->commit();
        } catch (\Throwable $e) {
            Db::pdo()->rollBack();
            throw $e;
        }
        $summary = $decision === 'refund'
            ? 'Full refund to the client. Escrow released from hold.'
            : ($decision === 'release'
                ? 'Full release to the worker.'
                : $workerPct . '% to the worker, remainder refunded.');
        foreach ([(int) $row['client_id'], (int) $row['worker_id']] as $uid) {
            NotificationService::push(
                $uid,
                'dispute',
                $row['public_code'] . ' resolved',
                $summary . ' ' . $note,
                'dispute-detail.html?id=' . $row['public_code']
            );
        }
        return self::get($adminId, (string) $row['public_code'], true);
    }

    public static function forOrder(int $orderId): ?array
    {
        $row = Db::fetch('SELECT * FROM disputes WHERE order_id = ? ORDER BY id DESC LIMIT 1', [$orderId]);
        return $row ? ['id' => $row['public_code'] ?: ('DSP-' . $row['id']), 'status' => $row['status']] : null;
    }

    /** @return array<string,mixed> */
    private static function row(string $key): array
    {
        $row = Db::fetch(
            "SELECT d.*, o.code AS order_code, o.title, o.amount_kobo, o.fee_kobo, o.client_id, o.worker_id,
                    ou.full_name AS opener
             FROM disputes d
             JOIN orders o ON o.id = d.order_id
             JOIN users ou ON ou.id = d.opened_by
             WHERE d.public_code = ? OR d.id = ?",
            [$key, $key]
        );
        if ($row === null) {
            throw new AppError('not_found', 'Dispute not found.', 404);
        }
        return $row;
    }

    private static function mustParty(array $row, int $uid): void
    {
        if ((int) $row['client_id'] !== $uid && (int) $row['worker_id'] !== $uid) {
            throw new AppError('forbidden', 'This case is not yours.', 403);
        }
    }

    /** @param array<string,mixed> $d */
    private static function card(array $d, int $uid): array
    {
        $open = $d['status'] === 'open';
        $opened = strtotime($d['created_at']) ?: time();
        $slaDays = 5 - (int) floor((time() - $opened) / 86400);
        return [
            'id'            => $d['public_code'] ?: ('DSP-' . $d['id']),
            'numeric_id'    => (int) $d['id'],
            'order'         => $d['order_code'],
            'title'         => $d['title'],
            'amount_label'  => ngn_fmt((int) $d['amount_kobo']),
            'reason'        => $d['reason_type'] ?: 'Dispute',
            'status'        => $d['status'],
            'stateLabel'    => $open ? 'Under review' : 'Closed',
            'chip'          => $open ? 'st-amber' : 'st-green',
            'opener'        => $d['opener'] ?? '',
            'mine'          => $uid && (int) $d['opened_by'] === $uid,
            'date'          => date('M j, Y', $opened),
            'sla'           => $open ? max(0, $slaDays) . ' days left' : null,
            'decision'      => $d['decision'] ?? null,
            'worker_pct'    => $d['worker_pct'] !== null ? (int) $d['worker_pct'] : null,
            'resolution'    => $d['resolution_note'] ?? null,
        ];
    }

    /** @param array<string,mixed> $d */
    private static function timeline(array $d): array
    {
        $items = [
            ['done' => true, 'title' => 'Dispute opened', 'sub' => date('M j, Y', strtotime($d['created_at']) ?: time()) . ' · ' . ($d['reason_type'] ?: 'case opened') . ' · escrow frozen'],
            ['done' => true, 'title' => 'Acknowledged', 'sub' => 'Both parties notified. Escrow cannot be released while this is open.'],
        ];
        if ($d['status'] === 'open') {
            $items[] = ['done' => false, 'current' => true, 'title' => 'Under review', 'sub' => 'Target: 5 business days'];
            $items[] = ['done' => false, 'title' => 'Resolution', 'sub' => 'Outcome + reasoning shared with both parties'];
        } else {
            $items[] = ['done' => true, 'title' => 'Resolved', 'sub' => ($d['decision'] ?? '') . ($d['worker_pct'] !== null ? ' · worker ' . $d['worker_pct'] . '%' : '')];
        }
        return $items;
    }

    private static function nextCode(): string
    {
        $n = 2031;
        $row = Db::fetch("SELECT public_code FROM disputes WHERE public_code LIKE 'DSP-%' ORDER BY id DESC LIMIT 1");
        if ($row && preg_match('/DSP-(\d+)/', (string) $row['public_code'], $m)) {
            $n = max($n, (int) $m[1] + 1);
        }
        return 'DSP-' . $n;
    }

    private static function ledger(int $orderId, string $account, ?int $userId, int $debit, int $credit, string $memo): void
    {
        $n = (int) (Db::fetch('SELECT COALESCE(MAX(entry_no),0) n FROM ledger')['n'] ?? 0) + 1;
        Db::run(
            'INSERT INTO ledger (entry_no, account, user_id, order_id, debit_kobo, credit_kobo, memo, created_at) VALUES (?,?,?,?,?,?,?,?)',
            [$n, $account, $userId, $orderId, $debit, $credit, $memo, now_iso()]
        );
    }
}
