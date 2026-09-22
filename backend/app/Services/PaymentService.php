<?php
declare(strict_types=1);

namespace App\Services;

use App\AppError;
use App\Core\Config;
use App\Core\Db;
use App\Core\RateLimit;

final class PaymentService
{
    public static function initiate(int $userId, array $in): array
    {
        $rl = RateLimit::hit('pay:' . $userId, 8, 60);
        if (!$rl['ok']) {
            throw new AppError('rate', 'Slow down — wait a moment before trying another payment.', 429, ['retry' => $rl['wait']]);
        }
        $idem = \App\Core\Idempotency::header();
        if ($idem !== '') {
            $cached = \App\Core\Idempotency::get($userId, $idem);
            if ($cached !== null) {
                return $cached;
            }
        }
        $purpose = (string) ($in['purpose'] ?? 'order');
        $method = (string) ($in['method'] ?? 'transfer');
        if (!in_array($method, ['transfer', 'card', 'ussd', 'mobile_money'], true)) {
            $method = 'transfer';
        }
        if (!in_array($purpose, ['order', 'verification', 'promotion'], true)) {
            throw new AppError('invalid', 'Unknown payment purpose.', 422);
        }

        $orderId = null;
        $amount = 0;
        $meta = [];
        if ($purpose === 'order') {
            $key = (string) ($in['order_id'] ?? $in['order'] ?? '');
            $order = Db::fetch('SELECT * FROM orders WHERE code = ? OR id = ?', [$key, $key]);
            if ($order === null || (int) $order['client_id'] !== $userId) {
                throw new AppError('not_found', 'Order not found.', 404);
            }
            if ($order['status'] !== OrderService::PENDING) {
                throw new AppError('conflict', 'This order is not waiting for payment.', 409);
            }
            $amount = (int) $order['amount_kobo'];
            $orderId = (int) $order['id'];
            $existing = Db::fetch(
                "SELECT * FROM payments WHERE order_id = ? AND purpose = 'order' AND status = 'initiated' ORDER BY id DESC LIMIT 1",
                [$orderId]
            );
            if ($existing) {
                $out = self::payload($existing, $order);
                if ($idem !== '') {
                    \App\Core\Idempotency::put($userId, $idem, $out);
                }
                return $out;
            }
        } elseif ($purpose === 'verification') {
            $amount = (int) Config::get('fees.verification_kobo', 500000);
            $st = TrustService::status($userId);
            if ($st['status'] === 'approved') {
                throw new AppError('exists', 'This account is already verified.', 409);
            }
        } else {
            $plan = (string) ($in['plan'] ?? 'search');
            $tiers = TrustService::promoTiers();
            if (!isset($tiers[$plan])) {
                throw new AppError('invalid', 'Pick a promotion plan.', 422);
            }
            $amount = $tiers[$plan]['amount_kobo'];
            $meta['plan'] = $plan;
        }

        $code = self::nextCode();
        $now = now_iso();
        $ref = $code;
        Db::run(
            "INSERT INTO payments (code, user_id, order_id, purpose, provider, provider_ref, amount_kobo, method, status, raw_json, created_at, updated_at)
             VALUES (?,?,?,?, 'paystack', ?, ?, ?, 'initiated', ?, ?, ?)",
            [$code, $userId, $orderId, $purpose, $ref, $amount, $method, json_encode($meta), $now, $now]
        );
        $row = Db::fetch('SELECT * FROM payments WHERE code = ?', [$code]);
        $order = $orderId ? Db::fetch('SELECT * FROM orders WHERE id = ?', [$orderId]) : null;
        $out = self::payload($row, $order);
        if ($idem !== '') {
            \App\Core\Idempotency::put($userId, $idem, $out);
        }
        return $out;
    }

    public static function status(int $userId, string $key): array
    {
        $row = self::row($key);
        if ((int) $row['user_id'] !== $userId) {
            throw new AppError('forbidden', 'This payment is not yours.', 403);
        }
        $order = $row['order_id'] ? Db::fetch('SELECT * FROM orders WHERE id = ?', [$row['order_id']]) : null;
        return self::payload($row, $order);
    }

    public static function simulate(int $userId, string $key, string $result = 'success'): array
    {
        if (!Config::isDev()) {
            throw new AppError('forbidden', 'Simulate is only available in development.', 403);
        }
        $row = self::row($key);
        if ((int) $row['user_id'] !== $userId) {
            throw new AppError('forbidden', 'This payment is not yours.', 403);
        }
        $event = $result === 'fail' ? 'charge.failed' : 'charge.success';
        $body = json_encode([
            'event' => $event,
            'data'  => [
                'reference' => $row['provider_ref'] ?: $row['code'],
                'amount'    => (int) $row['amount_kobo'],
                'status'    => $result === 'fail' ? 'failed' : 'success',
                'channel'   => $row['method'],
            ],
        ]);
        $sig = hash_hmac('sha512', $body, (string) Config::get('paystack.webhook'));
        return self::webhookPaystack($body, $sig);
    }

