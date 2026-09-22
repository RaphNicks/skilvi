<?php
declare(strict_types=1);

$env = getenv('APP_ENV') ?: 'dev';

$parent = dirname(__DIR__); // backend/
$repo   = dirname($parent);  // GitHub clone root, or /home/user in the sandbox
$frontend = getenv('FRONTEND_ROOT');
if (!$frontend) {
    if (is_file($repo . DIRECTORY_SEPARATOR . 'index.html')) {
        $frontend = $repo;
    } elseif (is_file($repo . DIRECTORY_SEPARATOR . 'skilvi-frontend' . DIRECTORY_SEPARATOR . 'index.html')) {
        $frontend = $repo . DIRECTORY_SEPARATOR . 'skilvi-frontend';
    } else {
        $frontend = $repo;
    }
}

return [
    'app_env'  => $env,
    'app_name' => 'Skilvi',
    'app_key'  => getenv('APP_KEY') ?: '',
    'frontend_root' => $frontend,

    'db' => [
        'driver' => getenv('DB_DRIVER') ?: 'sqlite',          // production: mysql
        'sqlite_path' => getenv('SQLITE_PATH') ?: dirname(__DIR__) . '/storage/skilvi.sqlite',
        'mysql' => [
            'host'     => getenv('DB_HOST') ?: '127.0.0.1',
            'port'     => (int) (getenv('DB_PORT') ?: 3306),
            'database' => getenv('DB_NAME') ?: 'skilvi',
            'user'     => getenv('DB_USER') ?: 'skilvi',
            'pass'     => getenv('DB_PASS') ?: '',
        ],
    ],

    'session' => [
        'name'   => 'skilvi_sid',
        'lifetime' => 30 * 86400,
        'secure' => $env !== 'dev',
    ],

    'otp' => [
        'ttl'           => 600,   // seconds (matches "expires in 10 minutes")
        'attempts'      => 5,
        'resend_lock'   => 60,    // seconds between sends
        'max_per_phone_15m' => 3,
        'max_per_ip_hour'   => 10,
        'claim_ttl'   => 900,     // pending register claim lifetime
    ],

    'sms' => [
        'driver' => getenv('SMS_DRIVER') ?: 'console',        // production: africastalking | termii
        'daily_budget' => (int) (getenv('SMS_DAILY_BUDGET') ?: 200),
        'africastalking' => [
            'key'    => getenv('AT_KEY') ?: '',
            'user'   => getenv('AT_USER') ?: '',
            'sender' => getenv('AT_SENDER') ?: 'Skilvi',
        ],
    ],

    'files' => [
        'upload_dir' => dirname(__DIR__) . '/storage/uploads',
        'max_bytes'  => 5 * 1024 * 1024,
    ],

    'paystack' => [
        'secret'  => getenv('PAYSTACK_SECRET') ?: '',
        'public'  => getenv('PAYSTACK_PUBLIC') ?: '',
        // HMAC secret for /api/webhooks/paystack. In dev the simulate endpoint signs with this.
        'webhook' => getenv('PAYSTACK_WEBHOOK') ?: 'skilvi-dev-webhook',
    ],

    'fees' => [
        'percent' => 10,
        'verification_kobo' => 500000, // ₦5,000 one-time identity check
        'promo_search_kobo' => 250000, // ₦2,500 / 7 days
        'promo_category_kobo' => 500000, // ₦5,000 / 7 days
        'promo_days' => 7,
        'min_withdrawal_kobo' => 500000, // ₦5,000
    ],
];
