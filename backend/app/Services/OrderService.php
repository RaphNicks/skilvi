<?php
declare(strict_types=1);

namespace App\Services;

use App\AppError;
use App\Core\Db;

final class OrderService
{
    public const PENDING = 'pending_payment';
    public const FUNDED = 'funded';
    public const IN_PROGRESS = 'in_progress';
    public const DELIVERED = 'completion_submitted';
    public const RELEASED = 'released';
    public const CANCELLED = 'cancelled';
    public const DISPUTED = 'disputed';

    public static function openFromProposal(array $job, array $proposal): array
    {
        $amount = (int) $proposal['bid_kobo'];
        $feePct = (int) (Db::fetch("SELECT value FROM settings WHERE key='fee_percent'")['value'] ?? 10);
        $fee = (int) round($amount * $feePct / 100);
        $code = self::nextCode();
        $now = now_iso();
        Db::run(
            'INSERT INTO orders (code, job_id, service_id, client_id, worker_id, amount_kobo, fee_kobo, status, created_at, updated_at, title, proposal_id)
             VALUES (?,?,NULL,?,?,?,?,?,?,?,?,?)',
            [$code, $job['id'], $job['client_id'], $proposal['worker_id'], $amount, $fee, self::PENDING, $now, $now, $job['title'], $proposal['id']]
        );
        return self::get($code, (int) $job['client_id']);
    }

    public static function openFromService(int $clientId, string $serviceKey, string $pkgName = ''): array
    {
        $svc = Db::fetch(
            'SELECT s.* FROM services s WHERE s.public_code = ? OR s.id = ?',
            [$serviceKey, $serviceKey]
        );
        if ($svc === null || $svc['status'] !== 'live') {
            throw new AppError('not_found', 'Service not found.', 404);
        }
        if ((int) $svc['worker_id'] === $clientId) {
            throw new AppError('forbidden', 'You cannot hire your own service.', 403);
        }
        $amount = (int) $svc['price_kobo'];
        $packages = json_decode((string) ($svc['packages_json'] ?? ''), true) ?: [];
        $title = $svc['title'];
        $matched = false;
        foreach ($packages as $p) {
            if ($pkgName !== '' && strcasecmp((string) ($p['name'] ?? ''), $pkgName) === 0) {
                $amount = (int) ($p['price_kobo'] ?? ((int) ($p['price_naira'] ?? 0) * 100));
                $title = $svc['title'] . ' — ' . $p['name'];
                $matched = true;
                break;
            }
        }
        if (!$matched && $packages) {
            $mid = $packages[min(1, count($packages) - 1)];
            $amount = (int) ($mid['price_kobo'] ?? ((int) ($mid['price_naira'] ?? 0) * 100));
            $title = $svc['title'] . ' — ' . ($mid['name'] ?? 'Standard');
        }
        if ($amount < 100000) {
            throw new AppError('invalid', 'That package has no price.', 422);
        }
        $feePct = (int) (Db::fetch("SELECT value FROM settings WHERE key='fee_percent'")['value'] ?? 10);
        $fee = (int) round($amount * $feePct / 100);
        $code = self::nextCode();
        $now = now_iso();
        Db::run(
            'INSERT INTO orders (code, job_id, service_id, client_id, worker_id, amount_kobo, fee_kobo, status, created_at, updated_at, title)
             VALUES (?,NULL,?,?,?,?,?,?,?,?,?)',
            [$code, $svc['id'], $clientId, $svc['worker_id'], $amount, $fee, self::PENDING, $now, $now, $title]
        );
        return self::get($code, $clientId);
    }