    public static function webhookPaystack(string $raw, ?string $signature): array
    {
        $secret = (string) Config::get('paystack.webhook');
        $expected = hash_hmac('sha512', $raw, $secret);
        if (!$signature || !hash_equals($expected, $signature)) {
            throw new AppError('forbidden', 'Invalid webhook signature.', 403);
        }
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            throw new AppError('invalid', 'Bad webhook body.', 400);
        }
        $event = (string) ($payload['event'] ?? '');
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $ref = (string) ($data['reference'] ?? $data['ref'] ?? '');
        $row = Db::fetch('SELECT * FROM payments WHERE provider_ref = ? OR code = ?', [$ref, $ref]);
        if ($row === null) {
            throw new AppError('not_found', 'Unknown payment reference.', 404);
        }
        if ($row['status'] === 'succeeded' || $row['status'] === 'failed') {
            return ['id' => $row['code'], 'status' => $row['status'], 'replayed' => true];
        }
        $ok = str_contains($event, 'success') || ($data['status'] ?? '') === 'success';
        if (!$ok) {
            Db::run("UPDATE payments SET status='failed', raw_json=?, updated_at=? WHERE id=?", [$raw, now_iso(), $row['id']]);
            return ['id' => $row['code'], 'status' => 'failed', 'replayed' => false];
        }
        $claimed = (int) ($data['amount'] ?? $row['amount_kobo']);
        if ($claimed !== (int) $row['amount_kobo']) {
            throw new AppError('conflict', 'Amount does not match the initiated payment.', 409);
        }
        self::succeed($row, $raw);
        return ['id' => $row['code'], 'status' => 'succeeded', 'replayed' => false];
    }

    public static function webhookFlutterwave(string $raw, ?string $signature): array
    {
        // Same contract, second provider — reuse HMAC secret until live keys exist.
        return self::webhookPaystack($raw, $signature);
    }

    /** @param array<string,mixed> $row */
    private static function succeed(array $row, string $raw): void
    {
        $now = now_iso();
        Db::pdo()->beginTransaction();
        try {
            Db::run(
                "UPDATE payments SET status='succeeded', raw_json=?, updated_at=? WHERE id=? AND status='initiated'",
                [$raw, $now, $row['id']]
            );
            $purpose = $row['purpose'];
            if ($purpose === 'order' && $row['order_id']) {
                OrderService::markFunded((int) $row['order_id']);
            } elseif ($purpose === 'verification') {
                TrustService::markPaid((int) $row['user_id'], (int) $row['id']);
            } elseif ($purpose === 'promotion') {
                $meta = json_decode((string) $row['raw_json'], true) ?: [];
                $plan = (string) ($meta['plan'] ?? 'search');
                TrustService::activatePromo((int) $row['user_id'], $plan, (int) $row['amount_kobo'], (int) $row['id']);
            }
            Db::pdo()->commit();
        } catch (\Throwable $e) {
            Db::pdo()->rollBack();
            throw $e;
        }
    }

    /** @return array<string,mixed> */
    private static function row(string $key): array
    {
        $row = Db::fetch('SELECT * FROM payments WHERE code = ? OR id = ? OR provider_ref = ?', [$key, $key, $key]);
        if ($row === null) {
            throw new AppError('not_found', 'Payment not found.', 404);
        }
        return $row;
    }

    /** @param array<string,mixed>|null $order */
    private static function payload(?array $row, ?array $order): array
    {
        if ($row === null) {
            throw new AppError('not_found', 'Payment not found.', 404);
        }
        $naira = kobo_naira((int) $row['amount_kobo']);
        $va = [
            'bank'            => 'GTBank',
            'account_name'    => 'Skilvi Technologies Ltd',
            'account_number'  => str_pad((string) (1000000000 + (int) $row['id']), 10, '0', STR_PAD_LEFT),
            'reference'       => $row['code'],
            'amount_label'    => ngn_fmt((int) $row['amount_kobo']),
        ];
        $out = [
            'id'              => $row['code'],
            'numeric_id'      => (int) $row['id'],
            'purpose'         => $row['purpose'],
            'method'          => $row['method'],
            'status'          => $row['status'],
            'amount_kobo'     => (int) $row['amount_kobo'],
            'amount_naira'    => $naira,
            'amount_label'    => ngn_fmt((int) $row['amount_kobo']),
            'provider'        => $row['provider'] ?: 'paystack',
            'provider_ref'    => $row['provider_ref'],
            'virtual_account' => $row['method'] === 'card' ? null : $va,
            'dev_simulate'    => Config::isDev() && $row['status'] === 'initiated',
            'order'           => $order ? [
                'id'     => $order['code'],
                'title'  => $order['title'],
                'status' => $order['status'],
            ] : null,
            'success_url'     => '/payment-success.html?id=' . rawurlencode($row['code']),
        ];
        return $out;
    }

    private static function nextCode(): string
    {
        $row = Db::fetch("SELECT code FROM payments WHERE code LIKE 'PAY-%' ORDER BY id DESC LIMIT 1");
        $n = 5521;
        if ($row && preg_match('/PAY-(\d+)/', (string) $row['code'], $m)) {
            $n = max($n, (int) $m[1] + 1);
        }
        return 'PAY-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
