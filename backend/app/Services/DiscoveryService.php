<?php
declare(strict_types=1);

namespace App\Services;

use App\AppError;
use App\Core\Db;
use App\Core\Session;

final class DiscoveryService
{
    public static function landing(): array
    {
        $stats = [
            'professionals' => (int) (self::setting('stats_professionals') ?: 12000),
            'gmv_naira'     => (int) (self::setting('stats_gmv_naira') ?: 4200000000),
            'jobs_done'     => (int) (self::setting('stats_jobs_done') ?: 8500),
            'live_workers'  => (int) Db::fetch("SELECT COUNT(*) c FROM users WHERE roles LIKE '%worker%' AND status='active'")['c'],
            'open_jobs'     => (int) Db::fetch("SELECT COUNT(*) c FROM jobs WHERE status='open'")['c'],
        ];
        return [
            'stats'      => $stats,
            'jobs'       => self::jobs(['per' => 6, 'page' => 1])['items'],
            'workers'    => self::workers(['per' => 3, 'page' => 1, 'verified' => '1'])['items'],
            'categories' => self::categories(),
        ];
    }

    public static function categories(): array
    {
        $parents = Db::fetchAll('SELECT * FROM categories WHERE parent_id IS NULL ORDER BY sort, id');
        $out = [];
        foreach ($parents as $p) {
            $subs = Db::fetchAll('SELECT id, slug, name FROM categories WHERE parent_id = ? ORDER BY sort, id', [$p['id']]);
            $workerCount = (int) Db::fetch(
                "SELECT COUNT(*) c FROM profiles pr
                 JOIN categories c ON c.name = pr.skill
                 WHERE c.parent_id = ? OR c.id = ?",
                [$p['id'], $p['id']]
            )['c'];
            $out[] = [
                'id'      => (int) $p['id'],
                'slug'    => $p['slug'],
                'name'    => $p['name'],
                'icon'    => $p['icon'],
                'blurb'   => $p['blurb'],
                'mode'    => $p['mode_label'],
                'workers' => $workerCount,
                'subs'    => array_map(static fn ($s) => [
                    'id' => (int) $s['id'], 'slug' => $s['slug'], 'name' => $s['name'],
                ], $subs),
            ];
        }
        return $out;
    }

    public static function category(string $slug): array
    {
        $cat = Db::fetch('SELECT * FROM categories WHERE slug = ? OR name = ?', [$slug, $slug]);
        if ($cat === null) {
            throw new AppError('not_found', 'Category not found.', 404);
        }
        $parent = $cat;
        $skill = null;
        if ($cat['parent_id']) {
            $skill = $cat['name'];
            $parent = Db::fetch('SELECT * FROM categories WHERE id = ?', [$cat['parent_id']]) ?: $cat;
        }
        $subs = Db::fetchAll('SELECT slug, name FROM categories WHERE parent_id = ? ORDER BY sort, id', [$parent['id']]);
        $workers = self::workers([
            'skill' => $skill,
            'parent' => $skill ? null : $parent['slug'],
            'per' => 24,
            'page' => 1,
        ]);
        return [
            'category' => [
                'slug'    => $parent['slug'],
                'name'    => $parent['name'],
                'blurb'   => $parent['blurb'],
                'icon'    => $parent['icon'],
                'mode'    => $parent['mode_label'],
                'workers' => $workers['total'],
                'subs'    => $subs,
                'active'  => $skill,
            ],
            'workers' => $workers['items'],
        ];
    }

