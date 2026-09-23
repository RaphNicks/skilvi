<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

final class MailGateway
{
    /** @var array{ok:bool,driver:string,error:?string} */
    public static array $last = ['ok' => false, 'driver' => 'console', 'error' => null];

    public static function send(string $to, string $subject, string $body, ?string $code = null): bool
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
        self::$last = ['ok' => false, 'driver' => $driver, 'error' => null];

        if ($driver === 'console') {
            self::fail($dir, 'console — PHP is not sending SMTP. MAIL_HOST empty or not loaded. Restart PHP after saving backend/.env');
            error_log('SKILVI MAIL ' . trim($line) . ' code=' . ($code ?? ''));
            return false;
        }
        if (Config::isDev()) {
            error_log('SKILVI MAIL ' . trim($line) . ' code=' . ($code ?? ''));
        }
        if ($driver === 'mail') {
            $ok = @mail($to, $subject, $body, 'From: ' . self::fromHeader());
            if (!$ok) {
                self::fail($dir, 'PHP mail() returned false');
                return false;
            }
            self::$last = ['ok' => true, 'driver' => 'mail', 'error' => null];
            return true;
        }
        if ($driver === 'smtp') {
            return self::smtp($to, $subject, $body, $dir);
        }
        self::fail($dir, 'unknown MAIL_MAILER=' . $driver);
        return false;
    }

    private static function fail(string $dir, string $msg): void
    {
        self::$last = ['ok' => false, 'driver' => (string) (self::$last['driver'] ?? 'smtp'), 'error' => $msg];
        file_put_contents($dir . '/mail.log', '[' . gmdate('c') . '] FAIL ' . $msg . "\n", FILE_APPEND);
        error_log('SKILVI MAIL FAIL ' . $msg);
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
        $user = trim((string) Config::get('mail.user', ''));
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
        if ($addr === '' && str_contains($user, '@')) {
            $addr = $user;
        }
        return [$name, $addr];
    }

    private static function caFile(): ?string
    {
        foreach ([
            (string) ini_get('openssl.cafile'),
            'C:\\xampp\\apache\\bin\\curl-ca-bundle.crt',
            'C:\\xampp\\php\\extras\\ssl\\cacert.pem',
            '/etc/ssl/certs/ca-certificates.crt',
        ] as $p) {
            if ($p !== '' && is_file($p)) {
                return $p;
            }
        }
        return null;
    }

    private static function smtp(string $to, string $subject, string $body, string $dir): bool
    {
        $host = trim((string) Config::get('mail.host'));
        $port = (int) Config::get('mail.port', 587);
        $user = (string) Config::get('mail.user');
        $pass = (string) Config::get('mail.pass');
        $enc = strtolower(trim((string) Config::get('mail.encryption', '')));
        $scheme = strtolower(trim((string) Config::get('mail.scheme', '')));
        $ehlo = trim((string) Config::get('mail.ehlo', 'localhost')) ?: 'localhost';
        [$fromName, $fromAddr] = self::fromParts();
        if ($host === '') {
            self::fail($dir, 'MAIL_HOST empty');
            return false;
        }
        if ($fromAddr === '' || !str_contains($fromAddr, '@')) {
            self::fail($dir, 'Set MAIL_FROM_ADDRESS to an address your SMTP host allows');
            return false;
        }
        if ($user === '' || $pass === '') {
            self::fail($dir, 'MAIL_USERNAME or MAIL_PASSWORD empty');
            return false;
        }
        $from = $fromName !== '' ? sprintf('%s <%s>', $fromName, $fromAddr) : $fromAddr;
        $useSsl = in_array($enc, ['ssl', 'smtps'], true)
            || in_array($scheme, ['ssl', 'smtps'], true)
            || $port === 465;
        $useTls = in_array($enc, ['tls', 'starttls'], true)
            || (!$useSsl && ($port === 587 || $enc === ''));
        $remote = ($useSsl ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $ssl = [
            'verify_peer'      => false,
            'verify_peer_name' => false,
            'allow_self_signed'=> true,
            'crypto_method'    => STREAM_CRYPTO_METHOD_TLS_CLIENT,
        ];
        $ca = self::caFile();
        if ($ca) {
            $ssl['cafile'] = $ca;
            $ssl['verify_peer'] = true;
            $ssl['verify_peer_name'] = true;
            $ssl['allow_self_signed'] = false;
        }
        $ctx = stream_context_create(['ssl' => $ssl]);
        $fp = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp && $ca) {
            $ssl['verify_peer'] = false;
            $ssl['verify_peer_name'] = false;
            $ctx = stream_context_create(['ssl' => $ssl]);
            $fp = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
        }
        if (!$fp) {
            self::fail($dir, "cannot connect $remote ($errstr). Is MAIL_HOST/PORT right? Firewall?");
            return false;
        }
        stream_set_timeout($fp, 20);
        $log = static function (string $s) use ($dir): void {
            $s = trim($s);
            if ($s !== '') {
                file_put_contents($dir . '/mail.log', '  smtp: ' . $s . "\n", FILE_APPEND);
            }
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
        $read();
        $cmd('EHLO ' . $ehlo);
        if ($useTls) {
            $tls = $cmd('STARTTLS');
            if (!preg_match('/^220\b/m', $tls)) {
                fclose($fp);
                self::fail($dir, 'STARTTLS refused: ' . trim($tls));
                return false;
            }
            $ok = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if (!$ok) {
                fclose($fp);
                self::fail($dir, 'TLS handshake failed after STARTTLS');
                return false;
            }
            $cmd('EHLO ' . $ehlo);
        }
        $authOk = $cmd('AUTH LOGIN');
        if (str_starts_with(trim($authOk), '334')) {
            $cmd(base64_encode($user), true);
            $authOk = $cmd(base64_encode($pass), true);
        } else {
            $authOk = $cmd('AUTH PLAIN ' . base64_encode("\0" . $user . "\0" . $pass), true);
        }
        if (!preg_match('/^235\b/m', $authOk)) {
            fclose($fp);
            self::fail($dir, 'SMTP login rejected. Username/password or host does not match.');
            return false;
        }
        $fromResp = $cmd('MAIL FROM:<' . $fromAddr . '>');
        if (!preg_match('/^2/m', $fromResp)) {
            fclose($fp);
            self::fail($dir, 'MAIL FROM rejected (' . $fromAddr . '): ' . trim($fromResp));
            return false;
        }
        $rcpt = $cmd('RCPT TO:<' . $to . '>');
        if (!preg_match('/^2/m', $rcpt)) {
            fclose($fp);
            self::fail($dir, 'Recipient rejected: ' . trim($rcpt));
            return false;
        }
        $cmd('DATA');
        $msgid = '<skilvi-' . bin2hex(random_bytes(8)) . '@' . (explode('@', $fromAddr)[1] ?? 'localhost') . '>';
        $payload = 'Date: ' . gmdate('D, d M Y H:i:s') . " +0000\r\n"
            . 'From: ' . $from . "\r\n"
            . 'To: ' . $to . "\r\n"
            . 'Message-ID: ' . $msgid . "\r\n"
            . 'Subject: ' . $subject . "\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $body . "\r\n.\r\n";
        fwrite($fp, $payload);
        $data = $read();
        $cmd('QUIT');
        fclose($fp);
        if (!preg_match('/^2/m', $data)) {
            self::fail($dir, 'DATA rejected: ' . trim($data));
            return false;
        }
        self::$last = ['ok' => true, 'driver' => 'smtp', 'error' => null];
        file_put_contents($dir . '/mail.log', '[' . gmdate('c') . "] smtp sent to $to\n", FILE_APPEND);
        return true;
    }
}