    public static function markFunded(int $orderId): array
    {
        $row = Db::fetch('SELECT * FROM orders WHERE id = ?', [$orderId]);
        if ($row === null) {
            throw new AppError('not_found', 'Order not found.', 404);
        }
        if ($row['status'] === self::FUNDED || in_array($row['status'], [self::IN_PROGRESS, self::DELIVERED, self::RELEASED], true)) {
            return self::get($row['code'], (int) $row['client_id']);
        }
        if ($row['status'] !== self::PENDING) {
            throw new AppError('conflict', 'This order cannot be funded now.', 409);
        }
        $now = now_iso();
        $amount = (int) $row['amount_kobo'];
        $own = !Db::pdo()->inTransaction();
        if ($own) {
            Db::pdo()->beginTransaction();
        }
        try {
            Db::run('UPDATE orders SET status=?, updated_at=? WHERE id=?', [self::FUNDED, $now, $orderId]);
            if (!Db::fetch('SELECT user_id FROM wallets WHERE user_id=?', [$row['worker_id']])) {
                Db::run('INSERT INTO wallets (user_id, available_kobo, pending_kobo, updated_at) VALUES (?,0,0,?)', [$row['worker_id'], $now]);
            }
            Db::run('UPDATE wallets SET pending_kobo = pending_kobo + ?, updated_at=? WHERE user_id=?', [$amount, $now, $row['worker_id']]);
            self::ledger($orderId, 'escrow', (int) $row['client_id'], 0, $amount, 'Escrow funded');
            if ($own) {
                Db::pdo()->commit();
            }
        } catch (\Throwable $e) {
            if ($own && Db::pdo()->inTransaction()) {
                Db::pdo()->rollBack();
            }
            throw $e;
        }
        self::notify((int) $row['worker_id'], 'order', 'New order ' . $row['code'], ($row['title'] ?: 'Order') . ' — escrow is holding ' . ngn_fmt($amount) . '. Mark it started when you begin.');
        self::notify((int) $row['client_id'], 'order', 'Payment verified', $row['code'] . ' is live. The worker can start.');
        return self::get($row['code'], (int) $row['client_id']);
    }

    public static function list(int $userId, string $role = 'any', string $status = ''): array
    {
        $where = ['(o.client_id = ? OR o.worker_id = ?)'];
        $bind = [$userId, $userId];
        if ($role === 'client') {
            $where = ['o.client_id = ?'];
            $bind = [$userId];
        } elseif ($role === 'worker') {
            $where = ['o.worker_id = ?'];
            $bind = [$userId];
        }
        if ($status !== '') {
            $map = [
                'active' => self::IN_PROGRESS,
                'delivered' => self::DELIVERED,
                'completed' => self::RELEASED,
                'paid' => self::FUNDED,
                'disputed' => self::DISPUTED,
            ];
            $where[] = 'o.status = ?';
            $bind[] = $map[$status] ?? $status;
        }
        $sql = implode(' AND ', $where);
        $rows = Db::fetchAll(
            "SELECT o.*,
                    cu.full_name AS client_name, wu.full_name AS worker_name,
                    wp.tone AS worker_tone, wp.rating_avg AS worker_rating, wp.public_code AS worker_code,
                    cp.tone AS client_tone
             FROM orders o
             JOIN users cu ON cu.id = o.client_id
             JOIN users wu ON wu.id = o.worker_id
             LEFT JOIN profiles wp ON wp.user_id = wu.id
             LEFT JOIN profiles cp ON cp.user_id = cu.id
             WHERE $sql
             ORDER BY o.created_at DESC",
            $bind
        );
        return array_map([self::class, 'card'], $rows);
    }

    public static function get(string $key, int $userId): array
    {
        $row = self::row($key);
        if ((int) $row['client_id'] !== $userId && (int) $row['worker_id'] !== $userId) {
            throw new AppError('forbidden', 'This order is not yours.', 403);
        }
        $card = self::card($row);
        $card['viewer'] = [
            'is_client' => (int) $row['client_id'] === $userId,
            'is_worker' => (int) $row['worker_id'] === $userId,
        ];
        $card['actions'] = self::actions($row, $userId);
        $reviewed = Db::fetch('SELECT id FROM reviews WHERE order_id = ? AND from_user = ?', [$row['id'], $userId]);
        $card['reviewed'] = (bool) $reviewed;
        $dsp = DisputeService::forOrder((int) $row['id']);
        $card['dispute'] = $dsp;
        $conv = Db::fetch('SELECT id FROM conversations WHERE order_id = ? AND dispute_id IS NULL', [$row['id']]);
        $card['conversation_id'] = $conv ? (int) $conv['id'] : null;
        return $card;
    }

