<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\JobService;

final class JobController
{
    public static function create(Request $req, array $params = []): void
    {
        $uid = Auth::requireRole('client');
        Response::json(JobService::create($uid, [
            'title'         => $req->str('title'),
            'description'   => $req->str('description'),
            'category'      => $req->str('category'),
            'budget_naira'  => $req->int('budget_naira'),
            'budget_type'   => $req->str('budget_type', 'fixed'),
            'work_mode'     => $req->str('work_mode'),
            'state'         => $req->str('state'),
            'city'          => $req->str('city'),
            'deadline'      => $req->str('deadline'),
            'max_proposals' => $req->int('max_proposals'),
        ]));
    }

    public static function mine(Request $req, array $params = []): void
    {
        Response::json(JobService::mine(Auth::requireRole('client')));
    }

    public static function close(Request $req, array $params = []): void
    {
        Response::json(JobService::close(Auth::requireRole('client'), $params['id'] ?? ''));
    }

    public static function cancel(Request $req, array $params = []): void
    {
        Response::json(JobService::cancel(Auth::requireRole('client'), $params['id'] ?? ''));
    }

    public static function shortlist(Request $req, array $params = []): void
    {
        Response::json(JobService::shortlist(Auth::requireRole('client'), (int) ($params['id'] ?? 0)));
    }

    public static function withdrawProposal(Request $req, array $params = []): void
    {
        Response::json(JobService::withdrawProposal(Auth::requireRole('worker'), (int) ($params['id'] ?? 0)));
    }

    public static function reject(Request $req, array $params = []): void
    {
        Response::json(JobService::reject(Auth::requireRole('client'), (int) ($params['id'] ?? 0)));
    }

    public static function accept(Request $req, array $params = []): void
    {
        Response::json(JobService::accept(Auth::requireRole('client'), (int) ($params['id'] ?? 0)));
    }
}
