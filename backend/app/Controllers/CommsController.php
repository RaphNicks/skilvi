<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\DisputeService;
use App\Services\MessageService;
use App\Services\NotificationService;
use App\Services\TrustService;

final class CommsController
{
    public static function disputeOpen(Request $req, array $params = []): void
    {
        Response::json(DisputeService::open(
            Auth::id(),
            $params['id'] ?? $req->str('order_id'),
            $req->str('reason') ?: $req->str('reason_type'),
            $req->str('description') ?: $req->str('body')
        ));
    }

    public static function disputes(Request $req, array $params = []): void
    {
        Response::json(DisputeService::list(Auth::id()));
    }

    public static function disputeGet(Request $req, array $params = []): void
    {
        $uid = Auth::id();
        $admin = str_contains((string) Auth::user()['roles'], 'admin');
        Response::json(DisputeService::get($uid, $params['id'] ?? '', $admin));
    }

    public static function disputeReply(Request $req, array $params = []): void
    {
        Response::json(DisputeService::reply(Auth::id(), $params['id'] ?? '', $req->str('body') ?: $req->str('message')));
    }

    public static function disputeResolve(Request $req, array $params = []): void
    {
        Response::json(DisputeService::resolve(
            Auth::requireRole('admin'),
            $params['id'] ?? '',
            $req->str('decision') ?: $req->str('action'),
            $req->int('worker_pct', $req->int('percent')),
            $req->str('note') ?: $req->str('reason')
        ));
    }

    public static function adminDisputes(Request $req, array $params = []): void
    {
        Auth::requireRole('admin');
        Response::json(DisputeService::adminList());
    }

    public static function messages(Request $req, array $params = []): void
    {
        Response::json(MessageService::list(Auth::id()));
    }

    public static function messageGet(Request $req, array $params = []): void
    {
        Response::json(MessageService::get(Auth::id(), $params['id'] ?? ''));
    }

    public static function messageSend(Request $req, array $params = []): void
    {
        Response::json(MessageService::send(Auth::id(), $params['id'] ?? '', $req->str('body') ?: $req->str('message')));
    }

    public static function orderMessage(Request $req, array $params = []): void
    {
        $uid = Auth::id();
        $conv = MessageService::ensureForOrder($params['id'] ?? '');
        if ($req->str('body') !== '' || $req->str('message') !== '') {
            Response::json(MessageService::send($uid, (string) $conv['id'], $req->str('body') ?: $req->str('message')));
        }
        Response::json(MessageService::get($uid, (string) $conv['id']));
    }

    public static function notifications(Request $req, array $params = []): void
    {
        Response::json(NotificationService::list(Auth::id()));
    }

    public static function notificationsRead(Request $req, array $params = []): void
    {
        Response::json(NotificationService::readAll(Auth::id()));
    }

    public static function badges(Request $req, array $params = []): void
    {
        Response::json(NotificationService::badges(Auth::id()));
    }

    public static function verificationReview(Request $req, array $params = []): void
    {
        Response::json(TrustService::review(
            Auth::requireRole('admin'),
            $params['id'] ?? '',
            $req->str('action') ?: $req->str('decision'),
            $req->str('reason') ?: $req->str('note')
        ));
    }
}
