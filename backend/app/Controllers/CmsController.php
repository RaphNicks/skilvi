<?php
declare(strict_types=1);

namespace App\Controllers;

use App\AppError;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\CmsService;

final class CmsController
{
    private static function admin(): int
    {
        return Auth::requireRole('admin');
    }

    public static function adminGet(Request $req, array $params = []): void
    {
        self::admin();
        Response::json(CmsService::adminPayload());
    }

    public static function save(Request $req, array $params = []): void
    {
        $id = self::admin();
        $key = $req->str('key') ?: (string) ($params['key'] ?? '');
        Response::json(CmsService::save($id, $key, $req->str('value'), $req->ip()));
    }

    public static function revert(Request $req, array $params = []): void
    {
        $id = self::admin();
        $key = $req->str('key') ?: (string) ($params['key'] ?? '');
        Response::json(CmsService::revert($id, $key, $req->ip()));
    }

    public static function upload(Request $req, array $params = []): void
    {
        $id = self::admin();
        $key = $req->str('key') ?: (string) ($req->input['key'] ?? '');
        $file = $req->files['file'] ?? $req->files['media'] ?? null;
        if (!is_array($file)) {
            throw new AppError('invalid', 'Attach an image or video as `file`.', 422);
        }
        Response::json(CmsService::upload($id, $key, $file, $req->ip()));
    }
}