    public static function jobs(array $f): array
    {
        $page = max(1, (int) ($f['page'] ?? 1));
        $per  = min(24, max(1, (int) ($f['per'] ?? 12)));
        $where = ["j.status = 'open'"];
        $bind = [];
        if (!empty($f['mode'])) {
            $where[] = 'j.work_mode = ?';
            $bind[] = $f['mode'];
        }
        if (!empty($f['q'])) {
            $where[] = '(j.title LIKE ? OR j.description LIKE ? OR j.location LIKE ?)';
            $q = '%' . $f['q'] . '%';
            array_push($bind, $q, $q, $q);
        }
        if (!empty($f['category'])) {
            $where[] = '(c.slug = ? OR c.name = ? OR p.slug = ? OR p.name = ?)';
            $v = $f['category'];
            array_push($bind, $v, $v, $v, $v);
        }
        $sqlWhere = implode(' AND ', $where);
        $total = (int) Db::fetch(
            "SELECT COUNT(*) c FROM jobs j
             LEFT JOIN categories c ON c.id = j.category_id
             LEFT JOIN categories p ON p.id = c.parent_id
             WHERE $sqlWhere",
            $bind
        )['c'];
        $off = ($page - 1) * $per;
        $rows = Db::fetchAll(
            "SELECT j.*, c.name AS category, c.slug AS category_slug, p.slug AS parent_slug, p.name AS parent_name,
                    u.full_name AS client_name, pr.verified AS client_verified, pr.rating_avg AS client_rating, pr.tone AS client_tone,
                    (SELECT COUNT(*) FROM proposals prp WHERE prp.job_id = j.id) AS proposal_count
             FROM jobs j
             LEFT JOIN categories c ON c.id = j.category_id
             LEFT JOIN categories p ON p.id = c.parent_id
             JOIN users u ON u.id = j.client_id
             LEFT JOIN profiles pr ON pr.user_id = u.id
             WHERE $sqlWhere
             ORDER BY j.created_at DESC
             LIMIT $per OFFSET $off",
            $bind
        );
        return ['items' => array_map([self::class, 'jobCard'], $rows), 'total' => $total, 'page' => $page, 'per' => $per];
    }

    public static function job(string $id): array
    {
        $row = Db::fetch(
            "SELECT j.*, c.name AS category, c.slug AS category_slug, p.slug AS parent_slug,
                    u.full_name AS client_name, pr.verified AS client_verified, pr.rating_avg AS client_rating,
                    pr.review_count AS client_reviews, pr.tone AS client_tone, pr.orders_completed AS client_orders
             FROM jobs j
             LEFT JOIN categories c ON c.id = j.category_id
             LEFT JOIN categories p ON p.id = c.parent_id
             JOIN users u ON u.id = j.client_id
             LEFT JOIN profiles pr ON pr.user_id = u.id
             WHERE j.code = ? OR j.id = ?",
            [$id, $id]
        );
        if ($row === null) {
            throw new AppError('not_found', 'Job not found.', 404);
        }
        $card = self::jobCard($row);
        $props = Db::fetchAll(
            "SELECT prp.*, u.full_name, p.public_code, p.tone, p.rating_avg, p.review_count, p.orders_completed, p.verified, p.headline
             FROM proposals prp
             JOIN users u ON u.id = prp.worker_id
             LEFT JOIN profiles p ON p.user_id = u.id
             WHERE prp.job_id = ?
             ORDER BY prp.shortlisted DESC, prp.created_at DESC",
            [$row['id']]
        );
        $card['scope'] = $row['scope'];
        $card['description'] = $row['description'];
        $sinceRaw = $row['client_since'] ?? null;
        $sinceTs = $sinceRaw ? strtotime((string) $sinceRaw) : false;
        $card['client'] = [
            'name'     => $row['client_name'],
            'verified' => (int) $row['client_verified'] === 1,
            'rating'   => (float) $row['client_rating'],
            'reviews'  => (int) ($row['client_reviews'] ?? 0),
            'orders'   => (int) ($row['client_orders'] ?? 0),
            'tone'     => $row['client_tone'] ?: 'a2',
            'initials' => initials($row['client_name']),
            'since'    => $sinceTs ? date('M Y', $sinceTs) : '',
        ];
        $me = Session::userId();
        $card['viewer'] = [
            'is_client' => $me !== null && $me === (int) $row['client_id'],
            'is_worker' => $me !== null && self::isWorker($me),
            'proposed'  => $me !== null && (bool) Db::fetch(
                'SELECT id FROM proposals WHERE job_id = ? AND worker_id = ?',
                [$row['id'], $me]
            ),
        ];
        $card['proposals'] = array_map(static function ($p) use ($me) {
            return [
                'id'         => (int) $p['id'],
                'worker_id'  => $p['public_code'],
                'name'       => $p['full_name'],
                'initials'   => initials($p['full_name']),
                'tone'       => $p['tone'] ?: 'a1',
                'headline'   => $p['headline'],
                'rating'     => (float) $p['rating_avg'],
                'reviews'    => (int) $p['review_count'],
                'jobs'       => (int) $p['orders_completed'],
                'verified'   => (int) $p['verified'] === 1,
                'bid_naira'  => kobo_naira((int) $p['bid_kobo']),
                'bid_label'  => ngn_fmt((int) $p['bid_kobo']),
                'cover'      => $p['cover_note'],
                'days'       => (int) ($p['days'] ?? 0),
                'shortlisted'=> (int) $p['shortlisted'] === 1,
                'status'     => $p['status'],
                'mine'       => $me !== null && (int) $p['worker_id'] === $me,
            ];
        }, $props);
        return $card;
    }

