<?php
declare(strict_types=1);

namespace App\Controllers;

use App\AppError;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\DiscoveryService;

final class DiscoveryController
{
    public static function landing(Request $req, array $params = []): void
    {
        Response::json(DiscoveryService::landing());
    }

    public static function jobs(Request $req, array $params = []): void
    {
        Response::json(DiscoveryService::jobs([
            'mode'     => $req->q('mode'),
            'category' => $req->q('category'),
            'q'        => $req->q('q'),
            'page'     => $req->qInt('page', 1),
            'per'      => $req->qInt('per', 12),
        ]));
    }

    public static function job(Request $req, array $params = []): void
    {
        Response::json(DiscoveryService::job($params['id'] ?? ''));
    }

    public static function propose(Request $req, array $params = []): void
    {
        $uid = Session::userId();
        if ($uid === null) {
            throw new AppError('unauth', 'Log in as a worker to apply.', 401);
        }
        Response::json(DiscoveryService::propose(
            $uid,
            $params['id'] ?? '',
            $req->str('cover_note'),
            $req->int('bid_naira'),
            $req->int('days')
        ));
    }

    public static function workers(Request $req, array $params = []): void
    {
        Response::json(DiscoveryService::workers([
            'q'        => $req->q('q'),
            'state'    => $req->q('state'),
            'country'  => $req->q('country'),
            'mode'     => $req->q('mode'),
            'skill'    => $req->q('skill'),
            'parent'   => $req->q('parent'),
            'verified' => $req->q('verified'),
            'rating'   => $req->q('rating'),
            'sort'     => $req->q('sort'),
            'page'     => $req->qInt('page', 1),
            'per'      => $req->qInt('per', 12),
        ]));
    }

    public static function worker(Request $req, array $params = []): void
    {
        Response::json(DiscoveryService::worker($params['id'] ?? ''));
    }

    public static function service(Request $req, array $params = []): void
    {
        Response::json(DiscoveryService::service($params['id'] ?? ''));
    }

    public static function categories(Request $req, array $params = []): void
    {
        Response::json(DiscoveryService::categories());
    }

    public static function category(Request $req, array $params = []): void
    {
        Response::json(DiscoveryService::category($params['slug'] ?? $req->q('c')));
    }

    public static function saved(Request $req, array $params = []): void
    {
        $uid = Session::userId();
        if ($uid === null) {
            throw new AppError('unauth', 'Log in to continue.', 401);
        }
        Response::json(DiscoveryService::saved($uid));
    }

    public static function save(Request $req, array $params = []): void
    {
        $uid = Session::userId();
        if ($uid === null) {
            throw new AppError('unauth', 'Log in to continue.', 401);
        }
        Response::json(DiscoveryService::save($uid, $params['id'] ?? '', $req->str('note') ?: null));
    }

    public static function unsave(Request $req, array $params = []): void
    {
        $uid = Session::userId();
        if ($uid === null) {
            throw new AppError('unauth', 'Log in to continue.', 401);
        }
        Response::json(DiscoveryService::unsave($uid, $params['id'] ?? ''));
    }
}
