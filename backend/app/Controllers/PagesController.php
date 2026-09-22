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
        $extra = '<script src="/js/api.js?v=19"></script><script src="/js/chrome.js?v=19"></script>';
        $pageScripts = [
            'index.html'             => '/js/discovery.js',
            'jobs.html'              => '/js/discovery.js',
            'search.html'            => '/js/discovery.js',
            'category.html'          => '/js/discovery.js',
            'job-detail.html'        => '/js/discovery.js',
            'worker-profile.html'    => '/js/discovery.js',
            'service-detail.html'    => '/js/discovery.js',
            'saved.html'             => '/js/discovery.js',
            'account-settings.html'  => '/js/account.js',
            'post-job.html'          => '/js/client.js',
            'client-dashboard.html'  => '/js/client.js',
            'order-detail.html'      => '/js/client.js',
            'review.html'            => '/js/client.js',
            'worker-jobs.html'       => '/js/client.js',
            'worker-orders.html'     => '/js/client.js',
            'worker-dashboard.html'  => '/js/worker.js',
            'worker-wallet.html'     => '/js/worker.js',
            'worker-services.html'   => '/js/worker.js',
            'worker-service-form.html' => '/js/worker.js',
            'checkout.html'          => '/js/pay.js',
            'payment-success.html'   => '/js/pay.js',
            'verification.html'      => '/js/pay.js',
            'promotion.html'         => '/js/pay.js',
            'disputes.html'          => '/js/comms.js',
            'dispute-detail.html'    => '/js/comms.js',
            'messages.html'          => '/js/comms.js',
            'notifications.html'     => '/js/comms.js',
        ];
        $gated = [
            'account-settings.html', 'saved.html', 'post-job.html',
            'client-dashboard.html', 'review.html', 'worker-jobs.html', 'worker-orders.html',
            'worker-dashboard.html', 'worker-wallet.html', 'worker-services.html', 'worker-service-form.html',
            'checkout.html', 'payment-success.html',
            'disputes.html', 'dispute-detail.html', 'messages.html', 'notifications.html',
        ];
        if (str_starts_with($rel, 'admin/')) {
            if (!Session::userId()) {
                Response::redirect('/login.html?next=/' . $rel);
            }
            $u = User::find((int) Session::userId());
            if ($u === null || !str_contains((string) $u['roles'], 'admin')) {
                Response::redirect('/client-dashboard.html');
            }
            $extra .= '<script src="/js/admin.js"></script>';
        }
        if (in_array($rel, $gated, true) && !Session::userId()) {
            Response::redirect('/login.html?next=/' . $rel);
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
