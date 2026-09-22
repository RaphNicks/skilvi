<?php
declare(strict_types=1);

namespace App\Services;

use App\AppError;
use App\Core\Config;
use App\Core\Db;
use App\Core\Session;
use App\Models\User;

final class WalletService
{
    public static function get(int $userId): array
    {
        self::ensure($userId);
        $w = Db::fetch('SELECT * FROM wallets WHERE user_id = ?', [$userId]);
        $life = (int) (Db::fetch(
            "SELECT COALESCE(SUM(credit_kobo),0) c FROM ledger WHERE user_id = ? AND account = 'worker'",
            [$userId]
        )['c'] ?? 0);
        $min = (int) Config::get('fees.min_withdrawal_kobo', 500000);
        $banks = [];
        foreach (Db::fetchAll(
            'SELECT bank_name, account_number, account_name FROM withdrawals WHERE user_id = ? ORDER BY id DESC LIMIT 8',
            [$userId]
        ) as $b) {
            $masked = \App\Core\Crypto::maskAccount((string) $b['account_number']);
            $key = $b['bank_name'] . '|' . $masked;
            if (!isset($banks[$key])) {
                $banks[$key] = [
                    'bank_name'    => $b['bank_name'],
                    'account_name' => $b['account_name'],
                    'masked'       => $masked,
                    'label'        => $b['bank_name'] . ' ' . $masked . ' — ' . $b['account_name'],
                ];
            }
        }
        return [
            'available_kobo'   => (int) $w['available_kobo'],
            'pending_kobo'     => (int) $w['pending_kobo'],
            'lifetime_kobo'    => $life,
            'available_naira'  => kobo_naira((int) $w['available_kobo']),
            'pending_naira'    => kobo_naira((int) $w['pending_kobo']),
            'lifetime_naira'   => kobo_naira($life),
            'available_label'  => ngn_fmt((int) $w['available_kobo']),
            'pending_label'    => ngn_fmt((int) $w['pending_kobo']),
            'lifetime_label'   => ngn_fmt($life),
            'min_naira'        => kobo_naira($min),
            'min_label'        => ngn_fmt($min),
            'banks'            => array_values($banks),
            'bank_options'     => [
                'Access Bank', 'GTBank', 'First Bank', 'UBA', 'Zenith Bank', 'Fidelity Bank',
                'Sterling Bank', 'Wema Bank', 'Union Bank', 'Polaris Bank', 'FCMB', 'Stanbic IBTC',
                'Keystone Bank', 'Unity Bank', 'Jaiz Bank', 'Providus Bank', 'Globus Bank',
            ],
        ];
    }

    public static function transactions(int $userId): array
    {
        $rows = Db::fetchAll(
            'SELECT * FROM ledger WHERE user_id = ? ORDER BY id DESC LIMIT 50',
            [$userId]
        );
        $bal = (int) (Db::fetch('SELECT available_kobo FROM wallets WHERE user_id = ?', [$userId])['available_kobo'] ?? 0);
        $out = [];
        $running = $bal;
        foreach ($rows as $r) {
            $credit = (int) $r['credit_kobo'];
            $debit = (int) $r['debit_kobo'];
            $signed = $credit - $debit;
            $type = 'settlement';
            if (str_contains(strtolower((string) $r['memo']), 'commission')) {
                $type = 'commission';
            } elseif (str_contains(strtolower((string) $r['account'] . $r['memo']), 'withdraw')) {
                $type = 'withdrawal';
            }
            $order = $r['order_id'] ? Db::fetch('SELECT code FROM orders WHERE id=?', [$r['order_id']]) : null;
            $out[] = [
                'date'         => date('M j, Y', strtotime($r['created_at']) ?: time()),
                'label'        => $r['memo'] ?: $r['account'],
                'type'         => $type,
                'ref'          => $order['code'] ?? ('#' . $r['entry_no']),
                'amount_kobo'  => $signed,
                'amount_naira' => kobo_naira(abs($signed)),
                'amount_label' => ($signed < 0 ? '−' : '+') . ngn_fmt(abs($signed)),
                'bal_label'    => ngn_fmt($running),
            ];
            $running -= $signed;
        }
        return $out;
    }