    public static function start(int $workerId, string $key): array
    {
        $row = self::row($key);
        self::mustWorker($row, $workerId);
        self::mustNotDisputed($row);
        self::mustStatus($row, [self::FUNDED]);
        Db::run('UPDATE orders SET status=?, started_at=?, updated_at=? WHERE id=?', [self::IN_PROGRESS, now_iso(), now_iso(), $row['id']]);
        self::notify((int) $row['client_id'], 'order', 'Work started', $row['title'] . ' is now in progress.');
        return self::get($key, $workerId);
    }

    public static function submit(int $workerId, string $key, string $note): array
    {
        $row = self::row($key);
        self::mustWorker($row, $workerId);
        self::mustNotDisputed($row);
        self::mustStatus($row, [self::IN_PROGRESS]);
        Db::run(
            'UPDATE orders SET status=?, delivered_at=?, completion_note=?, updated_at=? WHERE id=?',
            [self::DELIVERED, now_iso(), $note !== '' ? $note : 'Work submitted for approval.', now_iso(), $row['id']]
        );
        self::notify((int) $row['client_id'], 'order', 'Delivery ready', 'Approve ' . $row['code'] . ' to release escrow, or request a revision.');
        return self::get($key, $workerId);
    }

    public static function approve(int $clientId, string $key): array
    {
        $row = self::row($key);
        self::mustClient($row, $clientId);
        self::mustNotDisputed($row);
        self::mustStatus($row, [self::DELIVERED]);
        $now = now_iso();
        $workerNet = (int) $row['amount_kobo'] - (int) $row['fee_kobo'];
        Db::pdo()->beginTransaction();
        try {
            Db::run('UPDATE orders SET status=?, released_at=?, updated_at=? WHERE id=?', [self::RELEASED, $now, $now, $row['id']]);
            if (!Db::fetch('SELECT user_id FROM wallets WHERE user_id=?', [$row['worker_id']])) {
                Db::run('INSERT INTO wallets (user_id, available_kobo, pending_kobo, updated_at) VALUES (?,0,0,?)', [$row['worker_id'], $now]);
            }
            Db::run(
                'UPDATE wallets SET available_kobo = available_kobo + ?, pending_kobo = MAX(0, pending_kobo - ?), updated_at=? WHERE user_id=?',
                [$workerNet, (int) $row['amount_kobo'], $now, $row['worker_id']]
            );
            self::ledger((int) $row['id'], 'escrow', (int) $row['client_id'], (int) $row['amount_kobo'], 0, 'Escrow released');
            self::ledger((int) $row['id'], 'worker', (int) $row['worker_id'], 0, $workerNet, 'Settlement');
            self::ledger((int) $row['id'], 'platform', null, 0, (int) $row['fee_kobo'], 'Commission ' . ((int) $row['fee_kobo'] / 100));
            Db::pdo()->commit();
        } catch (\Throwable $e) {
            Db::pdo()->rollBack();
            throw $e;
        }
        self::notify((int) $row['worker_id'], 'order', 'Payment released', ngn_fmt($workerNet) . ' is in your wallet from ' . $row['code'] . '.');
        return self::get($key, $clientId);
    }

    public static function revision(int $clientId, string $key): array
    {
        $row = self::row($key);
        self::mustClient($row, $clientId);
        self::mustNotDisputed($row);
        self::mustStatus($row, [self::DELIVERED]);
        $n = (int) $row['revision_count'] + 1;
        Db::run(
            'UPDATE orders SET status=?, revision_count=?, updated_at=? WHERE id=?',
            [self::IN_PROGRESS, $n, now_iso(), $row['id']]
        );
        self::notify((int) $row['worker_id'], 'order', 'Revision requested', 'The client asked for a revision on ' . $row['code'] . '.');
        return self::get($key, $clientId);
    }

