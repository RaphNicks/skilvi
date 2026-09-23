<?php
declare(strict_types=1);

$e = function_exists('skilvi_env') ? 'skilvi_env' : static fn (string $k, string $d = '') => (getenv($k) !== false && getenv($k) !== '') ? (string) getenv($k) : $d;

$env = $e('APP_ENV', 'dev');

$parent = dirname(__DIR__); // backend/
$repo   = dirname($parent);  // GitHub clone root, or /home/user in the sandbox
$frontend = $e('FRONTEND_ROOT');
if ($frontend === '') {
    if (is_file($repo . DIRECTORY_SEPARATOR . 'index.html')) {
        $frontend = $repo;
    } elseif (is_file($repo . DIRECTORY_SEPARATOR . 'skilvi-frontend' . DIRECTORY_SEPARATOR . 'index.html')) {
        $frontend = $repo . DIRECTORY_SEPARATOR . 'skilvi-frontend';
    } else {
        $frontend = $repo;
    }
}

$host = $e('MAIL_HOST');
$mailer = $e('MAIL_MAILER', $e('MAIL_DRIVER', $host !== '' ? 'smtp' : 'console'));

return [
    'app_env'  => $env,
    'app_name' => 'Skilvi',
    'app_key'  => $e('APP_KEY'),
    'frontend_root' => $frontend,

    'db' => [
        'driver' => $e('DB_DRIVER', $e('SQLITE_PATH') !== '' ? 'sqlite' : 'mysql'),
        'sqlite_path' => $e('SQLITE_PATH', dirname(__DIR__) . '/storage/skilvi.sqlite'),
        'mysql' => [
            'host'     => $e('DB_HOST', '127.0.0.1'),
            'port'     => (int) $e('DB_PORT', '3306'),
            'database' => $e('DB_NAME', 'skilvi'),
            'user'     => $e('DB_USER', 'root'),
            'pass'     => $e('DB_PASS'),
        ],
    ],

    'session' => [
        'name'   => 'skilvi_sid',
        'lifetime' => 30 * 86400,
        'secure' => $env !== 'dev',
    ],

    'otp' => [
        'ttl'           => 600,
        'attempts'      => 5,
        'resend_lock'   => 60,
        'max_per_phone_15m' => 3,
        'max_per_ip_hour'   => 10,
        'claim_ttl'   => 900,
    ],

    'sms' => [
        'driver' => $e('SMS_DRIVER', 'console'),
        'daily_budget' => (int) $e('SMS_DAILY_BUDGET', '200'),
        'termii' => [
            'key'    => $e('TERMII_KEY'),
            'sender' => $e('TERMII_SENDER', 'Skilvi'),
        ],
        'africastalking' => [
            'key'    => $e('AT_KEY'),
            'user'   => $e('AT_USER'),
            'sender' => $e('AT_SENDER', 'Skilvi'),
        ],
    ],

    'mail' => [
        'driver'       => $mailer,
        'scheme'       => $e('MAIL_SCHEME'),
        'encryption'   => $e('MAIL_ENCRYPTION'),
        'host'         => $host,
        'port'         => (int) $e('MAIL_PORT', '587'),
        'user'         => $e('MAIL_USERNAME', $e('MAIL_USER')),
        'pass'         => $e('MAIL_PASSWORD', $e('MAIL_PASS')),
        'from_name'    => $e('MAIL_FROM_NAME', 'Skilvi'),
        'from_address' => $e('MAIL_FROM_ADDRESS'),
        'from'         => $e('MAIL_FROM'),
        'ehlo'         => $e('MAIL_EHLO_DOMAIN', 'skilvi.ng'),
    ],

    'files' => [
        'upload_dir' => dirname(__DIR__) . '/storage/uploads',
        'max_bytes'  => 5 * 1024 * 1024,
    ],

    'paystack' => [
        'secret'  => $e('PAYSTACK_SECRET'),
        'public'  => $e('PAYSTACK_PUBLIC'),
        'webhook' => $e('PAYSTACK_WEBHOOK', 'skilvi-dev-webhook'),
    ],

    'fees' => [
        'percent' => 10,
        'verification_kobo' => 500000,
        'promo_search_kobo' => 250000,
        'promo_category_kobo' => 500000,
        'promo_days' => 7,
        'min_withdrawal_kobo' => 500000,
    ],
];