    public static function start(int $userId, array $in, string $ip): array
    {
        $user = User::find($userId);
        if ($user === null) {
            throw new AppError('unauth', 'Log in to continue.', 401);
        }
        $naira = (int) preg_replace('/\D/', '', (string) ($in['amount_naira'] ?? $in['amount'] ?? '0'));
        $kobo = $naira * 100;
        $min = (int) Config::get('fees.min_withdrawal_kobo', 500000);
        $w = self::get($userId);
        $fields = [];
        if ($kobo < $min) {
            $fields['amount'] = 'Minimum withdrawal is ' . ngn_fmt($min) . '.';
        }
        if ($kobo > (int) $w['available_kobo']) {
            $fields['amount'] = 'That is more than your available balance.';
        }
        $bank = trim((string) ($in['bank_name'] ?? ''));
        $acct = preg_replace('/\D/', '', (string) ($in['account_number'] ?? '')) ?? '';
        $name = trim((string) ($in['account_name'] ?? ''));
        if ($bank === '') {
            $fields['bank_name'] = 'Pick a bank.';
        }
        if (strlen($acct) !== 10) {
            $fields['account_number'] = 'Use a 10-digit NUBAN.';
        }
        if (mb_strlen($name) < 3) {
            $fields['account_name'] = 'Account name as it appears at the bank.';
        }
        if ($fields) {
            throw new AppError('invalid', 'Please fix the highlighted fields.', 422, $fields);
        }
        Session::set('pending_withdraw', [
            'user_id'         => $userId,
            'amount_kobo'     => $kobo,
            'bank_name'       => $bank,
            'account_number'  => $acct,
            'account_name'    => $name,
            'at'              => time(),
        ]);
        Session::claim($user['phone'], 'withdraw');
        return OtpService::issue($user['phone'], 'withdraw', $ip);
    }