    public static function cancel(int $clientId, string $key): array
    {
        $row = self::row($key);
        self::mustClient($row, $clientId);
        self::mustNotDisputed($row);
        self::mustStatus($row, [self::PENDING, self::FUNDED]);
        $now = now_iso();
        Db::run('UPDATE orders SET status=?, updated_at=? WHERE id=?', [self::CANCELLED, $now, $row['id']]);
        if ($row['status'] === self::FUNDED) {
            self::ledger((int) $row['id'], 'escrow', $clientId, (int) $row['amount_kobo'], 0, 'Escrow refunded (pre-start cancel)');
            Db::run(
                'UPDATE wallets SET pending_kobo = MAX(0, pending_kobo - ?), updated_at=? WHERE user_id=?',
                [(int) $row['amount_kobo'], $now, $row['worker_id']]
            );
            self::notify((int) $row['worker_id'], 'order', 'Order cancelled', $row['code'] . ' was cancelled before work started. Escrow refunded.');
        }
        return self::get($key, $clientId);
    }

    public static function review(int $fromId, string $key, int $rating, string $comment, array $tags = []): array
    {
        $row = self::row($key);
        if ((int) $row['client_id'] !== $fromId && (int) $row['worker_id'] !== $fromId) {
            throw new AppError('forbidden', 'This order is not yours.', 403);
        }
        self::mustStatus($row, [self::RELEASED]);
        if ($rating < 1 || $rating > 5) {
            throw new AppError('invalid', 'Pick a rating from 1 to 5.', 422);
        }
        if (mb_strlen(trim($comment)) < 20) {
            throw new AppError('invalid', 'Tell others a bit more — 20 characters at least.', 422);
        }
        if (Db::fetch('SELECT id FROM reviews WHERE order_id=? AND from_user=?', [$row['id'], $fromId])) {
            throw new AppError('exists', 'You already reviewed this order.', 409);
        }
        $to = ((int) $row['client_id'] === $fromId) ? (int) $row['worker_id'] : (int) $row['client_id'];
        Db::run(
            'INSERT INTO reviews (order_id, from_user, to_user, rating, comment, created_at, tags) VALUES (?,?,?,?,?,?,?)',
            [$row['id'], $fromId, $to, $rating, trim($comment), now_iso(), json_encode(array_values($tags))]
        );
        $agg = Db::fetch('SELECT AVG(rating) a, COUNT(*) c FROM reviews WHERE to_user=?', [$to]);
        Db::run('UPDATE profiles SET rating_avg=?, review_count=? WHERE user_id=?', [round((float) $agg['a'], 2), (int) $agg['c'], $to]);
        return ['ok' => true, 'order' => $row['code']];
    }

    public static function clientDashboard(int $userId): array
    {
        $orders = self::list($userId, 'client');
        $jobs = JobService::mine($userId);
        $active = array_values(array_filter($orders, static fn ($o) => in_array($o['status'], [self::FUNDED, self::IN_PROGRESS, self::DELIVERED], true)));
        $needs = array_values(array_filter($orders, static fn ($o) => $o['status'] === self::DELIVERED));
        $openJobs = array_values(array_filter($jobs, static fn ($j) => $j['status'] === 'open'));
        $spent = 0;
        foreach ($orders as $o) {
            if (in_array($o['status'], [self::FUNDED, self::IN_PROGRESS, self::DELIVERED, self::RELEASED], true)) {
                $spent += (int) $o['amount_naira'];
            }
        }
        $payRows = Db::fetchAll(
            "SELECT p.code, p.method, p.status, p.amount_kobo, p.created_at, o.code AS order_code, o.title
             FROM payments p LEFT JOIN orders o ON o.id = p.order_id
             WHERE p.user_id = ?
             ORDER BY p.created_at DESC LIMIT 40",
            [$userId]
        );
        $payments = array_map(static function ($p) {
            $st = $p['status'] === 'succeeded' ? ['In escrow / paid', 'st-royal'] : [$p['status'], 'st-gray'];
            if (($p['status'] ?? '') === 'refunded') {
                $st = ['Refunded', 'st-gray'];
            }
            return [
                'code'         => $p['code'],
                'order'        => trim(($p['order_code'] ?? '') . ' · ' . ($p['title'] ?? ''), ' ·'),
                'method'       => $p['method'] ?: '—',
                'amount_label' => ngn_fmt((int) $p['amount_kobo']),
                'date'         => date('M j, Y', strtotime($p['created_at']) ?: time()),
                'stateLabel'   => $st[0],
                'chip'         => $st[1],
            ];
        }, $payRows);
        return [
            'stats' => [
                'active_orders' => count($active),
                'needs_approval' => count($needs),
                'spent_naira' => $spent,
                'open_jobs' => count($openJobs),
                'open_proposals' => array_sum(array_column($openJobs, 'proposals')),
            ],
            'orders' => $orders,
            'jobs' => $jobs,
            'payments' => $payments,
            'recent' => array_slice($orders, 0, 5),
        ];
    }

