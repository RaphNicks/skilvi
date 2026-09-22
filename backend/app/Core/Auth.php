<?php
declare(strict_types=1);

namespace App\Core;

use App\AppError;
use App\Models\User;

final class Auth
{
    public static function id(): int
    {
        $id = Session::userId();
        if ($id === null) {
            throw new AppError('unauth', 'Log in to continue.', 401);
        }
        return $id;
    }

    public static function user(): array
    {
        $u = User::find(self::id());
        if ($u === null) {
            throw new AppError('unauth', 'Log in to continue.', 401);
        }
        if (($u['status'] ?? 'active') !== 'active') {
            throw new AppError('suspended', 'This account is not active. Contact support.', 403);
        }
        return $u;
    }

    public static function requireRole(string $role): int
    {
        $u = self::user();
        if (!str_contains((string) $u['roles'], $role)) {
            throw new AppError('forbidden', 'This action needs a ' . $role . ' account.', 403);
        }
        return (int) $u['id'];
    }
}
