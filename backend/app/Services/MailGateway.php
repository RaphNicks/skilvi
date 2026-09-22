<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

final class MailGateway
{
    public static function send(string $to, string $subject, string $body, ?string $code = null): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $line = sprintf("[%s] to=%s subject=%s\n", gmdate('c'), $to, $subject);
        file_put_contents($dir . '/mail.log', $line . $body . "\n---\n", FILE_APPEND);
        if ($code !== null) {
            file_put_contents($dir . '/last_otp.json', json_encode([
                'channel' => 'email',
                'to'      => $to,
                'code'    => $code,
                'at'      => gmdate('c'),
                'message' => $body,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $driver = (string) Config::get('mail.driver', 'console');
        if ($driver === 'console' || Config::isDev()) {
            error_log('SKILVI MAIL ' . trim($line) . ' code=' . ($code ?? ''));
            if ($driver === 'console') {
                return;
            }
        }
        if ($driver === 'mail') {
            @mail($to, $subject, $body, 'From: ' . (Config::get('mail.from') ?: 'Skilvi <noreply@skilvi.ng>'));
            return;
        }
        if ($driver === 'smtp') {
            self::smtp($to, $subject, $body);
        }
    }

    private static function smtp(string $to, string $subject, string $body): void
    {
        $host = (string) Config::get('mail.host');
        $port = (int) Config::get('mail.port', 587);
        $user = (string) Config::get('mail.user');
        $pass = (string) Config::get('mail.pass');
        $from = (string) (Config::get('mail.from') ?: 'noreply@skilvi.ng');
        if ($host === '') {
            error_log('SKILVI MAIL smtp skipped — MAIL_HOST empty');
            return;
        }
        $remote = (($port === 465) ? 'ssl://' : '') . $host . ':' . $port;
        $fp = @stream_socket_client($remote, $errno, $errstr, 12);
        if (!$fp) {
            error_log("SKILVI MAIL smtp connect failed: $errstr");
            return;
        }
        $read = static function () use ($fp): string {
            $out = '';
            while (!feof($fp)) {
                $line = fgets($fp, 2048);
                if ($line === false) {
                    break;
                }
                $out .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $out;
        };
        $cmd = static function (string $c) use ($fp, $read): string {
            fwrite($fp, $c . "\r\n");
            return $read();
        };
        $read();
        $cmd('EHLO skilvi.ng');
        if ($port === 587) {
            $cmd('STARTTLS');
            $ok = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($ok) {
                $cmd('EHLO skilvi.ng');
            }
        }
        if ($user !== '') {
            $cmd('AUTH LOGIN');
            $cmd(base64_encode($user));
            $cmd(base64_encode($pass));
        }
        $fromAddr = $from;
        if (preg_match('/<([^>]+)>/', $from, $m)) {
            $fromAddr = $m[1];
        }
        $cmd('MAIL FROM:<' . $fromAddr . '>');
        $cmd('RCPT TO:<' . $to . '>');
        $cmd('DATA');
        fwrite($fp, "From: $from\r\nTo: $to\r\nSubject: $subject\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n$body\r\n.\r\n");
        $read();
        $cmd('QUIT');
        fclose($fp);
    }
}