    public static function workerDashboard(int $userId): array
    {
        $orders = self::list($userId, 'worker');
        $proposals = self::myProposals($userId);
        $wallet = WalletService::get($userId);
        $active = array_values(array_filter($orders, static fn ($o) => in_array($o['status'], [self::FUNDED, self::IN_PROGRESS, self::DELIVERED], true)));
        $delivered = array_values(array_filter($orders, static fn ($o) => $o['status'] === self::DELIVERED));
        $progress = array_values(array_filter($orders, static fn ($o) => $o['status'] === self::IN_PROGRESS));
        $pendingProps = array_values(array_filter($proposals, static fn ($p) => $p['status'] === 'sent'));
        $profile = Db::fetch('SELECT headline, bio, city, skill, rating_avg, review_count, verified FROM profiles WHERE user_id=?', [$userId]);
        $svcN = (int) (Db::fetch('SELECT COUNT(*) c FROM services WHERE worker_id=? AND status=\'live\'', [$userId])['c'] ?? 0);
        $bits = [
            trim((string) ($profile['headline'] ?? '')) !== '',
            trim((string) ($profile['bio'] ?? '')) !== '',
            trim((string) ($profile['city'] ?? '')) !== '',
            trim((string) ($profile['skill'] ?? '')) !== '',
            $svcN > 0,
            (int) ($profile['verified'] ?? 0) === 1,
        ];
        $done = count(array_filter($bits));
        $strength = (int) round(100 * $done / max(1, count($bits)));
        $needs = [];
        foreach ($delivered as $o) {
            $needs[] = [
                'title' => $o['id'] . ' · ' . $o['title'],
                'sub'   => 'Delivered — waiting on the client to approve so escrow can release.',
                'chip'  => $o['chip'],
                'label' => $o['stateLabel'],
                'href'  => 'order-detail.html?id=' . rawurlencode($o['id']),
                'tone'  => $o['client_tone'] ?? 'a2',
                'who'   => $o['client_name'],
            ];
        }
        foreach ($pendingProps as $p) {
            $needs[] = [
                'title' => $p['title'],
                'sub'   => 'Proposal ' . $p['bid_label'] . ' · waiting on the client.',
                'chip'  => $p['shortlisted'] ? 'st-royal' : 'st-amber',
                'label' => $p['shortlisted'] ? 'Shortlisted' : 'Sent',
                'href'  => 'job-detail.html?id=' . rawurlencode($p['job']),
                'tone'  => 'a1',
                'who'   => 'Job',
            ];
        }
        return [
            'wallet'    => $wallet,
            'orders'    => $orders,
            'proposals' => $proposals,
            'needs'     => array_slice($needs, 0, 5),
            'recent'    => array_slice($orders, 0, 8),
            'stats'     => [
                'available_label' => $wallet['available_label'],
                'pending_label'   => $wallet['pending_label'],
                'active_orders'   => count($active),
                'delivered'       => count($delivered),
                'in_progress'     => count($progress),
                'proposals_pending' => count($pendingProps),
                'rating'          => round((float) ($profile['rating_avg'] ?? 0), 1),
                'reviews'         => (int) ($profile['review_count'] ?? 0),
                'verified'        => (int) ($profile['verified'] ?? 0) === 1,
                'profile_strength'=> $strength,
            ],
            'profile_strength' => $strength,
        ];
    }

