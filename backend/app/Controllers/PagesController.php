<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\User;

final class PagesController
{
    public static function serve(Request $req): void
    {
        $root = (string) Config::get('frontend_root');
        $path = $req->path;
        if ($path === '/') {
            $path = '/index.html';
        }
        $path = str_replace('\\', '/', $path);
        if (str_contains($path, '..')) {
            self::notFound();
        }

        $rel = ltrim($path, '/');
        $candidates = [$rel];
        if (!str_ends_with($rel, '.html')) {
            $candidates[] = $rel . '.html';
            $candidates[] = $rel . '/index.html';
        }

        foreach ($candidates as $c) {
            $full = $root . '/' . $c;
            if (is_file($full) && str_ends_with($full, '.html')) {
                $html = (string) file_get_contents($full);
                $html = self::inject($html, $c);
                Response::html($html);
            }
        }
        self::notFound();
    }

    private static function inject(string $html, string $rel): string
    {
        $extra = '<script src="/js/api.js?v=32"></script><script src="/js/chrome.js?v=31"></script>';
        $pageScripts = [
            'index.html'             => '/js/discovery.js?v=31',
            'jobs.html'              => '/js/discovery.js?v=31',
            'search.html'            => '/js/discovery.js?v=31',
            'category.html'          => '/js/discovery.js?v=31',
            'job-detail.html'        => '/js/discovery.js?v=31',
            'worker-profile.html'    => '/js/discovery.js?v=31',
            'service-detail.html'    => '/js/discovery.js?v=31',
            'saved.html'             => '/js/discovery.js?v=31',
            'account-settings.html'  => '/js/account.js?v=31',
            'post-job.html'          => '/js/client.js?v=31',
            'client-dashboard.html'  => '/js/client.js?v=33',
            'order-detail.html'      => '/js/client.js?v=31',
            'review.html'            => '/js/client.js?v=31',
            'worker-jobs.html'       => '/js/client.js?v=31',
            'worker-orders.html'     => '/js/client.js?v=31',
            'worker-dashboard.html'  => '/js/worker.js?v=32',
            'worker-wallet.html'     => '/js/worker.js?v=32',
            'worker-services.html'   => '/js/worker.js?v=32',
            'worker-service-form.html' => '/js/worker.js?v=32',
            'checkout.html'          => '/js/pay.js?v=32',
            'payment-success.html'   => '/js/pay.js?v=32',
            'verification.html'      => '/js/pay.js?v=32',
            'promotion.html'         => '/js/pay.js?v=32',
            'disputes.html'          => '/js/comms.js?v=31',
            'dispute-detail.html'    => '/js/comms.js?v=31',
            'messages.html'          => '/js/comms.js?v=31',
            'notifications.html'     => '/js/comms.js?v=31',
        ];
        $gated = [
            'account-settings.html', 'saved.html', 'post-job.html',
            'client-dashboard.html', 'review.html', 'worker-jobs.html', 'worker-orders.html',
            'worker-dashboard.html', 'worker-wallet.html', 'worker-services.html', 'worker-service-form.html',
            'checkout.html', 'payment-success.html',
            'disputes.html', 'dispute-detail.html', 'messages.html', 'notifications.html',
            'verification.html', 'promotion.html',
        ];
        $workerOnly = [
            'worker-dashboard.html', 'worker-orders.html', 'worker-services.html',
            'worker-service-form.html', 'worker-jobs.html', 'worker-wallet.html',
            'verification.html', 'promotion.html',
        ];
        if (str_starts_with($rel, 'admin/')) {
            if (!Session::userId()) {
                Response::redirect('/login.html?next=/' . $rel);
            }
            $u = User::find((int) Session::userId());
            if ($u === null || !str_contains((string) $u['roles'], 'admin')) {
                Response::redirect('/client-dashboard.html');
            }
            $extra .= '<script src="/js/admin.js?v=34"></script>';
        }
        if (in_array($rel, $gated, true) && !Session::userId()) {
            Response::redirect('/login.html?next=/' . $rel);
        }
        if (in_array($rel, $workerOnly, true) && Session::userId()) {
            $u = User::find((int) Session::userId());
            $roles = (string) ($u['roles'] ?? '');
            if ($u === null || !str_contains($roles, 'worker')) {
                Response::redirect('/client-dashboard.html');
            }
        }
        if (isset($pageScripts[$rel])) {
            $extra .= '<script src="' . $pageScripts[$rel] . '"></script>';
        }
        if (str_contains($html, '</body>')) {
            return str_replace('</body>', $extra . "\n</body>", $html);
        }
        return $html . $extra;
    }

    private static function notFound(): never
    {
        $root = (string) Config::get('frontend_root');
        $file = $root . '/404.html';
        $html = is_file($file) ? (string) file_get_contents($file) : '<h1>Not found</h1>';
        Response::notFound($html);
    }
}
