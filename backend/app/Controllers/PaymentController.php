<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\TrustService;

final class PaymentController
{
    public static function initiate(Request $req, array $params = []): void
    {
        Response::json(PaymentService::initiate(Auth::id(), [
            'purpose'  => $req->str('purpose', 'order'),
            'method'   => $req->str('method', 'transfer'),
            'order_id' => $req->str('order_id') ?: $req->str('order'),
            'plan'     => $req->str('plan'),
        ]));
    }

    public static function status(Request $req, array $params = []): void
    {
        Response::json(PaymentService::status(Auth::id(), $params['id'] ?? ''));
    }

    public static function receipt(Request $req, array $params = []): void
    {
        $pdf = PaymentService::receiptPdf(Auth::id(), $params['id'] ?? '');
        Response::download($pdf['filename'], $pdf['body'], 'application/pdf');
    }

    public static function simulate(Request $req, array $params = []): void
    {
        Response::json(PaymentService::simulate(Auth::id(), $params['id'] ?? '', $req->str('result', 'success')));
    }

    public static function fromService(Request $req, array $params = []): void
    {
        $uid = Auth::requireRole('client');
        Response::json(OrderService::openFromService($uid, $req->str('service_id') ?: $req->str('service'), $req->str('pkg')));
    }

    public static function paystack(Request $req, array $params = []): void
    {
        $sig = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? null;
        Response::json(PaymentService::webhookPaystack($req->raw, is_string($sig) ? $sig : null));
    }

    public static function flutterwave(Request $req, array $params = []): void
    {
        $sig = $_SERVER['HTTP_VERIF_HASH'] ?? ($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? null);
        Response::json(PaymentService::webhookFlutterwave($req->raw, is_string($sig) ? $sig : null));
    }

    public static function verificationStatus(Request $req, array $params = []): void
    {
        Response::json(TrustService::status(Auth::id()));
    }

    public static function verificationSubmit(Request $req, array $params = []): void
    {
        Response::json(TrustService::submit(Auth::id(), [
            'full_name' => $req->str('full_name'),
            'id_type'   => $req->str('id_type'),
            'id_number' => $req->str('id_number'),
        ]));
    }

    public static function promotions(Request $req, array $params = []): void
    {
        Response::json(TrustService::promotions(Auth::id()));
    }

    public static function buyPromo(Request $req, array $params = []): void
    {
        $plan = $params['id'] ?? $req->str('plan', 'search');
        Response::json(PaymentService::initiate(Auth::id(), [
            'purpose' => 'promotion',
            'method'  => $req->str('method', 'transfer'),
            'plan'    => $plan,
        ]));
    }
}