    public static function myProposals(int $workerId): array
    {
        $rows = Db::fetchAll(
            "SELECT p.*, j.code AS job_code, j.title, j.status AS job_status
             FROM proposals p JOIN jobs j ON j.id = p.job_id
             WHERE p.worker_id = ?
             ORDER BY p.created_at DESC",
            [$workerId]
        );
        return array_map(static function ($p) {
            return [
                'id'          => (int) $p['id'],
                'job'         => $p['job_code'],
                'title'       => $p['title'],
                'bid_label'   => ngn_fmt((int) $p['bid_kobo']),
                'days'        => (int) ($p['days'] ?? 0),
                'status'      => $p['status'],
                'shortlisted' => (int) $p['shortlisted'] === 1,
                'date'        => date('M j, Y', strtotime($p['created_at']) ?: time()),
            ];
        }, $rows);
    }

    /** @return array<string,mixed> */
    private static function row(string $key): array
    {
        $row = Db::fetch(
            "SELECT o.*,
                    cu.full_name AS client_name, wu.full_name AS worker_name,
                    wp.tone AS worker_tone, wp.rating_avg AS worker_rating, wp.public_code AS worker_code,
                    cp.tone AS client_tone, wp.verified AS worker_verified
             FROM orders o
             JOIN users cu ON cu.id = o.client_id
             JOIN users wu ON wu.id = o.worker_id
             LEFT JOIN profiles wp ON wp.user_id = wu.id
             LEFT JOIN profiles cp ON cp.user_id = cu.id
             WHERE o.code = ? OR o.id = ?",
            [$key, $key]
        );
        if ($row === null) {
            throw new AppError('not_found', 'Order not found.', 404);
        }
        return $row;
    }

    private static function card(array $o): array
    {
        $ui = self::uiStatus($o['status']);
        return [
            'id'            => $o['code'],
            'numeric_id'    => (int) $o['id'],
            'title'         => $o['title'] ?: 'Order',
            'status'        => $o['status'],
            'ui_status'     => $ui['key'],
            'stateLabel'    => $ui['label'],
            'chip'          => $ui['chip'],
            'escrow'        => $ui['escrow'],
            'amount_kobo'   => (int) $o['amount_kobo'],
            'amount_naira'  => kobo_naira((int) $o['amount_kobo']),
            'amount_label'  => ngn_fmt((int) $o['amount_kobo']),
            'fee_label'     => ngn_fmt((int) $o['fee_kobo']),
            'worker_net'    => ngn_fmt((int) $o['amount_kobo'] - (int) $o['fee_kobo']),
            'client_name'   => $o['client_name'],
            'worker_name'   => $o['worker_name'],
            'worker_code'   => $o['worker_code'] ?? null,
            'worker_tone'   => $o['worker_tone'] ?: 'a1',
            'worker_rating' => (float) ($o['worker_rating'] ?? 0),
            'client_tone'   => $o['client_tone'] ?: 'a2',
            'date'          => date('M j, Y', strtotime($o['created_at']) ?: time()),
            'started_at'    => $o['started_at'] ?? null,
            'delivered_at'  => $o['delivered_at'] ?? null,
            'released_at'   => $o['released_at'] ?? null,
            'note'          => $o['completion_note'] ?? null,
            'revisions'     => (int) ($o['revision_count'] ?? 0),
            'party'         => $o['worker_name'],
            'tone'          => $o['worker_tone'] ?: 'a1',
            'state'         => $ui['key'],
            'checkout_url'  => $o['status'] === self::PENDING ? '/checkout.html?order=' . rawurlencode($o['code']) : null,
        ];
    }

