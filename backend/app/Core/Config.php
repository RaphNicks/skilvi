<?php
declare(strict_types=1);

namespace App\Core;

final class Config
{
    private static ?array $data = null;

    public static function all(): array
    {
        if (self::$data === null) {
            self::$data = require dirname(__DIR__, 2) . '/config/env.php';
        }
        return self::$data;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $data = self::all();
        foreach (explode('.', $key) as $part) {
            if (!is_array($data) || !array_key_exists($part, $data)) {
                return $default;
            }
            $data = $data[$part];
        }
        return $data;
    }

    public static function isDev(): bool
    {
        return self::get('app_env') === 'dev';
    }
}
