<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\OrderService;
use App\Services\ServiceCatalog;
use App\Services\WalletService;

final class WorkerController
{
    public static function dashboard(Request $req, array $params = []): void
    {
        Response::json(OrderService::workerDashboard(Auth::requireRole('worker')));
    }

    public static function wallet(Request $req, array $params = []): void
    {
        $uid = Auth::requireRole('worker');
        Response::json([
            'wallet'       => WalletService::get($uid),
            'withdrawals'  => WalletService::list($uid),
            'transactions' => WalletService::transactions($uid),
        ]);
    }

    public static function ledger(Request $req, array $params = []): void
    {
        Response::json(WalletService::transactions(Auth::requireRole('worker')));
    }

    public static function requestWithdraw(Request $req, array $params = []): void
    {
        $uid = Auth::requireRole('worker');
        if ($req->str('code') !== '') {
            Response::json(WalletService::confirm($uid, $req->str('code')));
        }
        Response::json(WalletService::start($uid, $req->input, $req->ip()));
    }

    public static function startWithdraw(Request $req, array $params = []): void
    {
        Response::json(WalletService::start(Auth::requireRole('worker'), $req->input, $req->ip()));
    }

    public static function confirmWithdraw(Request $req, array $params = []): void
    {
        Response::json(WalletService::confirm(Auth::requireRole('worker'), $req->str('code')));
    }

    public static function withdrawals(Request $req, array $params = []): void
    {
        Response::json(WalletService::list(Auth::requireRole('worker')));
    }

    public static function payWithdraw(Request $req, array $params = []): void
    {
        $admin = Auth::requireRole('admin');
        Response::json(WalletService::adminAction($admin, $params['id'] ?? '', $req->str('action', 'paid')));
    }

    public static function adminWithdrawals(Request $req, array $params = []): void
    {
        Auth::requireRole('admin');
        Response::json(WalletService::adminList());
    }

    public static function servicesIndex(Request $req, array $params = []): void
    {
        if ($req->q('owner') !== 'me') {
            throw new \App\AppError('invalid', 'Use ?owner=me for your services, or /api/services/{id} for a public listing.', 422);
        }
        Response::json(ServiceCatalog::mine(Auth::requireRole('worker')));
    }

    public static function services(Request $req, array $params = []): void
    {
        Response::json(ServiceCatalog::mine(Auth::requireRole('worker')));
    }

    public static function serviceGet(Request $req, array $params = []): void
    {
        Response::json(ServiceCatalog::getOwned(Auth::requireRole('worker'), $params['id'] ?? ''));
    }

    public static function serviceCreate(Request $req, array $params = []): void
    {
        $uid = Auth::requireRole('worker');
        Response::json(ServiceCatalog::create($uid, $req->input, $req->bool('draft')));
    }

    public static function serviceUpdate(Request $req, array $params = []): void
    {
        Response::json(ServiceCatalog::update(Auth::requireRole('worker'), $params['id'] ?? '', $req->input));
    }

    public static function servicePause(Request $req, array $params = []): void
    {
        Response::json(ServiceCatalog::pause(Auth::requireRole('worker'), $params['id'] ?? ''));
    }
}
