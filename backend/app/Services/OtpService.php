<?php
declare(strict_types=1);

namespace App\Services;

use App\AppError;
use App\Core\Config;
use App\Core\Db;
use App\Core\RateLimit;

final class OtpService
{
    public static function issue(string $phone, string $purpose, string $ip): array
    {
        $cfg = Config::get('otp');
        $perPhone = RateLimit::hit('otp:dest:' . $phone, (int) $cfg['max_per_phone_15m'], 15 * 60);
        if (!$perPhone['ok']) {
            throw new AppError('rate_limited', 'Too many codes sent. Try again in ' . $perPhone['wait'] . 's.', 429);
        }
        $perIp = RateLimit::hit('otp:ip:' . $ip, (int) $cfg['max_per_ip_hour'], 3600);
        if (!$perIp['ok']) {
            throw new AppError('rate_limited', 'Too many codes requested from this network. Try again later.', 429);
        }

        $recent = Db::fetch(
            'SELECT created_at FROM otp_codes WHERE phone = ? AND purpose = ? ORDER BY id DESC LIMIT 1',
            [$phone, $purpose]
        );
        if ($recent && (time() - (int) $recent['created_at']) < (int) $cfg['resend_lock']) {
            $wait = (int) $cfg['resend_lock'] - (time() - (int) $recent['created_at']);
            throw new AppError('resend_lock', 'Wait ' . $wait . 's before requesting another code.', 429);
        }

        Db::run(
            'UPDATE otp_codes SET consumed_at = ? WHERE phone = ? AND purpose = ? AND consumed_at IS NULL',
            [time(), $phone, $purpose]
        );

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $hash = password_hash($code, PASSWORD_DEFAULT);
        $ttl = (int) $cfg['ttl'];
        Db::run(
            'INSERT INTO otp_codes (phone, purpose, code_hash, attempts, max_attempts, expires_at, ip, created_at)
             VALUES (?, ?, ?, 0, ?, ?, ?, ?)',
            [$phone, $purpose, $hash, (int) $cfg['attempts'], time() + $ttl, $ip, time()]
        );

        $msg = "Your Skilvi login code is $code.\n\nIt expires in " . ($ttl / 60) . " minutes. Do not share it with anyone.\n\nIf you did not try to sign in, ignore this email.";
        $mail = ['ok' => false, 'driver' => 'none', 'error' => null];
        if (otp_channel($phone) === 'email') {
            $to = str_starts_with($phone, 'e:') ? substr($phone, 2) : $phone;
            MailGateway::send($to, 'Your Skilvi login code', $msg, $code);
            $mail = MailGateway::$last;
        } else {
            SmsGateway::send($phone, $msg, $code);
        }

        $out = [
            'phone_mask' => mask_dest($phone),
            'channel'    => otp_channel($phone),
            'expires_in' => $ttl,
            'purpose'    => $purpose,
            'mail'       => $mail,
        ];
        if (Config::isDev()) {
            $out['dev_code'] = $code;
        }
        return $out;
    }

    public static function verify(string $phone, string $purpose, string $code): void
    {
        $row = Db::fetch(
            'SELECT * FROM otp_codes WHERE phone = ? AND purpose = ? AND consumed_at IS NULL ORDER BY id DESC LIMIT 1',
            [$phone, $purpose]
        );
        if ($row === null) {
            throw new AppError('otp_invalid', 'That code is invalid or has expired. Request a new one.', 401);
        }
        if ((int) $row['expires_at'] < time()) {
            throw new AppError('otp_expired', 'That code has expired. Request a new one.', 401);
        }
        if ((int) $row['attempts'] >= (int) $row['max_attempts']) {
            throw new AppError('otp_locked', 'Too many attempts. Request a new code.', 429);
        }
        Db::run('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?', [$row['id']]);
        if (!password_verify($code, $row['code_hash'])) {
            $left = (int) $row['max_attempts'] - (int) $row['attempts'] - 1;
            throw new AppError('otp_invalid', 'Wrong code. ' . max(0, $left) . ' attempts left.', 401);
        }
        Db::run('UPDATE otp_codes SET consumed_at = ? WHERE id = ?', [time(), $row['id']]);
    }
}
