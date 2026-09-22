<?php
declare(strict_types=1);

namespace App\Services;

use App\AppError;
use App\Core\Config;
use App\Core\RateLimit;

final class SmsGateway
{
    public static function send(string $phone, string $message, ?string $code = null): void
    {
        $budget = (int) Config::get('sms.daily_budget', 200);
        $day = gmdate('Y-m-d');
        $hit = RateLimit::hit('sms:day:' . $day, max(1, $budget), 86400);
        if (!$hit['ok']) {
            throw new AppError('sms_budget', 'SMS budget for today is used up. Try email or wait until tomorrow.', 429, ['retry' => $hit['wait']]);
        }

        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $line = sprintf("[%s] to=%s msg=%s\n", gmdate('c'), $phone, $message);
        file_put_contents($dir . '/sms.log', $line, FILE_APPEND);

        if ($code !== null) {
            file_put_contents($dir . '/last_otp.json', json_encode([
                'phone'   => $phone,
                'code'    => $code,
                'at'      => gmdate('c'),
                'message' => $message,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $driver = Config::get('sms.driver', 'console');
        if ($driver === 'console') {
            error_log('SKILVI SMS ' . trim($line));
        }
        // africastalking / termii: set SMS_DRIVER + keys. Console is the sandbox default.
    }

    public static function remainingToday(): int
    {
        $budget = (int) Config::get('sms.daily_budget', 200);
        $day = gmdate('Y-m-d');
        $row = \App\Core\Db::fetch('SELECT hits FROM rate_limits WHERE bucket = ?', ['sms:day:' . $day]);
        $used = (int) ($row['hits'] ?? 0);
        return max(0, $budget - $used);
    }
}
