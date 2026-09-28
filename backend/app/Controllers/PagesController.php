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
        $html = preg_replace('/skilvi\\.css\\?v=\\d+/', 'skilvi.css?v=36', $html) ?? $html;
        $html = preg_replace('/landing\\.css\\?v=\\d+/', 'landing.css?v=28', $html) ?? $html;
        $html = preg_replace('/skilvi\\.js\\?v=\\d+/', 'skilvi.js?v=22', $html) ?? $html;
        $boot = '<script>(function(){try{var p=localStorage.getItem("skilvi_theme")||"system";var dark=p==="dark"||(p!=="light"&&window.matchMedia("(prefers-color-scheme: dark)").matches);document.documentElement.setAttribute("data-theme",dark?"dark":"light");document.documentElement.setAttribute("data-theme-pref",p);}catch(e){}})();</script>'
            . '<style id="skLoaderCss">html.sk-loading{background:#F8FAFC}html[data-theme="dark"].sk-loading{background:#10141C}html.sk-loading #skLoader{display:flex}#skLoader{display:none;position:fixed;inset:0;z-index:99999;align-items:center;justify-content:center;background:#F8FAFC;pointer-events:none}html[data-theme="dark"] #skLoader{background:#10141C}#skLoader video{width:min(420px,86vw);height:auto;display:block}</style>'
            . '<script>(function(){try{if(sessionStorage.getItem("skilvi_nav")==="1")document.documentElement.classList.add("sk-loading")}catch(e){}})();</script>'
            . '<link rel="preload" href="/assets/video/skilvi-loader.webm" as="video" type="video/webm">';
        if (str_contains($html, '<head>')) {
            $html = preg_replace('/<head>/i', '<head>' . $boot, $html, 1) ?? $html;
        }
        $overlay = '<div id="skLoader" aria-hidden="true" role="status" aria-label="Loading"><video muted loop playsinline autoplay preload="auto"><source src="/assets/video/skilvi-loader.webm" type="video/webm"><source src="/assets/video/skilvi-loader.mp4" type="video/mp4"></video></div>';
        $html = preg_replace('/<body([^>]*)>/i', '<body$1>' . $overlay, $html, 1) ?? $html;
        $extra = '<script src="/js/loader.js?v=6"></script><script src="/js/theme.js?v=2"></script><script src="/js/api.js?v=39"></script><script src="/js/chrome.js?v=44"></script><script src="/js/geo.js?v=1"></script>';
        $pageScripts = [
            'index.html'             => '/js/discovery.js?v=31',
            'jobs.html'              => '/js/discovery.js?v=31',
            'search.html'            => '/js/discovery.js?v=32',
            'category.html'          => '/js/discovery.js?v=31',
            'job-detail.html'        => '/js/discovery.js?v=31',
            'worker-profile.html'    => '/js/discovery.js?v=34',
            'service-detail.html'    => '/js/discovery.js?v=31',
            'saved.html'             => '/js/discovery.js?v=31',
            'account-settings.html'  => '/js/account.js?v=32',
            'post-job.html'          => '/js/client.js?v=32',
            'client-dashboard.html'  => '/js/client.js?v=37',
            'order-detail.html'      => '/js/client.js?v=35',
            'review.html'            => '/js/client.js?v=37',
            'worker-jobs.html'       => '/js/client.js?v=31',
            'worker-orders.html'     => '/js/client.js?v=31',
            'worker-dashboard.html'  => '/js/worker.js?v=33',
            'worker-wallet.html'     => '/js/worker.js?v=33',
            'worker-services.html'   => '/js/worker.js?v=34',
            'worker-service-form.html' => '/js/worker.js?v=33',
            'checkout.html'          => '/js/pay.js?v=38',
            'payment-success.html'   => '/js/pay.js?v=38',
            'verification.html'      => '/js/pay.js?v=38',
            'promotion.html'         => '/js/pay.js?v=38',
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
            // Do not bounce staff to a client/worker dashboard. Cookie is shared
            // across tabs; this tab's account is the Bearer token in JS.
            $extra .= '<script src="/js/admin.js?v=46"></script>';
        }
        if (in_array($rel, $gated, true) && !Session::userId() && empty($_SERVER['HTTP_AUTHORIZATION']) && empty($_SERVER['HTTP_X_SKILVI_TOKEN'])) {
            Response::redirect('/login.html?next=/' . $rel);
        }
        // Worker-only HTML is gated in chrome.js. Do not bounce on the shared cookie.
        if (isset($pageScripts[$rel])) {
            $extra .= '<script src="' . $pageScripts[$rel] . '"></script>';
        }
        if (str_contains($html, '</body>')) {
            $html = str_replace('</body>', $extra . "\n</body>", $html);
        } else {
            $html .= $extra;
        }
        try {
            $html = \App\Services\CmsService::apply($html);
        } catch (\Throwable $e) {
            error_log('SKILVI CMS ' . $e->getMessage());
        }
        return $html;
    }

    private static function notFound(): never
    {
        $root = (string) Config::get('frontend_root');
        $file = $root . '/404.html';
        $html = is_file($file) ? (string) file_get_contents($file) : '<h1>Not found</h1>';
        if (is_file($file)) {
            $html = self::inject($html, '404.html');
        }
        Response::notFound($html);
    }
}
