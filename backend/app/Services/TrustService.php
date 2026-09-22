<?php
declare(strict_types=1);

namespace App\Services;

use App\AppError;
use App\Core\Config;
use App\Core\Db;

final class TrustService
{
    public static function promoTiers(): array
    {
        $days = (int) Config::get('fees.promo_days', 7);
        return [
            'search' => [
                'id'           => 'search',
                'name'         => 'Search boost',
                'blurb'        => 'Top of relevant searches, labelled Promoted.',
                'amount_kobo'  => (int) Config::get('fees.promo_search_kobo', 250000),
                'amount_naira' => kobo_naira((int) Config::get('fees.promo_search_kobo', 250000)),
                'amount_label' => ngn_fmt((int) Config::get('fees.promo_search_kobo', 250000)),
                'days'         => $days,
            ],
            'category' => [
                'id'           => 'category',
                'name'         => 'Category spotlight',
                'blurb'        => 'Category page plus a home tile. Never a trust badge.',
                'amount_kobo'  => (int) Config::get('fees.promo_category_kobo', 500000),
                'amount_naira' => kobo_naira((int) Config::get('fees.promo_category_kobo', 500000)),
                'amount_label' => ngn_fmt((int) Config::get('fees.promo_category_kobo', 500000)),
                'days'         => $days,
            ],
        ];
    }

