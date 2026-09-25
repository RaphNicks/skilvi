<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\AuthService;

final class AuthController
{
    public static function loginPage(Request $req, array $params = []): void
    {
        $me = null;
        if (Session::userId()) {
            try {
                $me = AuthService::me();
            } catch (\Throwable $e) {
                AuthService::logout();
                $me = null;
            }
        }
        Response::html(View::render('login', ['me' => $me]));
    }

    public static function logoutPage(Request $req, array $params = []): void
    {
        AuthService::logout();
        Response::redirect('/login.html');
    }

    public static function forgotPage(Request $req, array $params = []): void
    {
        Response::html(View::render('forgot-password'));
    }

    public static function register(Request $req, array $params = []): void
    {
        Response::json(AuthService::registerStart(
            $req->str('full_name'),
            $req->str('phone'),
            $req->str('email'),
            $req->str('password'),
            $req->str('join_as'),
            $req->ip(),
            [
                'country' => $req->str('country'),
                'state'   => $req->str('state'),
                'city'    => $req->str('city'),
                'dob'     => $req->str('dob'),
                'gender'  => $req->str('gender'),
            ]
        ));
    }

    public static function login(Request $req, array $params = []): void
    {
        Response::json(AuthService::loginStart(
            $req->str('identifier'),
            $req->str('password'),
            $req->ip()
        ));
    }

    public static function verify(Request $req, array $params = []): void
    {
        $code = preg_replace('/\D/', '', $req->str('code')) ?? '';
        Response::json(AuthService::verify($code, $req->str('purpose'), $req->ip()));
    }

    public static function resend(Request $req, array $params = []): void
    {
        Response::json(AuthService::resend($req->ip()));
    }

    public static function logout(Request $req, array $params = []): void
    {
        AuthService::logout();
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html')) {
            Response::redirect('/login.html');
        }
        Response::json(['ok' => true]);
    }

    public static function forgot(Request $req, array $params = []): void
    {
        Response::json(AuthService::forgot($req->str('identifier'), $req->ip()));
    }

    public static function reset(Request $req, array $params = []): void
    {
        $code = preg_replace('/\D/', '', $req->str('code')) ?? '';
        Response::json(AuthService::resetPassword($code, $req->str('password'), $req->ip()));
    }

    public static function me(Request $req, array $params = []): void
    {
        Response::json(AuthService::me());
    }

    public static function csrf(Request $req, array $params = []): void
    {
        Response::json(['csrf' => Session::csrf()]);
    }

    public static function health(Request $req, array $params = []): void
    {
        $db = 'ok';
        $maint = '0';
        try {
            \App\Core\Db::fetch('SELECT 1 AS x');
            $row = \App\Core\Db::fetch("SELECT value FROM settings WHERE key='maintenance'");
            $maint = (string) ($row['value'] ?? '0');
        } catch (\Throwable $e) {
            $db = 'down';
        }
        Response::json([
            'status'      => $db === 'ok' ? 'ok' : 'degraded',
            'time'        => gmdate('c'),
            'db'          => $db,
            'env'         => \App\Core\Config::get('app_env'),
            'maintenance' => $maint === '1',
            'sms_left'    => $db === 'ok' ? \App\Services\SmsGateway::remainingToday() : null,
        ]);
    }
}
