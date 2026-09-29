<?php
declare(strict_types=1);

namespace App\Services;

use App\AppError;
use App\Core\Db;

final class ServiceCatalog
{
    public static function mine(int $workerId): array
    {
        $rows = Db::fetchAll(
            "SELECT s.*, c.name AS category
             FROM services s LEFT JOIN categories c ON c.id = s.category_id
             WHERE s.worker_id = ?
             ORDER BY s.id DESC",
            [$workerId]
        );
        return array_map([self::class, 'card'], $rows);
    }

    public static function getOwned(int $workerId, string $key): array
    {
        $row = Db::fetch(
            "SELECT s.*, c.name AS category FROM services s
             LEFT JOIN categories c ON c.id = s.category_id
             WHERE (s.public_code = ? OR s.id = ?) AND s.worker_id = ?",
            [$key, $key, $workerId]
        );
        if ($row === null) {
            throw new AppError('not_found', 'Service not found.', 404);
        }
        return self::card($row);
    }

    public static function create(int $workerId, array $in, bool $draft = false): array
    {
        self::ensureAreas();
        $parsed = self::parse($in, null, $draft);
        $now = now_iso();
        $status = $draft ? 'draft' : 'live';
        $code = self::nextCode($workerId);
        Db::run(
            'INSERT INTO services (worker_id, title, description, price_kobo, status, created_at, updated_at, category_id, packages_json, public_code, work_mode, service_areas)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $workerId, $parsed['title'], $parsed['description'], $parsed['price_kobo'], $status,
                $now, $now, $parsed['category_id'], $parsed['packages_json'], $code, $parsed['work_mode'], $parsed['service_areas'],
            ]
        );
        return self::getOwned($workerId, $code);
    }

    public static function update(int $workerId, string $key, array $in): array
    {
        $cur = Db::fetch(
            'SELECT * FROM services WHERE (public_code = ? OR id = ?) AND worker_id = ?',
            [$key, $key, $workerId]
        );
        if ($cur === null) {
            throw new AppError('not_found', 'Service not found.', 404);
        }
        if (isset($in['status']) && in_array($in['status'], ['live', 'draft'], true) && count($in) === 1) {
            Db::run('UPDATE services SET status=?, updated_at=? WHERE id=?', [$in['status'], now_iso(), $cur['id']]);
            return self::getOwned($workerId, (string) $cur['id']);
        }
        self::ensureAreas();
        $asDraft = array_key_exists('draft', $in) ? !empty($in['draft']) : false;
        $parsed = self::parse($in, $cur, $asDraft);
        $status = $cur['status'];
        if (array_key_exists('draft', $in)) {
            $status = $asDraft ? 'draft' : 'live';
        }
        Db::run(
            'UPDATE services SET title=?, description=?, price_kobo=?, category_id=?, packages_json=?, work_mode=?, service_areas=?, status=?, updated_at=? WHERE id=?',
            [
                $parsed['title'], $parsed['description'], $parsed['price_kobo'], $parsed['category_id'],
                $parsed['packages_json'], $parsed['work_mode'], $parsed['service_areas'], $status, now_iso(), $cur['id'],
            ]
        );
        return self::getOwned($workerId, (string) $cur['id']);
    }

    public static function pause(int $workerId, string $key): array
    {
        $cur = Db::fetch(
            'SELECT * FROM services WHERE (public_code = ? OR id = ?) AND worker_id = ?',
            [$key, $key, $workerId]
        );
        if ($cur === null) {
            throw new AppError('not_found', 'Service not found.', 404);
        }
        $next = $cur['status'] === 'live' ? 'draft' : 'live';
        Db::run('UPDATE services SET status=?, updated_at=? WHERE id=?', [$next, now_iso(), $cur['id']]);
        return self::getOwned($workerId, (string) $cur['id']);
    }

    /** @param array<string,mixed> $in */
    private static function parse(array $in, ?array $cur = null, bool $draft = false): array
    {
        $title = trim((string) ($in['title'] ?? $cur['title'] ?? ''));
        $desc = trim((string) ($in['description'] ?? $cur['description'] ?? ''));
        $cat = trim((string) ($in['category'] ?? ''));
        $mode = (string) ($in['work_mode'] ?? $cur['work_mode'] ?? 'remote');
        $fields = [];
        if (mb_strlen($title) < 8) {
            $fields['title'] = 'Give the service a clearer title.';
        }
        if (mb_strlen($desc) < 20) {
            $fields['description'] = 'Tell clients what you deliver — 20 characters at least.';
        }
        if (!in_array($mode, ['remote', 'on-site', 'hybrid'], true)) {
            $mode = 'remote';
        }
        $packages = $in['packages'] ?? null;
        if (!is_array($packages) || !$packages) {
            $one = (int) preg_replace('/\D/', '', (string) ($in['price_naira'] ?? '0'));
            if ($one >= 500) {
                $packages = [['name' => 'Standard', 'price_naira' => $one, 'days' => 7, 'revisions' => 1]];
            } elseif ($cur && $cur['packages_json']) {
                $packages = json_decode((string) $cur['packages_json'], true) ?: [];
            }
        }
        $norm = [];
        foreach (array_slice($packages ?: [], 0, 3) as $p) {
            $naira = (int) preg_replace('/\D/', '', (string) ($p['price_naira'] ?? $p['price'] ?? '0'));
            if ($naira < 500) {
                continue;
            }
            if ($naira > 20000000) {
                $fields['packages'] = 'Packages above ₦20,000,000 need a custom quote, not a listed price.';
            }
            $norm[] = [
                'name'        => trim((string) ($p['name'] ?? 'Package')) ?: 'Package',
                'price_naira' => $naira,
                'price_kobo'  => $naira * 100,
                'days'        => max(1, (int) ($p['days'] ?? 7)),
                'revisions'   => max(0, min(10, (int) ($p['revisions'] ?? 1))),
            ];
        }
        if (!$norm) {
            $fields['packages'] = 'Add at least one package of ₦500 or more.';
        }
        $allowed = self::ngStates();
        $rawAreas = $in['service_areas'] ?? null;
        if (is_string($rawAreas)) {
            $rawAreas = array_map('trim', explode(',', $rawAreas));
        }
        if (!is_array($rawAreas)) {
            $prev = json_decode((string) ($cur['service_areas'] ?? ''), true);
            $rawAreas = is_array($prev) ? $prev : [];
        }
        $areas = [];
        foreach ($rawAreas as $st) {
            $st = trim((string) $st);
            if ($st !== '' && in_array($st, $allowed, true) && !in_array($st, $areas, true)) {
                $areas[] = $st;
            }
        }
        if (!$draft && ($mode === 'on-site' || $mode === 'hybrid') && $areas === []) {
            $fields['service_areas'] = 'Pick the states you travel to.';
        }
        if ($mode === 'remote') {
            $areas = [];
        }
        if ($fields) {
            throw new AppError('invalid', 'Please fix the highlighted fields.', 422, $fields);
        }
        $aliases = [
            'Graphic Design & Branding' => 'Graphic Design',
            'App Development' => 'Web Development',
        ];
        $lookup = $aliases[$cat] ?? $cat;
        $catRow = $lookup !== ''
            ? Db::fetch('SELECT id FROM categories WHERE name = ? OR slug = ?', [$lookup, $lookup])
            : null;
        $floor = $norm[0]['price_kobo'];
        foreach ($norm as $p) {
            $floor = min($floor, $p['price_kobo']);
        }
        return [
            'title'         => $title,
            'description'   => $desc,
            'work_mode'      => $mode,
            'category_id'    => $catRow['id'] ?? ($cur['category_id'] ?? null),
            'packages_json'  => json_encode($norm),
            'price_kobo'     => $floor,
            'service_areas'  => json_encode($areas),
        ];
    }

    private static function card(array $s): array
    {
        $packages = json_decode((string) ($s['packages_json'] ?? ''), true) ?: [];
        return [
            'id'          => $s['public_code'] ?: ('s' . $s['id']),
            'numeric_id'  => (int) $s['id'],
            'title'       => $s['title'],
            'description' => $s['description'],
            'category'    => $s['category'] ?? '',
            'mode'        => $s['work_mode'] ?: 'remote',
            'service_areas' => json_decode((string) ($s['service_areas'] ?? ''), true) ?: [],
            'status'      => $s['status'],
            'live'        => $s['status'] === 'live',
            'from_label'  => ngn_fmt((int) $s['price_kobo']),
            'packages'    => $packages,
        ];
    }

    /** @return list<string> */
    public static function ngStates(): array
    {
        return [
            'Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue', 'Borno',
            'Cross River', 'Delta', 'Ebonyi', 'Edo', 'Ekiti', 'Enugu', 'FCT', 'Gombe', 'Imo',
            'Jigawa', 'Kaduna', 'Kano', 'Katsina', 'Kebbi', 'Kogi', 'Kwara', 'Lagos', 'Nasarawa',
            'Niger', 'Ogun', 'Ondo', 'Osun', 'Oyo', 'Plateau', 'Rivers', 'Sokoto', 'Taraba',
            'Yobe', 'Zamfara',
        ];
    }

    private static function ensureAreas(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            $col = Db::isMysql() ? 'VARCHAR(800) NULL' : 'TEXT';
            Db::exec('ALTER TABLE services ADD COLUMN service_areas ' . $col);
        } catch (\Throwable $e) {
        }
    }

    private static function nextCode(int $workerId): string
    {
        $p = Db::fetch('SELECT public_code FROM profiles WHERE user_id=?', [$workerId]);
        $base = $p['public_code'] ?? ('u' . $workerId);
        $n = (int) (Db::fetch('SELECT COUNT(*) c FROM services WHERE worker_id=?', [$workerId])['c'] ?? 0) + 1;
        $code = 's-' . $base . '-' . $n;
        while (Db::fetch('SELECT id FROM services WHERE public_code=?', [$code])) {
            $n++;
            $code = 's-' . $base . '-' . $n;
        }
        return $code;
    }
}
