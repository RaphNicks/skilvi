<?php
declare(strict_types=1);

namespace App\Services;

use App\AppError;
use App\Core\Config;

final class GoogleAuthService
{
    public static function enabled(): bool
    {
        return self::clientId() !== '' && self::clientSecret() !== '';
    }

    public static function clientId(): string
    {
        return trim((string) Config::get('google.client_id', ''));
    }

    private static function clientSecret(): string
    {
        return trim((string) Config::get('google.client_secret', ''));
    }

    public static function redirectUri(): string
    {
        return self::publicOrigin() . '/api/auth/google/callback';
    }

    public static function authorizeUrl(string $state): string
    {
        if (!self::enabled()) {
            throw new AppError('config', 'Google sign-in is not configured on this server.', 503);
        }
        $q = http_build_query([
            'client_id'     => self::clientId(),
            'redirect_uri'  => self::redirectUri(),
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
            'access_type'   => 'online',
            'prompt'        => 'select_account',
        ]);
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . $q;
    }

    /** @return array{sub:string,email:string,email_verified:bool,name:string,picture:?string} */
    public static function userFromCode(string $code): array
    {
        if (!self::enabled()) {
            throw new AppError('config', 'Google sign-in is not configured on this server.', 503);
        }
        $tok = self::http('POST', 'https://oauth2.googleapis.com/token', [
            'code'          => $code,
            'client_id'     => self::clientId(),
            'client_secret' => self::clientSecret(),
            'redirect_uri'  => self::redirectUri(),
            'grant_type'    => 'authorization_code',
        ], false);
        $access = (string) ($tok['access_token'] ?? '');
        if ($access === '') {
            throw new AppError('google', 'Google did not return an access token.', 502);
        }
        $info = self::http('GET', 'https://openidconnect.googleapis.com/v1/userinfo', null, true, $access);
        $email = strtolower(trim((string) ($info['email'] ?? '')));
        $sub = trim((string) ($info['sub'] ?? ''));
        $verified = !empty($info['email_verified']);
        if ($sub === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new AppError('google', 'Google did not return a verified email.', 400);
        }
        if (!$verified) {
            throw new AppError('google', 'That Google email is not verified. Use another account, or sign up with email.', 400);
        }
        $name = trim((string) ($info['name'] ?? ''));
        if ($name === '') {
            $name = trim((string) (($info['given_name'] ?? '') . ' ' . ($info['family_name'] ?? '')));
        }
        if (mb_strlen($name) < 2) {
            $name = explode('@', $email)[0];
        }
        return [
            'sub'            => $sub,
            'email'          => $email,
            'email_verified' => true,
            'name'           => mb_substr($name, 0, 80),
            'picture'        => isset($info['picture']) ? (string) $info['picture'] : null,
        ];
    }

    private static function publicOrigin(): string
    {
        $u = trim((string) Config::get('app_url', ''));
        if ($u !== '') {
            return rtrim($u, '/');
        }
        $fwd = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        $https = $fwd === 'https'
            || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8080');
        return ($https ? 'https://' : 'http://') . $host;
    }

    /** @param array<string,string>|null $form */
    private static function http(string $method, string $url, ?array $form, bool $json = true, string $bearer = ''): array
    {
        $payload = $form !== null ? http_build_query($form) : null;
        $headers = ['Accept: application/json'];
        if ($bearer !== '') {
            $headers[] = 'Authorization: Bearer ' . $bearer;
        }
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        $raw = '';
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $opts = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => $method,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_TIMEOUT        => 25,
            ];
            if ($payload !== null) {
                $opts[CURLOPT_POSTFIELDS] = $payload;
            }
            curl_setopt_array($ch, $opts);
            $raw = (string) curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);
            if ($raw === '' && $err !== '') {
                throw new AppError('google', 'Could not reach Google. Check this machine can access the internet.', 502);
            }
        } else {
            $hdr = implode("\r\n", $headers) . "\r\n";
            $ctx = stream_context_create([
                'http' => [
                    'method'        => $method,
                    'header'        => $hdr,
                    'content'       => $payload ?? '',
                    'timeout'       => 25,
                    'ignore_errors' => true,
                ],
            ]);
            $raw = (string) file_get_contents($url, false, $ctx);
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new AppError('google', 'Google returned an unexpected response.', 502);
        }
        if (!empty($decoded['error'])) {
            $msg = (string) ($decoded['error_description'] ?? $decoded['error']);
            throw new AppError('google', 'Google sign-in failed. Try again.', 400);
        }
        return $decoded;
    }
}
