<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\OrderService;

final class OrderController
{
    public static function list(Request $req, array $params = []): void
    {
        $uid = Auth::id();
        Response::json(OrderService::list($uid, $req->q('role', 'any'), $req->q('status')));
    }

    public static function get(Request $req, array $params = []): void
    {
        Response::json(OrderService::get($params['id'] ?? '', Auth::id()));
    }

    public static function start(Request $req, array $params = []): void
    {
        Response::json(OrderService::start(Auth::requireRole('worker'), $params['id'] ?? ''));
    }

    public static function submit(Request $req, array $params = []): void
    {
        Response::json(OrderService::submit(Auth::requireRole('worker'), $params['id'] ?? '', $req->str('note')));
    }

    public static function approve(Request $req, array $params = []): void
    {
        Response::json(OrderService::approve(Auth::requireRole('client'), $params['id'] ?? ''));
    }

    public static function revision(Request $req, array $params = []): void
    {
        Response::json(OrderService::revision(Auth::requireRole('client'), $params['id'] ?? ''));
    }

    public static function cancel(Request $req, array $params = []): void
    {
        Response::json(OrderService::cancel(Auth::requireRole('client'), $params['id'] ?? ''));
    }

    public static function review(Request $req, array $params = []): void
    {
        Response::json(OrderService::review(
            Auth::id(),
            $params['id'] ?? '',
            $req->int('rating'),
            $req->str('comment'),
            $req->array('tags')
        ));
    }

    public static function clientDashboard(Request $req, array $params = []): void
    {
        Response::json(OrderService::clientDashboard(Auth::requireRole('client')));
    }

    public static function workerDashboard(Request $req, array $params = []): void
    {
        Response::json(OrderService::workerDashboard(Auth::requireRole('worker')));
    }

    public static function myProposals(Request $req, array $params = []): void
    {
        Response::json(OrderService::myProposals(Auth::requireRole('worker')));
    }

    public static function dispute(Request $req, array $params = []): void
    {
        Response::json(\App\Services\DisputeService::open(
            Auth::id(),
            $params['id'] ?? '',
            $req->str('reason') ?: $req->str('reason_type'),
            $req->str('description') ?: $req->str('body')
        ));
    }
}