    public static function propose(int $workerId, string $jobKey, string $cover, int $bidNaira, int $days = 0): array
    {
        if (!self::isWorker($workerId)) {
            throw new AppError('forbidden', 'Only worker accounts can send a proposal.', 403);
        }
        $job = Db::fetch('SELECT * FROM jobs WHERE code = ? OR id = ?', [$jobKey, $jobKey]);
        if ($job === null || $job['status'] !== 'open') {
            throw new AppError('not_found', 'That job is not open.', 404);
        }
        if ((int) $job['client_id'] === $workerId) {
            throw new AppError('forbidden', 'You cannot apply to your own job.', 403);
        }
        if (mb_strlen($cover) < 20) {
            throw new AppError('invalid', 'Tell the client a bit more — 20 characters at least.', 422, ['cover_note' => 'Tell the client a bit more — 20 characters at least.']);
        }
        if ($bidNaira < 1000) {
            throw new AppError('invalid', 'Enter a bid in naira.', 422, ['bid' => 'Enter a bid of at least ₦1,000.']);
        }
        try {
            Db::run(
                'INSERT INTO proposals (job_id, worker_id, cover_note, bid_kobo, status, shortlisted, created_at, days) VALUES (?,?,?,?,\'sent\',0,?,?)',
                [$job['id'], $workerId, $cover, $bidNaira * 100, now_iso(), $days > 0 ? $days : null]
            );
        } catch (\PDOException $e) {
            throw new AppError('exists', 'You already sent a proposal for this job.', 409);
        }
        return ['ok' => true, 'job' => $job['code']];
    }

