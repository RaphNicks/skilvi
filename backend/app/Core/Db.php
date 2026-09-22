<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

final class Db
{
    private static ?PDO $pdo = null;

    public static function driver(): string
    {
        return (string) Config::get('db.driver', 'mysql');
    }

    public static function isMysql(): bool
    {
        return self::driver() === 'mysql';
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $cfg = Config::get('db');
        if (self::isMysql()) {
            self::$pdo = self::connectMysql($cfg['mysql'] ?? []);
        } else {
            $path = $cfg['sqlite_path'];
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0775, true);
            }
            self::$pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            self::$pdo->exec('PRAGMA journal_mode=WAL');
            self::$pdo->exec('PRAGMA foreign_keys=ON');
        }
        return self::$pdo;
    }

    /** @param array<string,mixed> $m */
    private static function connectMysql(array $m): PDO
    {
        $host = (string) ($m['host'] ?? '127.0.0.1');
        $port = (int) ($m['port'] ?? 3306);
        $name = (string) ($m['database'] ?? 'skilvi');
        $user = (string) ($m['user'] ?? 'root');
        $pass = (string) ($m['pass'] ?? '');
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO(
                "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
                $user,
                $pass,
                $opts
            );
        } catch (\PDOException $e) {
            $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, $opts);
            $safe = '`' . str_replace('`', '', $name) . '`';
            $pdo->exec("CREATE DATABASE IF NOT EXISTS {$safe} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE {$safe}");
        }
        $pdo->exec('SET NAMES utf8mb4');
        return $pdo;
    }

    public static function dialect(string $sql): string
    {
        if (!self::isMysql()) {
            return $sql;
        }
        $trim = ltrim($sql);
        if (str_starts_with(strtoupper($trim), 'PRAGMA')) {
            return '';
        }
        $sql = str_ireplace('INSERT OR IGNORE INTO', 'INSERT IGNORE INTO', $sql);
        $sql = str_ireplace('INSERT OR REPLACE INTO', 'REPLACE INTO', $sql);
        $sql = str_ireplace('COLLATE NOCASE', '', $sql);
        $sql = preg_replace('/INTO settings\s*\(\s*key\s*,\s*value\s*\)/i', 'INTO settings (`key`, `value`)', $sql) ?? $sql;
        $sql = preg_replace('/SELECT key, value FROM settings/i', 'SELECT `key`, `value` FROM settings', $sql) ?? $sql;
        $sql = preg_replace('/SELECT key FROM settings/i', 'SELECT `key` FROM settings', $sql) ?? $sql;
        $sql = preg_replace('/FROM settings WHERE key\b/i', 'FROM settings WHERE `key`', $sql) ?? $sql;
        $sql = preg_replace('/WHERE key\s*=/i', 'WHERE `key` =', $sql) ?? $sql;
        return $sql;
    }

    public static function run(string $sql, array $params = []): int
    {
        $sql = self::dialect($sql);
        if ($sql === '') {
            return 0;
        }
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    public static function lastInsertId(): int
    {
        return (int) self::pdo()->lastInsertId();
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $sql = self::dialect($sql);
        if ($sql === '') {
            return null;
        }
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        $sql = self::dialect($sql);
        if ($sql === '') {
            return [];
        }
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public static function exec(string $sql): void
    {
        $sql = self::dialect($sql);
        if ($sql === '') {
            return;
        }
        try {
            self::pdo()->exec($sql);
        } catch (\PDOException $e) {
            $m = $e->getMessage();
            if (str_contains($m, 'Duplicate') || str_contains($m, 'already exists')) {
                return;
            }
            throw $e;
        }
    }

    public static function prepare(string $sql): PDOStatement
    {
        return self::pdo()->prepare(self::dialect($sql));
    }
}
