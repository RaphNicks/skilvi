<?php
declare(strict_types=1);

namespace App\Services;

use App\AppError;
use App\Core\Db;

final class JobService
{
    public static function create(int $clientId, array $in): array
    {
        $title = trim((string) ($in['title'] ?? ''));
        $desc  = trim((string) ($in['description'] ?? ''));
        $cat   = trim((string) ($in['category'] ?? ''));
        $mode  = (string) ($in['work_mode'] ?? 'on-site');
        $type  = (($in['budget_type'] ?? 'fixed') === 'negotiable') ? 'negotiable' : 'fixed';
        $state = trim((string) ($in['state'] ?? ''));
        $city  = trim((string) ($in['city'] ?? ''));
        $fields = [];
        if (mb_strlen($title) < 8) {
            $fields['title'] = 'Give the job a clearer title.';
        }
        if (mb_strlen($desc) < 30) {
            $fields['description'] = 'Add more scope — 30 characters at least.';
        }
        if ($cat === '') {
            $fields['category'] = 'Pick a category.';
        }
        if (!in_array($mode, ['remote', 'on-site', 'hybrid'], true)) {
            $fields['work_mode'] = 'Choose remote, on-site or hybrid.';
        }
        $naira = (int) preg_replace('/\D/', '', (string) ($in['budget_naira'] ?? $in['budget'] ?? '0'));
        if ($type === 'fixed' && $naira < 1000) {
            $fields['budget'] = 'Enter a budget in naira.';
        }
        if ($mode !== 'remote' && $state === '') {
            $fields['state'] = 'Pick a state for on-site work.';
        }
        if ($fields) {
            throw new AppError('invalid', 'Please fix the highlighted fields.', 422, $fields);
        }
        $aliases = [
            'Graphic Design & Branding' => 'Graphic Design',
            'App Development' => 'Web Development',
            'Software & Scripts' => 'Web Development',
            'Photography' => 'Video & Motion',
            'AC Installation & Repair' => 'Generator & Power',
            'Virtual Assistance' => 'Accounting & Bookkeeping',
            'Consulting' => 'Accounting & Bookkeeping',
        ];
        $lookup = $aliases[$cat] ?? $cat;
        $catRow = Db::fetch('SELECT id FROM categories WHERE name = ? OR slug = ?', [$lookup, $lookup]);
        $loc = $mode === 'remote' ? 'Remote' : trim($city . ($city && $state ? ', ' : '') . $state);
        $deadline = trim((string) ($in['deadline'] ?? ''));
        if ($deadline !== '') {
            $deadline = date('M j, Y', strtotime($deadline) ?: time());
        } else {
            $deadline = null;
        }
        $code = self::nextCode();
        $now = now_iso();
        $max = (int) ($in['max_proposals'] ?? 0);
        Db::run(
            'INSERT INTO jobs (code, client_id, category_id, title, description, budget_kobo, work_mode, location, deadline, status, created_at, updated_at, budget_type, scope, max_proposals)
             VALUES (?,?,?,?,?,?,?,?,?,\'open\',?,?,?,?,?)',
            [
                $code, $clientId, $catRow['id'] ?? null, $title, $desc,
                $type === 'negotiable' ? ($naira > 0 ? $naira * 100 : null) : $naira * 100,
                $mode, $loc, $deadline, $now, $now, $type, mb_substr($desc, 0, 160),
                $max > 0 ? $max : null,
            ]
        );
        return DiscoveryService::job($code);
    }

    public static function close(int $clientId, string $key): array
    {
        $job = self::owned($clientId, $key);
        if ($job['status'] !== 'open') {
            throw new AppError('conflict', 'Only open jobs can be closed.', 409);
        }
        Db::run("UPDATE jobs SET status='closed', updated_at=? WHERE id=?", [now_iso(), $job['id']]);
        return ['id' => $job['code'], 'status' => 'closed'];
    }

    public static function cancel(int $clientId, string $key): array
    {
        $job = self::owned($clientId, $key);
        if ($job['status'] !== 'open') {
            throw new AppError('conflict', 'Only open jobs can be cancelled.', 409);
        }
        Db::run("UPDATE jobs SET status='cancelled', updated_at=? WHERE id=?", [now_iso(), $job['id']]);
        return ['id' => $job['code'], 'status' => 'cancelled'];
    }

    public static function mine(int $clientId): array
    {
        $rows = Db::fetchAll(
            "SELECT j.*, c.name AS category,
                    (SELECT COUNT(*) FROM proposals p WHERE p.job_id = j.id) AS proposal_count
             FROM jobs j
             LEFT JOIN categories c ON c.id = j.category_id
             WHERE j.client_id = ?
             ORDER BY j.created_at DESC",
            [$clientId]
        );
        return array_map(static function ($j) {
            return [
                'id'           => $j['code'],
                'title'        => $j['title'],
                'status'       => $j['status'],
                'category'     => $j['category'],
                'mode'         => $j['work_mode'],
                'loc'          => $j['location'],
                'budget_label' => ngn_fmt($j['budget_kobo'] !== null ? (int) $j['budget_kobo'] : null, $j['budget_type'] ?: 'fixed'),
                'proposals'    => (int) $j['proposal_count'],
                'time'         => ago($j['created_at']),
            ];
        }, $rows);
    }

    public static function shortlist(int $clientId, int $proposalId): array
    {
        $p = self::ownedProposal($clientId, $proposalId);
        $next = ((int) $p['shortlisted'] === 1) ? 0 : 1;
        Db::run('UPDATE proposals SET shortlisted = ? WHERE id = ?', [$next, $proposalId]);
        return ['id' => $proposalId, 'shortlisted' => $next === 1];
    }

    public static function withdrawProposal(int $workerId, int $proposalId): array
    {
        $p = Db::fetch('SELECT * FROM proposals WHERE id = ? AND worker_id = ?', [$proposalId, $workerId]);
        if ($p === null) {
            throw new AppError('not_found', 'Proposal not found.', 404);
        }
        if ($p['status'] !== 'sent') {
            throw new AppError('conflict', 'Only a sent proposal can be withdrawn.', 409);
        }
        Db::run("UPDATE proposals SET status='withdrawn' WHERE id=?", [$proposalId]);
        return ['id' => $proposalId, 'status' => 'withdrawn'];
    }

    public static function reject(int $clientId, int $proposalId): array
    {
        $p = self::ownedProposal($clientId, $proposalId);
        if ($p['status'] !== 'sent') {
            throw new AppError('conflict', 'That proposal cannot be rejected.', 409);
        }
        Db::run("UPDATE proposals SET status='rejected' WHERE id=?", [$proposalId]);
        self::notify((int) $p['worker_id'], 'proposal', 'Proposal not selected', 'A client passed on one of your proposals.');
        return ['id' => $proposalId, 'status' => 'rejected'];
    }

    public static function accept(int $clientId, int $proposalId): array
    {
        $p = self::ownedProposal($clientId, $proposalId);
        $job = Db::fetch('SELECT * FROM jobs WHERE id = ?', [$p['job_id']]);
        if ($job === null || $job['status'] !== 'open') {
            throw new AppError('conflict', 'This job is no longer open.', 409);
        }
        if ($p['status'] !== 'sent') {
            throw new AppError('conflict', 'That proposal is not open.', 409);
        }
        $order = OrderService::openFromProposal($job, $p);
        Db::run("UPDATE proposals SET status='accepted' WHERE id=?", [$proposalId]);
        Db::run("UPDATE proposals SET status='rejected' WHERE job_id=? AND id<>? AND status='sent'", [$job['id'], $proposalId]);
        Db::run("UPDATE jobs SET status='awarded', updated_at=? WHERE id=?", [now_iso(), $job['id']]);
        self::notify((int) $p['worker_id'], 'order', 'Proposal accepted', 'A client accepted your proposal. They are paying into escrow for ' . $order['id'] . '.');
        $order['checkout_url'] = '/checkout.html?order=' . rawurlencode($order['id']);
        return $order;
    }

    /** @return array<string,mixed> */
    private static function owned(int $clientId, string $key): array
    {
        $job = Db::fetch('SELECT * FROM jobs WHERE (code = ? OR id = ?) AND client_id = ?', [$key, $key, $clientId]);
        if ($job === null) {
            throw new AppError('not_found', 'Job not found.', 404);
        }
        return $job;
    }

    /** @return array<string,mixed> */
    private static function ownedProposal(int $clientId, int $id): array
    {
        $p = Db::fetch(
            'SELECT p.* FROM proposals p JOIN jobs j ON j.id = p.job_id WHERE p.id = ? AND j.client_id = ?',
            [$id, $clientId]
        );
        if ($p === null) {
            throw new AppError('not_found', 'Proposal not found.', 404);
        }
        return $p;
    }

    private static function nextCode(): string
    {
        $row = Db::fetch("SELECT code FROM jobs WHERE code LIKE 'j%' ORDER BY id DESC LIMIT 1");
        $n = 7;
        if ($row && preg_match('/j(\d+)/', (string) $row['code'], $m)) {
            $n = (int) $m[1] + 1;
        }
        return 'j' . str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    }

    private static function notify(int $userId, string $kind, string $title, string $body): void
    {
        Db::run(
            'INSERT INTO notifications (user_id, kind, title, body, created_at) VALUES (?,?,?,?,?)',
            [$userId, $kind, $title, $body, now_iso()]
        );
    }
}
