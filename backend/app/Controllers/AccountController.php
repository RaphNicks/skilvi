<?php
declare(strict_types=1);

namespace App\Controllers;

use App\AppError;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuthService;

final class AccountController
{
    public static function requireUser(): int
    {
        return \App\Core\Auth::id();
    }

    public static function update(Request $req, array $params = []): void
    {
        $id = self::requireUser();
        Response::json(AuthService::updateProfile($id, $req->input));
    }

    public static function password(Request $req, array $params = []): void
    {
        $id = self::requireUser();
        AuthService::updatePassword($id, $req->str('current'), $req->str('password'));
        Response::json(['updated' => true]);
    }

    public static function email(Request $req, array $params = []): void
    {
        $id = self::requireUser();
        Response::json(AuthService::updateEmail($id, $req->str('email')));
    }

    public static function phone(Request $req, array $params = []): void
    {
        $id = self::requireUser();
        Response::json(AuthService::updatePhone($id, $req->str('phone')));
    }

    public static function export(Request $req, array $params = []): void
    {
        $id = self::requireUser();
        Response::json(AuthService::export($id));
    }

    public static function consent(Request $req, array $params = []): void
    {
        $id = self::requireUser();
        Response::json(AuthService::consent($id));
    }

    public static function requestDeletion(Request $req, array $params = []): void
    {
        $id = self::requireUser();
        Response::json(AuthService::requestDeletion($id));
    }

    public static function deactivate(Request $req, array $params = []): void
    {
        $id = self::requireUser();
        Response::json(AuthService::deactivate($id));
    }

    public static function avatar(Request $req, array $params = []): void
    {
        $id = self::requireUser();
        $file = $req->files['avatar'] ?? $req->files['file'] ?? null;
        if (!is_array($file)) {
            throw new AppError('invalid', 'Attach a JPG, PNG or WebP as `avatar`.', 422);
        }
        Response::json(\App\Services\UploadService::avatar($id, $file));
    }

    public static function file(Request $req, array $params = []): void
    {
        $id = (int) (\App\Core\Auth::resolvedId() ?? 0);
        $admin = false;
        if ($id) {
            $u = \App\Models\User::find($id);
            $admin = $u !== null && str_contains((string) $u['roles'], 'admin');
        }
        \App\Services\UploadService::stream((string) ($params['token'] ?? ''), $id, $admin);
    }
}