    public static function confirm(int $userId, string $code): array
    {
        $pending = Session::get('pending_withdraw');
        if (!is_array($pending) || (int) $pending['user_id'] !== $userId || (time() - (int) $pending['at']) > 900) {
            throw new AppError('otp_session', 'Start the withdrawal again.', 401);
        }
        $user = User::find($userId);
        $claim = Session::get('otp_claim');
        if (!is_array($claim) || ($claim['purpose'] ?? '') !== 'withdraw') {
            throw new AppError('otp_session', 'Start the withdrawal again.', 401);
        }
        OtpService::verify((string) $user['phone'], 'withdraw', $code);
        Session::clearClaim();
        Session::remove('pending_withdraw');

        $kobo = (int) $pending['amount_kobo'];
        self::ensure($userId);
        $w = Db::fetch('SELECT available_kobo FROM wallets WHERE user_id = ?', [$userId]);
        if ((int) $w['available_kobo'] < $kobo) {
            throw new AppError('conflict', 'Balance changed. Check your available funds.', 409);
        }
        $codeWd = self::nextWd();
        $now = now_iso();
        Db::pdo()->beginTransaction();
        try {
            Db::run(
                'UPDATE wallets SET available_kobo = available_kobo - ?, updated_at = ? WHERE user_id = ?',
                [$kobo, $now, $userId]
            );
            Db::run(
                'INSERT INTO withdrawals (user_id, amount_kobo, fee_kobo, bank_name, account_number, account_name, status, created_at, updated_at)
                 VALUES (?,?,0,?,?,?,\'pending\',?,?)',
                [$userId, $kobo, $pending['bank_name'], \App\Core\Crypto::encrypt((string) $pending['account_number']), $pending['account_name'], $now, $now]
            );
            $id = Db::lastInsertId();
            self::ledger($userId, null, $kobo, 0, 'Withdrawal requested ' . $codeWd);
            Db::pdo()->commit();
        } catch (\Throwable $e) {
            Db::pdo()->rollBack();
            throw $e;
        }
        return self::one($id, $userId);
    }

    public static function list(int $userId): array
    {
        $rows = Db::fetchAll('SELECT * FROM withdrawals WHERE user_id = ? ORDER BY id DESC', [$userId]);
        return array_map([self::class, 'card'], $rows);
    }

    public static function adminAction(int $adminId, string $key, string $action): array
    {
        $id = (int) preg_replace('/^WD-/i', '', $key);
        $row = Db::fetch('SELECT * FROM withdrawals WHERE id = ?', [$id]);
        if ($row === null) {
            throw new AppError('not_found', 'Withdrawal not found.', 404);
        }
        $now = now_iso();
        if ($action === 'reject') {
            if ($row['status'] !== 'pending' && $row['status'] !== 'approved') {
                throw new AppError('conflict', 'That withdrawal cannot be rejected.', 409);
            }
            Db::run("UPDATE withdrawals SET status='rejected', updated_at=? WHERE id=?", [$now, $row['id']]);
            Db::run(
                'UPDATE wallets SET available_kobo = available_kobo + ?, updated_at=? WHERE user_id=?',
                [$row['amount_kobo'], $now, $row['user_id']]
            );
            self::ledger((int) $row['user_id'], null, 0, (int) $row['amount_kobo'], 'Withdrawal rejected WD-' . $row['id']);
        } elseif ($action === 'approve') {
            if ($row['status'] !== 'pending') {
                throw new AppError('conflict', 'Only pending withdrawals can be approved.', 409);
            }
            Db::run("UPDATE withdrawals SET status='approved', updated_at=? WHERE id=?", [$now, $row['id']]);
        } elseif ($action === 'paid') {
            if (!in_array($row['status'], ['pending', 'approved'], true)) {
                throw new AppError('conflict', 'Already settled.', 409);
            }
            Db::run("UPDATE withdrawals SET status='paid', updated_at=? WHERE id=?", [$now, $row['id']]);
        } else {
            throw new AppError('invalid', 'Unknown action.', 422);
        }
        Db::run(
            'INSERT INTO admin_audit (admin_id, action, target, meta, created_at) VALUES (?,?,?,?,?)',
            [$adminId, 'withdrawal.' . $action, 'withdrawal:' . $row['id'], json_encode(['status' => $action]), $now]
        );
        return self::card(Db::fetch('SELECT * FROM withdrawals WHERE id=?', [$row['id']]));
    }

    public static function one(int $id, int $userId): array
    {
        $row = Db::fetch('SELECT * FROM withdrawals WHERE id = ? AND user_id = ?', [$id, $userId]);
        if ($row === null) {
            throw new AppError('not_found', 'Withdrawal not found.', 404);
        }
        return self::card($row);
    }

    private static function card(array $w): array
    {
        $st = $w['status'];
        $label = match ($st) {
            'paid' => 'Paid',
            'rejected' => 'Rejected',
            'approved' => 'Approved',
            default => 'Pending',
        };
        $chip = match ($st) {
            'paid' => 'st-green',
            'rejected' => 'st-red',
            'approved' => 'st-royal',
            default => 'st-amber',
        };
        return [
            'id'            => 'WD-' . $w['id'],
            'numeric_id'    => (int) $w['id'],
            'amount_label'  => ngn_fmt((int) $w['amount_kobo']),
            'amount_naira'  => kobo_naira((int) $w['amount_kobo']),
            'bank'          => $w['bank_name'] . ' ' . \App\Core\Crypto::maskAccount((string) $w['account_number']),
            'account_name'  => $w['account_name'],
            'status'        => $st,
            'stateLabel'    => $label,
            'chip'          => $chip,
            'date'          => date('M j, Y', strtotime($w['created_at']) ?: time()),
        ];
    }

    public static function adminList(): array
    {
        $rows = Db::fetchAll(
            'SELECT w.*, u.full_name FROM withdrawals w JOIN users u ON u.id = w.user_id ORDER BY w.id DESC'
        );
        return array_map(static function ($r) {
            $c = self::card($r);
            $c['worker'] = $r['full_name'];
            return $c;
        }, $rows);
    }

    /** Credit seed / historical orders that never wrote the ledger. Idempotent. */
    public static function syncFromOrders(): void
    {
        foreach (Db::fetchAll("SELECT * FROM orders WHERE status = 'released'") as $o) {
            if (Db::fetch("SELECT id FROM ledger WHERE order_id = ? AND account = 'worker' AND credit_kobo > 0", [$o['id']])) {
                continue;
            }
            self::ensure((int) $o['worker_id']);
            $net = (int) $o['amount_kobo'] - (int) $o['fee_kobo'];
            Db::run(
                'UPDATE wallets SET available_kobo = available_kobo + ?, updated_at = ? WHERE user_id = ?',
                [$net, now_iso(), $o['worker_id']]
            );
            self::ledger((int) $o['worker_id'], (int) $o['id'], 0, $net, 'Settlement ' . $o['code']);
        }
        foreach (Db::fetchAll("SELECT * FROM orders WHERE status IN ('funded','in_progress','completion_submitted')") as $o) {
            if (Db::fetch("SELECT id FROM ledger WHERE order_id = ? AND account = 'escrow' AND credit_kobo > 0", [$o['id']])) {
                continue;
            }
            self::ensure((int) $o['worker_id']);
            Db::run(
                'UPDATE wallets SET pending_kobo = pending_kobo + ?, updated_at = ? WHERE user_id = ?',
                [(int) $o['amount_kobo'], now_iso(), $o['worker_id']]
            );
            $n = (int) (Db::fetch('SELECT COALESCE(MAX(entry_no),0) n FROM ledger')['n'] ?? 0) + 1;
            Db::run(
                'INSERT INTO ledger (entry_no, account, user_id, order_id, debit_kobo, credit_kobo, memo, created_at) VALUES (?,?,?,?,?,?,?,?)',
                [$n, 'escrow', (int) $o['client_id'], (int) $o['id'], 0, (int) $o['amount_kobo'], 'Escrow held ' . $o['code'], now_iso()]
            );
        }
    }

    public static function ensure(int $userId): void
    {
        if (!Db::fetch('SELECT user_id FROM wallets WHERE user_id=?', [$userId])) {
            Db::run('INSERT INTO wallets (user_id, available_kobo, pending_kobo, updated_at) VALUES (?,0,0,?)', [$userId, now_iso()]);
        }
    }

    private static function mask(string $acct): string
    {
        $d = preg_replace('/\D/', '', $acct) ?? $acct;
        if (strlen($d) < 4) {
            return '····';
        }
        return '····' . substr($d, -4);
    }

    private static function nextWd(): string
    {
        $n = (int) (Db::fetch('SELECT COALESCE(MAX(id),0) n FROM withdrawals')['n'] ?? 0) + 1;
        return 'WD-' . $n;
    }

    private static function ledger(int $userId, ?int $orderId, int $debit, int $credit, string $memo): void
    {
        $n = (int) (Db::fetch('SELECT COALESCE(MAX(entry_no),0) n FROM ledger')['n'] ?? 0) + 1;
        Db::run(
            'INSERT INTO ledger (entry_no, account, user_id, order_id, debit_kobo, credit_kobo, memo, created_at) VALUES (?,?,?,?,?,?,?,?)',
            [$n, 'worker', $userId, $orderId, $debit, $credit, $memo, now_iso()]
        );
    }
}
