<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Db;

final class Profile
{
    public static function forUser(int $userId): ?array
    {
        return Db::fetch('SELECT * FROM profiles WHERE user_id = ?', [$userId]);
    }

    public static function update(int $userId, array $fields): void
    {
        $allowed = ['headline', 'bio', 'state', 'city', 'country', 'country_code', 'dob', 'gender', 'heard_about', 'avatar_path', 'notify_sms', 'notify_jobs', 'notify_marketing', 'work_mode', 'skill'];
        $set = [];
        $vals = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $fields)) {
                $set[] = "$k = ?";
                $vals[] = $fields[$k];
            }
        }
        if (!$set) {
            return;
        }
        $set[] = 'updated_at = ?';
        $vals[] = now_iso();
        $vals[] = $userId;
        Db::run('UPDATE profiles SET ' . implode(', ', $set) . ' WHERE user_id = ?', $vals);
    }
}
