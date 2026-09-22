<?php
declare(strict_types=1);

namespace App\Core;

/** AES-256-GCM for NUBAN / proof-doc names. Prefix `enc:v1:` so legacy plaintext still reads. */
final class Crypto
{
    public static function keyBytes(): string
    {
        $raw = (string) (getenv('APP_KEY') ?: Config::get('app_key', ''));
        if ($raw === '') {
            $file = dirname(__DIR__, 2) . '/storage/.app_key';
            if (!is_file($file)) {
                if (!is_dir(dirname($file))) {
                    mkdir(dirname($file), 0775, true);
                }
                file_put_contents($file, bin2hex(random_bytes(32)));
                @chmod($file, 0600);
            }
            $raw = trim((string) file_get_contents($file));
        }
        return hash('sha256', $raw, true);
    }

    public static function encrypt(string $plain): string
    {
        if ($plain === '' || str_starts_with($plain, 'enc:v1:')) {
            return $plain;
        }
        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($plain, 'aes-256-gcm', self::keyBytes(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($ct === false) {
            return $plain;
        }
        return 'enc:v1:' . base64_encode($iv . $tag . $ct);
    }

    public static function decrypt(string $value): string
    {
        if ($value === '' || !str_starts_with($value, 'enc:v1:')) {
            return $value;
        }
        $bin = base64_decode(substr($value, 7), true);
        if (!is_string($bin) || strlen($bin) < 29) {
            return '';
        }
        $iv  = substr($bin, 0, 12);
        $tag = substr($bin, 12, 16);
        $ct  = substr($bin, 28);
        $p = openssl_decrypt($ct, 'aes-256-gcm', self::keyBytes(), OPENSSL_RAW_DATA, $iv, $tag);
        return $p === false ? '' : $p;
    }

    public static function maskAccount(string $acct): string
    {
        $d = preg_replace('/\D/', '', self::decrypt($acct)) ?? '';
        if (strlen($d) < 4) {
            return '····';
        }
        return '····' . substr($d, -4);
    }
}