    private static function uiStatus(string $st): array
    {
        return match ($st) {
            self::PENDING   => ['key' => 'awaiting_payment', 'label' => 'Awaiting payment', 'chip' => 'st-gray', 'escrow' => 'Unpaid'],
            self::FUNDED    => ['key' => 'paid', 'label' => 'Paid · in escrow', 'chip' => 'st-royal', 'escrow' => 'Held'],
            self::IN_PROGRESS => ['key' => 'in_progress', 'label' => 'In progress', 'chip' => 'st-amber', 'escrow' => 'Held'],
            self::DELIVERED => ['key' => 'delivered', 'label' => 'Awaiting approval', 'chip' => 'st-teal', 'escrow' => 'Held'],
            self::RELEASED  => ['key' => 'completed', 'label' => 'Completed', 'chip' => 'st-green', 'escrow' => 'Released'],
            self::CANCELLED => ['key' => 'cancelled', 'label' => 'Cancelled', 'chip' => 'st-gray', 'escrow' => 'Refunded'],
            self::DISPUTED  => ['key' => 'disputed', 'label' => 'In dispute', 'chip' => 'st-red', 'escrow' => 'Frozen'],
            default         => ['key' => $st, 'label' => $st, 'chip' => 'st-gray', 'escrow' => '—'],
        };
    }

    private static function actions(array $row, int $uid): array
    {
        $st = $row['status'];
        $client = (int) $row['client_id'] === $uid;
        $worker = (int) $row['worker_id'] === $uid;
        return [
            'start'    => $worker && $st === self::FUNDED,
            'submit'   => $worker && $st === self::IN_PROGRESS,
            'approve'  => $client && $st === self::DELIVERED,
            'revision' => $client && $st === self::DELIVERED,
            'cancel'   => $client && in_array($st, [self::PENDING, self::FUNDED], true),
            'pay'      => $client && $st === self::PENDING,
            'review'   => $st === self::RELEASED,
            'dispute'  => ($client || $worker) && in_array($st, [self::FUNDED, self::IN_PROGRESS, self::DELIVERED], true),
            'message'  => $client || $worker,
        ];
    }

    private static function mustNotDisputed(array $row): void
    {
        if ($row['status'] === self::DISPUTED) {
            throw new AppError('conflict', 'This order is in dispute. Escrow is frozen until Skilvi resolves it.', 409);
        }
    }

    private static function mustClient(array $row, int $id): void
    {
        if ((int) $row['client_id'] !== $id) {
            throw new AppError('forbidden', 'Only the client can do this.', 403);
        }
    }

    private static function mustWorker(array $row, int $id): void
    {
        if ((int) $row['worker_id'] !== $id) {
            throw new AppError('forbidden', 'Only the worker can do this.', 403);
        }
    }

    private static function mustStatus(array $row, array $ok): void
    {
        if (!in_array($row['status'], $ok, true)) {
            throw new AppError('conflict', 'That is not allowed in the current order state.', 409);
        }
    }

    private static function nextCode(): string
    {
        $row = Db::fetch("SELECT code FROM orders WHERE code LIKE 'OR-%' ORDER BY id DESC LIMIT 1");
        $n = 1043;
        if ($row && preg_match('/OR-(\d+)/', (string) $row['code'], $m)) {
            $n = max($n, (int) $m[1] + 1);
        }
        return 'OR-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    private static function ledger(int $orderId, string $account, ?int $userId, int $debit, int $credit, string $memo): void
    {
        $n = (int) (Db::fetch('SELECT COALESCE(MAX(entry_no),0) n FROM ledger')['n'] ?? 0) + 1;
        Db::run(
            'INSERT INTO ledger (entry_no, account, user_id, order_id, debit_kobo, credit_kobo, memo, created_at) VALUES (?,?,?,?,?,?,?,?)',
            [$n, $account, $userId, $orderId, $debit, $credit, $memo, now_iso()]
        );
    }

    private static function notify(int $userId, string $kind, string $title, string $body): void
    {
        NotificationService::push($userId, $kind, $title, $body);
    }
}
