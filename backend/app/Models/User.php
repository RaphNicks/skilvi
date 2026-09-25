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
            'INSERT INTO users (phone, email, password_hash, full_name, roles, status, phone_verified_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
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
        return $id;
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

    public static function setStatus(int $id, string $status): void
    {
        Db::run('UPDATE users SET status = ?, updated_at = ? WHERE id = ?', [$status, now_iso(), $id]);
    }

    public static function roles(array $user): array
    {
        return array_values(array_filter(explode(',', (string) $user['roles'])));
    }

    public static function public(array $user, ?array $profile = null): array
    {
        $profile = $profile ?? Profile::forUser((int) $user['id']);
        $roles = self::roles($user);
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
            'verified'   => (int) ($profile['verified'] ?? 0) === 1,
            'rating_avg' => (float) ($profile['rating_avg'] ?? 0),
            'review_count' => (int) ($profile['review_count'] ?? 0),
            'public_code'=> $profile['public_code'] ?? null,
            'member_since' => substr((string) $user['created_at'], 0, 10),
        ];
    }
}
