<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\AdminService;
use App\Services\DisputeService;
use App\Services\TrustService;
use App\Services\WalletService;

final class AdminController
{
    private static function admin(): int
    {
        return Auth::requireRole('admin');
    }

    public static function dashboard(Request $req, array $params = []): void
    {
        self::admin();
        Response::json(AdminService::dashboard());
    }

    public static function users(Request $req, array $params = []): void
    {
        self::admin();
        Response::json(AdminService::users($req->q('q'), $req->q('role'), $req->q('status')));
    }

    public static function userGet(Request $req, array $params = []): void
    {
        self::admin();
        Response::json(AdminService::userGet($params['id'] ?? ''));
    }

    public static function userAction(Request $req, array $params = []): void
    {
        Response::json(AdminService::userAction(
            self::admin(),
            $params['id'] ?? '',
            $req->str('action') ?: $req->str('status'),
            $req->str('reason') ?: $req->str('note'),
            $req->ip()
        ));
    }

    public static function orders(Request $req, array $params = []): void
    {
        self::admin();
        Response::json(AdminService::orders($req->q('status'), $req->q('q')));
    }

    public static function orderAction(Request $req, array $params = []): void
    {
        Response::json(AdminService::orderAction(
            self::admin(),
            $params['id'] ?? '',
            $req->str('action'),
            $req->str('reason') ?: $req->str('note'),
            $req->ip()
        ));
    }

    public static function payments(Request $req, array $params = []): void
    {
        self::admin();
        Response::json(AdminService::payments($req->q('status'), $req->q('method'), $req->q('q')));
    }

    public static function verifications(Request $req, array $params = []): void
    {
        self::admin();
        Response::json(AdminService::verifications());
    }

    public static function verificationAction(Request $req, array $params = []): void
    {
        Response::json(TrustService::review(
            self::admin(),
            $params['id'] ?? '',
            $req->str('action') ?: $req->str('decision'),
            $req->str('reason') ?: $req->str('note')
        ));
    }

    public static function promotions(Request $req, array $params = []): void
    {
        self::admin();
        Response::json(AdminService::promotions());
    }

    public static function promoPause(Request $req, array $params = []): void
    {
        Response::json(AdminService::pausePromo(self::admin(), $params['id'] ?? '', $req->ip()));
    }

    public static function categories(Request $req, array $params = []): void
    {
        self::admin();
        Response::json(AdminService::categories());
    }

    public static function categoryAction(Request $req, array $params = []): void
    {
        Response::json(AdminService::categoryAction(
            self::admin(),
            $params['id'] ?? '',
            $req->str('action') ?: 'archive',
            $req->ip()
        ));
    }

    public static function reports(Request $req, array $params = []): void
    {
        self::admin();
        Response::json(AdminService::reports());
    }

    public static function reportAction(Request $req, array $params = []): void
    {
        Response::json(AdminService::reportAction(
            self::admin(),
            $params['id'] ?? '',
            $req->str('action'),
            $req->str('note') ?: $req->str('reason'),
            $req->ip()
        ));
    }

    public static function analytics(Request $req, array $params = []): void
    {
        self::admin();
        if ($req->q('format') === 'csv' || str_ends_with($req->path, '.csv')) {
            Response::csv('skilvi-report.csv', AdminService::analyticsCsv());
        }
        Response::json(AdminService::analytics());
    }

    public static function audit(Request $req, array $params = []): void
    {
        self::admin();
        Response::json(AdminService::auditLog($req->q('q')));
    }

    public static function settingsGet(Request $req, array $params = []): void
    {
        self::admin();
        Response::json(AdminService::settings());
    }

    public static function settingsSave(Request $req, array $params = []): void
    {
        Response::json(AdminService::saveSettings(self::admin(), $req->input, $req->ip()));
    }

    public static function support(Request $req, array $params = []): void
    {
        self::admin();
        Response::json(AdminService::tickets());
    }

    public static function supportGet(Request $req, array $params = []): void
    {
        self::admin();
        Response::json(AdminService::ticketGet((int) ($params['id'] ?? 0)));
    }

    public static function supportReply(Request $req, array $params = []): void
    {
        Response::json(AdminService::ticketReply(
            self::admin(),
            $params['id'] ?? '',
            $req->str('body') ?: $req->str('message'),
            $req->ip()
        ));
    }

    public static function withdrawals(Request $req, array $params = []): void
    {
        self::admin();
        Response::json(WalletService::adminList());
    }

    public static function withdrawAction(Request $req, array $params = []): void
    {
        Response::json(WalletService::adminAction(
            self::admin(),
            $params['id'] ?? '',
            $req->str('action', 'paid')
        ));
    }

    public static function disputes(Request $req, array $params = []): void
    {
        self::admin();
        Response::json(DisputeService::adminList());
    }

    public static function ledgerAudit(Request $req, array $params = []): void
    {
        self::admin();
        Response::json(\App\Services\LedgerAudit::run());
    }
}
