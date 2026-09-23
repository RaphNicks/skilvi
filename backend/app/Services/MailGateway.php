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

        $driver = strtolower(trim((string) Config::get('mail.driver', 'console')));
        $host = trim((string) Config::get('mail.host', ''));
        if ($driver === '' || $driver === 'log') {
            $driver = $host !== '' ? 'smtp' : 'console';
        }
        if ($driver === 'console' || Config::isDev()) {
            error_log('SKILVI MAIL ' . trim($line) . ' code=' . ($code ?? ''));
            if ($driver === 'console') {
                self::note($dir, 'driver=console — fill MAIL_HOST in backend/.env and restart PHP');
                return;
            }
        }
        if ($driver === 'mail') {
            @mail($to, $subject, $body, 'From: ' . self::fromHeader());
            return;
        }
        if ($driver === 'smtp') {
            self::smtp($to, $subject, $body, $dir);
        }
    }

    private static function note(string $dir, string $msg): void
    {
        file_put_contents($dir . '/mail.log', '[' . gmdate('c') . '] ' . $msg . "\n", FILE_APPEND);
        error_log('SKILVI MAIL ' . $msg);
    }

    private static function fromHeader(): string
    {
        [$name, $addr] = self::fromParts();
        return $name !== '' ? sprintf('%s <%s>', $name, $addr) : $addr;
    }

    /** @return array{0:string,1:string} */
    private static function fromParts(): array
    {
        $legacy = trim((string) Config::get('mail.from', ''));
        $name = trim((string) Config::get('mail.from_name', 'Skilvi'));
        $addr = trim((string) Config::get('mail.from_address', ''));
        if ($legacy !== '') {
            if (preg_match('/^\s*(.*?)\s*<([^>]+)>\s*$/', $legacy, $m)) {
                if ($name === '' || $name === 'Skilvi') {
                    $name = trim($m[1]);
                }
                if ($addr === '') {
                    $addr = trim($m[2]);
                }
            } elseif ($addr === '' && str_contains($legacy, '@')) {
                $addr = $legacy;
            }
        }
        if ($addr === '') {
            $addr = 'noreply@skilvi.ng';
        }
        return [$name, $addr];
    }

    private static function caFile(): ?string
    {
        $ini = (string) ini_get('openssl.cafile');
        $candidates = array_filter([
            $ini,
            'C:\\xampp\\apache\\bin\\curl-ca-bundle.crt',
            'C:\\xampp\\php\\extras\\ssl\\cacert.pem',
            '/etc/ssl/certs/ca-certificates.crt',
        ]);
        foreach ($candidates as $p) {
            if (is_string($p) && $p !== '' && is_file($p)) {
                return $p;
            }
        }
        return null;
    }

    private static function smtp(string $to, string $subject, string $body, string $dir): void
    {
        $host = trim((string) Config::get('mail.host'));
        $port = (int) Config::get('mail.port', 587);
        $user = (string) Config::get('mail.user');
        $pass = (string) Config::get('mail.pass');
        $enc = strtolower(trim((string) Config::get('mail.encryption', '')));
        $scheme = strtolower(trim((string) Config::get('mail.scheme', '')));
        $ehlo = trim((string) Config::get('mail.ehlo', 'localhost')) ?: 'localhost';
        [$fromName, $fromAddr] = self::fromParts();
        $from = $fromName !== '' ? sprintf('%s <%s>', $fromName, $fromAddr) : $fromAddr;
        if ($host === '') {
            self::note($dir, 'smtp skipped — MAIL_HOST empty');
            return;
        }
        $useSsl = in_array($enc, ['ssl', 'smtps'], true)
            || in_array($scheme, ['ssl', 'smtps'], true)
            || $port === 465;
        $useTls = in_array($enc, ['tls', 'starttls'], true)
            || (!$useSsl && $port === 587);
        $remote = ($useSsl ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $ssl = [
            'verify_peer'       => true,
            'verify_peer_name'  => true,
            'allow_self_signed' => false,
            'crypto_method'     => STREAM_CRYPTO_METHOD_TLS_CLIENT,
        ];
        $ca = self::caFile();
        if ($ca) {
            $ssl['cafile'] = $ca;
        }
        $ctx = stream_context_create(['ssl' => $ssl]);
        $fp = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            $ssl['verify_peer'] = false;
            $ssl['verify_peer_name'] = false;
            $ctx = stream_context_create(['ssl' => $ssl]);
            $fp = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
        }
        if (!$fp) {
            self::note($dir, "smtp connect failed ($remote): $errstr");
            return;
        }
        stream_set_timeout($fp, 20);
        $log = static function (string $s) use ($dir): void {
            $s = trim($s);
            if ($s === '') {
                return;
            }
            file_put_contents($dir . '/mail.log', '  smtp: ' . $s . "\n", FILE_APPEND);
        };
        $read = static function () use ($fp, $log): string {
            $out = '';
            while (!feof($fp)) {
                $line = fgets($fp, 2048);
                if ($line === false) {
                    break;
                }
                $out .= $line;
                $log('< ' . trim($line));
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $out;
        };
        $cmd = static function (string $c, bool $secret = false) use ($fp, $read, $log): string {
            $log('> ' . ($secret ? '[hidden]' : $c));
            fwrite($fp, $c . "\r\n");
            return $read();
        };
        $banner = $read();
        $hello = $cmd('EHLO ' . $ehlo);
        if ($useTls) {
            $cmd('STARTTLS');
            $crypto = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            $ok = @stream_socket_enable_crypto($fp, true, $crypto);
            if (!$ok) {
                self::note($dir, 'STARTTLS failed — check MAIL_ENCRYPTION / openssl.cafile in php.ini');
                fclose($fp);
                return;
            }
            $cmd('EHLO ' . $ehlo);
        }
        if ($user !== '') {
            $auth = $cmd('AUTH LOGIN');
            if (str_starts_with(trim($auth), '334')) {
                $cmd(base64_encode($user), true);
                $authOk = $cmd(base64_encode($pass), true);
            } else {
                $plain = base64_encode("\0" . $user . "\0" . $pass);
                $authOk = $cmd('AUTH PLAIN ' . $plain, true);
            }
            if (!preg_match('/^235\b/m', $authOk ?? '')) {
                self::note($dir, 'smtp login rejected — check MAIL_USERNAME / MAIL_PASSWORD');
                fclose($fp);
                return;
            }
        }
        $fromResp = $cmd('MAIL FROM:<' . $fromAddr . '>');
        $rcpt = $cmd('RCPT TO:<' . $to . '>');
        if (!preg_match('/^2/m', $rcpt)) {
            self::note($dir, 'recipient rejected: ' . trim($rcpt));
            fclose($fp);
            return;
        }
        $cmd('DATA');
        $payload = "From: $from\r\nTo: $to\r\nSubject: $subject\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n$body\r\n.\r\n";
        fwrite($fp, $payload);
        $data = $read();
        $cmd('QUIT');
        fclose($fp);
        if (!preg_match('/^2/m', $data) && !preg_match('/^2/m', $fromResp)) {
            self::note($dir, 'smtp send may have failed: ' . trim($data));
            return;
        }
        self::note($dir, 'smtp sent to ' . $to);
    }
}