    public static function status(int $userId): array
    {
        $row = Db::fetch('SELECT * FROM verifications WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$userId]);
        $profile = Db::fetch('SELECT verified FROM profiles WHERE user_id = ?', [$userId]);
        $status = 'none';
        if ($row) {
            $status = $row['status'];
        } elseif ($profile && (int) $profile['verified'] === 1) {
            $status = 'approved';
        }
        return [
            'status'       => $status,
            'amount_kobo'  => (int) Config::get('fees.verification_kobo', 500000),
            'amount_naira' => 5000,
            'amount_label' => '₦5,000',
            'valid_years'  => 2,
            'notes'        => $row['notes'] ?? null,
            'expires_at'   => $row['expires_at'] ?? null,
        ];
    }

    public static function submit(int $userId, array $in): array
    {
        $name = trim((string) ($in['full_name'] ?? ''));
        $type = trim((string) ($in['id_type'] ?? ''));
        $num  = preg_replace('/\s+/', '', (string) ($in['id_number'] ?? '')) ?? '';
        $fields = [];
        if (mb_strlen($name) < 4) {
            $fields['full_name'] = 'Enter the name exactly as on your ID.';
        }
        if ($type === '') {
            $fields['id_type'] = 'Pick an ID type.';
        }
        if (strlen($num) < 8) {
            $fields['id_number'] = 'Enter the ID number.';
        }
        if ($fields) {
            throw new AppError('invalid', 'Please fix the highlighted fields.', 422, $fields);
        }
        $paid = Db::fetch(
            "SELECT id FROM payments WHERE user_id = ? AND purpose = 'verification' AND status = 'succeeded' ORDER BY id DESC LIMIT 1",
            [$userId]
        );
        if ($paid === null) {
            throw new AppError('unpaid', 'Pay the ₦5,000 identity check first.', 409);
        }
        $notes = json_encode(['full_name' => $name, 'id_type' => $type, 'id_number' => $num]);
        $now = now_iso();
        $existing = Db::fetch('SELECT id, status FROM verifications WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$userId]);
        if ($existing && $existing['status'] === 'approved') {
            throw new AppError('exists', 'This account is already verified.', 409);
        }
        if ($existing) {
            Db::run(
                "UPDATE verifications SET status='pending', notes=?, payment_id=?, updated_at=? WHERE id=?",
                [$notes, $paid['id'], $now, $existing['id']]
            );
        } else {
            Db::run(
                "INSERT INTO verifications (user_id, status, amount_kobo, payment_id, notes, created_at, updated_at)
                 VALUES (?, 'pending', ?, ?, ?, ?, ?)",
                [$userId, Config::get('fees.verification_kobo', 500000), $paid['id'], $notes, $now, $now]
            );
        }
        return self::status($userId);
    }

    public static function markPaid(int $userId, int $paymentId): void
    {
        $now = now_iso();
        $existing = Db::fetch('SELECT id, status FROM verifications WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$userId]);
        if ($existing && $existing['status'] === 'approved') {
            return;
        }
        if ($existing) {
            Db::run('UPDATE verifications SET payment_id=?, updated_at=? WHERE id=?', [$paymentId, $now, $existing['id']]);
            return;
        }
        Db::run(
            "INSERT INTO verifications (user_id, status, amount_kobo, payment_id, notes, created_at, updated_at)
             VALUES (?, 'pending', ?, ?, 'payment received — documents not yet submitted', ?, ?)",
            [$userId, Config::get('fees.verification_kobo', 500000), $paymentId, $now, $now]
        );
    }

    public static function promotions(int $userId): array
    {
        $active = Db::fetch(
            "SELECT * FROM promotions WHERE user_id = ? AND status = 'active' AND (ends_at IS NULL OR ends_at > ?) ORDER BY id DESC LIMIT 1",
            [$userId, now_iso()]
        );
        return [
            'tiers'  => array_values(self::promoTiers()),
            'active' => $active ? [
                'plan'      => $active['plan'],
                'starts_at' => $active['starts_at'],
                'ends_at'   => $active['ends_at'],
            ] : null,
        ];
    }

    public static function activatePromo(int $userId, string $plan, int $amountKobo, int $paymentId): void
    {
        $days = (int) Config::get('fees.promo_days', 7);
        $now = now_iso();
        $end = gmdate('Y-m-d H:i:s', time() + $days * 86400);
        Db::run(
            "INSERT INTO promotions (user_id, plan, amount_kobo, status, starts_at, ends_at, created_at)
             VALUES (?, ?, ?, 'active', ?, ?, ?)",
            [$userId, $plan, $amountKobo, $now, $end, $now]
        );
        Db::run('UPDATE profiles SET promo = 1 WHERE user_id = ?', [$userId]);
    }

    public static function expirePromos(): void
    {
        $now = now_iso();
        Db::run("UPDATE promotions SET status='expired' WHERE status='active' AND ends_at IS NOT NULL AND ends_at < ?", [$now]);
        Db::run(
            "UPDATE profiles SET promo = 0 WHERE promo = 1 AND user_id IN (
                SELECT user_id FROM promotions WHERE status = 'expired'
             ) AND user_id NOT IN (
                SELECT user_id FROM promotions WHERE status='active' AND (ends_at IS NULL OR ends_at > ?)
             )",
            [$now]
        );
    }

    public static function review(int $adminId, string $key, string $action, string $reason = ''): array
    {
        $row = Db::fetch('SELECT * FROM verifications WHERE id = ? OR user_id = ? ORDER BY id DESC LIMIT 1', [$key, $key]);
        if ($row === null) {
            throw new AppError('not_found', 'Verification not found.', 404);
        }
        $action = strtolower($action);
        $now = now_iso();
        if ($action === 'approve') {
            $exp = gmdate('Y-m-d H:i:s', time() + 2 * 365 * 86400);
            Db::run(
                "UPDATE verifications SET status='approved', reviewed_by=?, expires_at=?, updated_at=? WHERE id=?",
                [$adminId, $exp, $now, $row['id']]
            );
            Db::run('UPDATE profiles SET verified = 1 WHERE user_id = ?', [$row['user_id']]);
            NotificationService::push((int) $row['user_id'], 'order', 'Identity verified', 'Your identity check is approved. The badge is not a skill certificate.', 'verification.html');
        } elseif ($action === 'reject') {
            Db::run(
                "UPDATE verifications SET status='rejected', reviewed_by=?, notes=?, updated_at=? WHERE id=?",
                [$adminId, $reason !== '' ? $reason : $row['notes'], $now, $row['id']]
            );
            Db::run('UPDATE profiles SET verified = 0 WHERE user_id = ?', [$row['user_id']]);
            NotificationService::push((int) $row['user_id'], 'order', 'Verification not approved', $reason !== '' ? $reason : 'Resubmit with a clearer ID photo.', 'verification.html');
        } else {
            throw new AppError('invalid', 'Approve or reject.', 422);
        }
        Db::run(
            'INSERT INTO admin_audit (admin_id, action, target, meta, created_at) VALUES (?,?,?,?,?)',
            [$adminId, 'verification.' . $action, 'verification:' . $row['id'], json_encode(['status' => $action]), $now]
        );
        return self::status((int) $row['user_id']);
    }
}