    public static function workers(array $f): array
    {
        TrustService::expirePromos();
        $page = max(1, (int) ($f['page'] ?? 1));
        $per  = min(24, max(1, (int) ($f['per'] ?? 12)));
        $where = ["u.status = 'active'", "u.roles LIKE '%worker%'"];
        $bind = [];
        if (!empty($f['q'])) {
            $ors = [];
            foreach (self::expandSearch((string) $f['q']) as $term) {
                $like = '%' . $term . '%';
                $ors[] = '(u.full_name LIKE ? OR p.headline LIKE ? OR p.skill LIKE ? OR p.city LIKE ? OR p.state LIKE ?
                    OR c.name LIKE ? OR EXISTS (
                        SELECT 1 FROM services s
                        WHERE s.worker_id = u.id AND (s.title LIKE ? OR s.description LIKE ?)
                    ))';
                array_push($bind, $like, $like, $like, $like, $like, $like, $like, $like);
            }
            $where[] = '(' . implode(' OR ', $ors) . ')';
        }
        if (!empty($f['state'])) {
            $where[] = 'p.state = ?';
            $bind[] = $f['state'];
        }
        if (!empty($f['mode'])) {
            $where[] = 'p.work_mode = ?';
            $bind[] = $f['mode'];
        }
        if (!empty($f['skill'])) {
            $where[] = 'p.skill = ?';
            $bind[] = $f['skill'];
        }
        if (!empty($f['parent'])) {
            $where[] = 'c.parent_id = (SELECT id FROM categories WHERE slug = ?)';
            $bind[] = $f['parent'];
        }
        if (!empty($f['verified'])) {
            $where[] = 'p.verified = 1';
        }
        if (!empty($f['rating'])) {
            $min = str_contains((string) $f['rating'], '4.8') ? 4.8 : 4.5;
            $where[] = 'p.rating_avg >= ?';
            $bind[] = $min;
        }
        $sqlWhere = implode(' AND ', $where);
        $total = (int) Db::fetch(
            "SELECT COUNT(*) c FROM users u
             JOIN profiles p ON p.user_id = u.id
             LEFT JOIN categories c ON c.name = p.skill
             WHERE $sqlWhere",
            $bind
        )['c'];
        $sort = match ($f['sort'] ?? '') {
            'orders' => 'p.orders_completed DESC',
            'newest' => 'u.created_at DESC',
            default  => 'p.promo DESC, p.rating_avg DESC, p.orders_completed DESC',
        };
        $off = ($page - 1) * $per;
        $rows = Db::fetchAll(
            "SELECT u.id, u.full_name, p.*
             FROM users u
             JOIN profiles p ON p.user_id = u.id
             LEFT JOIN categories c ON c.name = p.skill
             WHERE $sqlWhere
             ORDER BY $sort
             LIMIT $per OFFSET $off",
            $bind
        );
        return ['items' => array_map([self::class, 'workerCard'], $rows), 'total' => $total, 'page' => $page, 'per' => $per];
    }

    public static function worker(string $id): array
    {
        $row = Db::fetch(
            "SELECT u.id, u.full_name, u.created_at AS joined, p.*
             FROM users u JOIN profiles p ON p.user_id = u.id
             WHERE p.public_code = ? OR u.id = ?",
            [$id, $id]
        );
        if ($row === null) {
            throw new AppError('not_found', 'Worker not found.', 404);
        }
        $card = self::workerCard($row);
        $services = Db::fetchAll(
            'SELECT * FROM services WHERE worker_id = ? AND status = \'live\' ORDER BY id',
            [$row['user_id'] ?? $row['id']]
        );
        $uid = (int) ($row['user_id'] ?? $row['id']);
        $card['services'] = array_map([self::class, 'serviceCard'], $services);
        $card['bio'] = $row['headline']; // fallback; real bio on users/profiles
        $bio = Db::fetch('SELECT bio FROM profiles WHERE user_id = ?', [$uid]);
        $card['bio'] = $bio['bio'] ?? $row['headline'];
        $revs = Db::fetchAll(
            "SELECT r.*, u.full_name FROM reviews r JOIN users u ON u.id = r.from_user
             WHERE r.to_user = ? ORDER BY r.created_at DESC LIMIT 20",
            [$uid]
        );
        $card['reviews_list'] = array_map(static function ($r) {
            $tags = json_decode((string) $r['tags'], true) ?: [];
            return [
                'name'     => $r['full_name'],
                'initials' => initials($r['full_name']),
                'stars'    => (int) $r['rating'],
                'date'     => date('M j, Y', strtotime($r['created_at']) ?: time()),
                'text'     => $r['comment'],
                'tags'     => $tags,
                'reply'    => $r['reply'],
            ];
        }, $revs);
        $dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach (Db::fetchAll('SELECT rating, COUNT(*) c FROM reviews WHERE to_user = ? GROUP BY rating', [$uid]) as $d) {
            $dist[(int) $d['rating']] = (int) $d['c'];
        }
        $sum = array_sum($dist) ?: 1;
        $card['distribution'] = [];
        foreach ($dist as $star => $n) {
            $card['distribution'][] = ['stars' => $star, 'pct' => (int) round(100 * $n / $sum)];
        }
        $me = Session::userId();
        $card['saved'] = $me !== null && (bool) Db::fetch(
            'SELECT worker_id FROM saved_workers WHERE user_id = ? AND worker_id = ?',
            [$me, $uid]
        );
        return $card;
    }

    public static function service(string $id): array
    {
        $row = Db::fetch(
            'SELECT s.*, u.full_name, p.public_code AS worker_code, p.tone, p.verified, p.rating_avg, p.review_count, p.reply, p.city, p.state, p.headline
             FROM services s
             JOIN users u ON u.id = s.worker_id
             LEFT JOIN profiles p ON p.user_id = u.id
             WHERE s.public_code = ? OR s.id = ?',
            [$id, $id]
        );
        if ($row === null) {
            throw new AppError('not_found', 'Service not found.', 404);
        }
        $card = self::serviceCard($row);
        $card['worker'] = [
            'id'       => $row['worker_code'],
            'name'     => $row['full_name'],
            'initials' => initials($row['full_name']),
            'tone'     => $row['tone'] ?: 'a1',
            'verified' => (int) $row['verified'] === 1,
            'rating'   => (float) $row['rating_avg'],
            'reviews'  => (int) $row['review_count'],
            'reply'    => $row['reply'],
            'headline' => $row['headline'],
            'city'     => $row['city'],
            'state'    => $row['state'],
        ];
        return $card;
    }

    public static function saved(int $userId): array
    {
        $rows = Db::fetchAll(
            "SELECT sw.note, sw.created_at, u.full_name, p.*
             FROM saved_workers sw
             JOIN users u ON u.id = sw.worker_id
             JOIN profiles p ON p.user_id = u.id
             WHERE sw.user_id = ?
             ORDER BY sw.created_at DESC",
            [$userId]
        );
        return array_map(static function ($r) {
            $w = self::workerCard($r);
            $w['note'] = $r['note'];
            return $w;
        }, $rows);
    }

    public static function save(int $userId, string $workerKey, ?string $note = null): array
    {
        $w = Db::fetch(
            'SELECT u.id FROM users u JOIN profiles p ON p.user_id = u.id WHERE p.public_code = ? OR u.id = ?',
            [$workerKey, $workerKey]
        );
        if ($w === null) {
            throw new AppError('not_found', 'Worker not found.', 404);
        }
        Db::run(
            'INSERT OR IGNORE INTO saved_workers (user_id, worker_id, note, created_at) VALUES (?,?,?,?)',
            [$userId, $w['id'], $note, now_iso()]
        );
        return ['saved' => true];
    }

    public static function unsave(int $userId, string $workerKey): array
    {
        $w = Db::fetch(
            'SELECT u.id FROM users u JOIN profiles p ON p.user_id = u.id WHERE p.public_code = ? OR u.id = ?',
            [$workerKey, $workerKey]
        );
        if ($w) {
            Db::run('DELETE FROM saved_workers WHERE user_id = ? AND worker_id = ?', [$userId, $w['id']]);
        }
        return ['saved' => false];
    }

    private static function jobCard(array $j): array
    {
        $kobo = $j['budget_kobo'] !== null ? (int) $j['budget_kobo'] : null;
        $type = $j['budget_type'] ?: 'fixed';
        return [
            'id'            => $j['code'],
            'numeric_id'    => (int) $j['id'],
            'title'         => $j['title'],
            'category'      => $j['category'] ?? '',
            'parent'        => $j['parent_name'] ?? '',
            'mode'          => $j['work_mode'],
            'loc'           => $j['location'],
            'budget_kobo'   => $kobo,
            'budget_naira'  => kobo_naira($kobo),
            'budget_label'  => ngn_fmt($kobo, $type),
            'budget_type'   => $type,
            'time'          => ago($j['created_at']),
            'deadline'      => $j['deadline'] ?: '—',
            'proposals'     => (int) ($j['proposal_count'] ?? 0),
            'client'        => $j['client_name'] ?? '',
            'clientRating'  => (float) ($j['client_rating'] ?? 0),
            'verified'      => (int) ($j['client_verified'] ?? 0) === 1,
            'desc'          => $j['scope'] ?? '',
        ];
    }

    private static function workerCard(array $w): array
    {
        $svc = Db::fetch(
            'SELECT public_code, title, price_kobo FROM services WHERE worker_id = ? AND status = \'live\' ORDER BY id LIMIT 1',
            [$w['user_id'] ?? $w['id']]
        );
        return [
            'id'       => $w['public_code'],
            'numeric_id' => (int) ($w['user_id'] ?? $w['id']),
            'name'     => $w['full_name'],
            'init'     => initials($w['full_name']),
            'tone'     => $w['tone'] ?: 'a1',
            'headline' => $w['headline'],
            'skill'    => $w['skill'],
            'city'     => $w['city'],
            'state'    => $w['state'],
            'mode'     => $w['work_mode'],
            'rating'   => (float) $w['rating_avg'],
            'reviews'  => (int) $w['review_count'],
            'jobs'     => (int) $w['orders_completed'],
            'resp'     => $w['reply'],
            'verified' => (int) $w['verified'] === 1,
            'promo'    => (int) $w['promo'] === 1,
            'from'     => kobo_naira($svc ? (int) $svc['price_kobo'] : null),
            'service'  => $svc ? ['id' => $svc['public_code'], 'title' => $svc['title'], 'from' => kobo_naira((int) $svc['price_kobo'])] : null,
        ];
    }

    private static function serviceCard(array $s): array
    {
        $packages = json_decode((string) ($s['packages_json'] ?? ''), true) ?: [];
        return [
            'id'          => $s['public_code'] ?: ('s' . $s['id']),
            'numeric_id'  => (int) $s['id'],
            'title'       => $s['title'],
            'description' => $s['description'],
            'from_naira'  => kobo_naira((int) $s['price_kobo']),
            'from_label'  => ngn_fmt((int) $s['price_kobo']),
            'packages'    => $packages,
            'mode'        => $s['work_mode'] ?? null,
        ];
    }

    /** Map everyday search words to skill names workers actually list. */
    private static function expandSearch(string $q): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $map = [
            'website' => ['website', 'web', 'web development'],
            'web' => ['web', 'website', 'web development'],
            'logo' => ['logo', 'graphic design', 'brand'],
            'plumber' => ['plumber', 'plumbing'],
            'plumbing' => ['plumber', 'plumbing'],
            'electric' => ['electric', 'electrical', 'electrician'],
            'electrician' => ['electric', 'electrical', 'electrician'],
            'ui' => ['ui', 'ux', 'ui/ux', 'design'],
            'ux' => ['ui', 'ux', 'ui/ux'],
            'carpenter' => ['carpenter', 'carpentry'],
            'carpentry' => ['carpenter', 'carpentry'],
            'video' => ['video', 'video editing'],
            'marketing' => ['marketing', 'digital marketing'],
        ];
        $low = mb_strtolower($q);
        $out = [$q];
        foreach ($map as $k => $extra) {
            if ($low === $k || str_contains($low, $k)) {
                $out = array_merge($out, $extra);
            }
        }
        return array_values(array_unique($out));
    }

    private static function isWorker(int $id): bool
    {
        $u = Db::fetch('SELECT roles FROM users WHERE id = ?', [$id]);
        return $u !== null && str_contains((string) $u['roles'], 'worker');
    }

    private static function setting(string $key): ?string
    {
        $r = Db::fetch('SELECT value FROM settings WHERE key = ?', [$key]);
        return $r['value'] ?? null;
    }
}
