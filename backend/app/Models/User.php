<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Db;

final class User
{
    public static function find(int $id): ?array
    {
        return Db::fetch('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function findByPhone(string $phone): ?array
    {
        return Db::fetch('SELECT * FROM users WHERE phone = ?', [$phone]);
    }

    public static function findByEmail(string $email): ?array
    {
        return Db::fetch('SELECT * FROM users WHERE LOWER(email) = LOWER(?)', [$email]);
    }

    public static function findByGoogleId(string $sub): ?array
    {
        $sub = trim($sub);
        if ($sub === '') {
            return null;
        }
        return Db::fetch('SELECT * FROM users WHERE google_id = ?', [$sub]);
    }

    public static function findByIdentifier(string $raw): ?array
    {
        $raw = trim($raw);
        if (str_contains($raw, '@')) {
            return self::findByEmail($raw);
        }
        $phone = normalize_phone($raw);
        if ($phone !== '') {
            return self::findByPhone($phone);
        }
        return self::findByEmail($raw);
    }

    public static function create(array $row): int
    {
        $now = now_iso();
        Db::run(
            'INSERT INTO users (phone, email, password_hash, full_name, roles, status, phone_verified_at, email_verified_at, google_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (($row['phone'] ?? '') !== '')
                    ? $row['phone']
                    : ('e:' . strtolower((string) ($row['email'] ?? ''))),
                $row['email'] ?? null,
                $row['password_hash'],
                $row['full_name'],
                $row['roles'],
                $row['status'] ?? 'active',
                $row['phone_verified_at'] ?? $now,
                $row['email_verified_at'] ?? null,
                $row['google_id'] ?? null,
                $now,
                $now,
            ]
        );
        $id = Db::lastInsertId();
        Db::run(
            'INSERT INTO profiles (user_id, headline, bio, state, city, country, country_code, dob, gender, heard_about, notify_sms, notify_jobs, notify_marketing, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, 0, ?, ?)',
            [
                $id,
                $row['headline'] ?? null,
                $row['bio'] ?? null,
                $row['state'] ?? null,
                $row['city'] ?? null,
                $row['country'] ?? null,
                $row['country_code'] ?? null,
                $row['dob'] ?? null,
                $row['gender'] ?? null,
                $row['heard_about'] ?? null,
                $now,
                $now,
            ]
        );
        Db::run('INSERT INTO wallets (user_id, available_kobo, pending_kobo, updated_at) VALUES (?, 0, 0, ?)', [$id, $now]);
        self::ensurePublicCode($id);
        return $id;
    }

    public static function ensurePublicCode(int $id): string
    {
        $p = Profile::forUser($id);
        $code = trim((string) ($p['public_code'] ?? ''));
        if ($code !== '') {
            return $code;
        }
        $code = 'u' . $id;
        Db::run('UPDATE profiles SET public_code = ? WHERE user_id = ?', [$code, $id]);
        return $code;
    }

    public static function touchLogin(int $id): void
    {
        Db::run('UPDATE users SET last_login_at = ?, updated_at = ? WHERE id = ?', [now_iso(), now_iso(), $id]);
    }

    public static function updatePassword(int $id, string $hash): void
    {
        Db::run('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?', [$hash, now_iso(), $id]);
    }

    public static function updateEmail(int $id, ?string $email): void
    {
        Db::run('UPDATE users SET email = ?, updated_at = ? WHERE id = ?', [$email, now_iso(), $id]);
    }

    public static function updatePhone(int $id, string $phone): void
    {
        Db::run(
            'UPDATE users SET phone = ?, phone_verified_at = ?, updated_at = ? WHERE id = ?',
            [$phone, now_iso(), now_iso(), $id]
        );
    }

    public static function updateName(int $id, string $name): void
    {
        Db::run('UPDATE users SET full_name = ?, updated_at = ? WHERE id = ?', [$name, now_iso(), $id]);
    }

    public static function updateRoles(int $id, string $roles): void
    {
        Db::run('UPDATE users SET roles = ?, updated_at = ? WHERE id = ?', [$roles, now_iso(), $id]);
    }

    public static function setGoogleId(int $id, string $sub, bool $emailVerified = true): void
    {
        $now = now_iso();
        Db::run(
            'UPDATE users SET google_id = ?, email_verified_at = COALESCE(email_verified_at, ?), updated_at = ? WHERE id = ?',
            [$sub, $emailVerified ? $now : null, $now, $id]
        );
    }

    public static function setStatus(int $id, string $status): void
    {
        Db::run('UPDATE users SET status = ?, updated_at = ? WHERE id = ?', [$status, now_iso(), $id]);
    }

    public static function isBlocked(?string $email, ?string $phone = null): bool
    {
        try {
            $email = strtolower(trim((string) $email));
            $phone = trim((string) $phone);
            if ($email !== '') {
                if (Db::fetch('SELECT id FROM account_blocks WHERE LOWER(email) = ?', [$email])) {
                    return true;
                }
            }
            if ($phone !== '' && !str_starts_with($phone, 'e:') && !str_starts_with($phone, 'x:')) {
                if (Db::fetch("SELECT id FROM account_blocks WHERE phone <> '' AND phone = ?", [$phone])) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            return false;
        }
        return false;
    }

    public static function block(array $user, int $adminId, string $reason): void
    {
        try {
            Db::fetch('SELECT id FROM account_blocks LIMIT 1');
        } catch (\Throwable $e) {
            \App\Core\Schema::install();
        }
        self::ensureBlockName();
        $email = strtolower(trim((string) ($user['email'] ?? '')));
        $phone = (string) ($user['phone'] ?? '');
        $name = trim((string) ($user['full_name'] ?? ''));
        if (str_starts_with($phone, 'e:') || str_starts_with($phone, 'x:')) {
            $phone = '';
        }
        if ($email === '') {
            $email = 'user:' . (int) $user['id'];
        }
        if (Db::fetch('SELECT id FROM account_blocks WHERE LOWER(email) = ?', [$email])) {
            try {
                Db::run(
                    'UPDATE account_blocks SET phone=?, name=?, user_id=?, reason=?, admin_id=?, created_at=? WHERE LOWER(email)=?',
                    [$phone !== '' ? $phone : null, $name !== '' ? $name : null, (int) $user['id'], $reason, $adminId, now_iso(), $email]
                );
            } catch (\Throwable $e) {
                Db::run(
                    'UPDATE account_blocks SET phone=?, user_id=?, reason=?, admin_id=?, created_at=? WHERE LOWER(email)=?',
                    [$phone !== '' ? $phone : null, (int) $user['id'], $reason, $adminId, now_iso(), $email]
                );
            }
            return;
        }
        try {
            Db::run(
                'INSERT INTO account_blocks (email, phone, name, user_id, reason, admin_id, created_at) VALUES (?,?,?,?,?,?,?)',
                [$email, $phone !== '' ? $phone : null, $name !== '' ? $name : null, (int) $user['id'], $reason, $adminId, now_iso()]
            );
        } catch (\Throwable $e) {
            Db::run(
                'INSERT INTO account_blocks (email, phone, user_id, reason, admin_id, created_at) VALUES (?,?,?,?,?,?)',
                [$email, $phone !== '' ? $phone : null, (int) $user['id'], $reason, $adminId, now_iso()]
            );
        }
    }

    public static function ensureBlockName(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            $col = Db::isMysql() ? 'VARCHAR(191) NULL' : 'TEXT';
            Db::exec('ALTER TABLE account_blocks ADD COLUMN name ' . $col);
        } catch (\Throwable $e) {
        }
        try {
            $rows = Db::fetchAll(
                "SELECT id, user_id FROM account_blocks WHERE (name IS NULL OR name = '') AND user_id IS NOT NULL"
            );
            foreach ($rows as $row) {
                $audit = Db::fetch(
                    "SELECT meta FROM admin_audit WHERE action = 'user.delete' AND target = ? ORDER BY id DESC LIMIT 1",
                    ['user:' . (int) $row['user_id']]
                );
                $meta = json_decode((string) ($audit['meta'] ?? ''), true) ?: [];
                $name = trim((string) ($meta['name'] ?? ''));
                if ($name === '' || strcasecmp($name, 'Deleted account') === 0) {
                    continue;
                }
                Db::run('UPDATE account_blocks SET name = ? WHERE id = ?', [$name, (int) $row['id']]);
            }
        } catch (\Throwable $e) {
        }
    }

    public static function unblock(array $user): void
    {
        try {
            $email = strtolower(trim((string) ($user['email'] ?? '')));
            $id = (int) $user['id'];
            if ($email !== '') {
                Db::run('DELETE FROM account_blocks WHERE LOWER(email) = ? OR user_id = ?', [$email, $id]);
                return;
            }
            Db::run('DELETE FROM account_blocks WHERE user_id = ?', [$id]);
        } catch (\Throwable $e) {
        }
    }

    public static function roles(array $user): array
    {
        return array_values(array_filter(explode(',', (string) $user['roles'])));
    }

    public static function public(array $user, ?array $profile = null): array
    {
        $profile = $profile ?? Profile::forUser((int) $user['id']);
        $roles = self::roles($user);
        $google = trim((string) ($user['google_id'] ?? '')) !== '';
        $pending = $roles === [] || $roles === ['pending'];
        $needs = $pending;
        return [
            'id'         => (int) $user['id'],
            'full_name'  => $user['full_name'],
            'initials'   => initials($user['full_name']),
            'phone'      => $user['phone'],
            'phone_fmt'  => format_phone($user['phone']),
            'phone_mask' => mask_phone($user['phone']),
            'email'      => $user['email'],
            'roles'      => $roles,
            'status'     => $user['status'],
            'headline'   => $profile['headline'] ?? null,
            'bio'        => $profile['bio'] ?? null,
            'state'      => $profile['state'] ?? null,
            'city'       => $profile['city'] ?? null,
            'country'    => $profile['country'] ?? null,
            'country_code' => $profile['country_code'] ?? null,
            'dob'        => $profile['dob'] ?? null,
            'gender'     => $profile['gender'] ?? null,
            'notify_sms' => (int) ($profile['notify_sms'] ?? 1),
            'notify_jobs'=> (int) ($profile['notify_jobs'] ?? 1),
            'notify_marketing' => (int) ($profile['notify_marketing'] ?? 0),
            'work_mode'  => $profile['work_mode'] ?? null,
            'skill'      => $profile['skill'] ?? null,
            'verified'   => \App\Services\TrustService::isApproved((int) $user['id']),
            'rating_avg' => (float) ($profile['rating_avg'] ?? 0),
            'review_count' => (int) ($profile['review_count'] ?? 0),
            'public_code'=> self::ensurePublicCode((int) $user['id']),
            'member_since' => substr((string) $user['created_at'], 0, 10),
            'google'     => $google,
            'needs_profile' => $needs,
        ];
    }
}
