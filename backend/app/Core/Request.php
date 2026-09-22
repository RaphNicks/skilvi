<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    public string $method;
    public string $path;
    public array $query;
    public array $input;
    public array $files;
    public string $raw = '';
    public ?int $userId;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $this->path = rawurldecode($uri);
        $this->query = $_GET;
        $this->files = $_FILES;
        $this->input = [];

        $raw = file_get_contents('php://input') ?: '';
        $this->raw = $raw;
        $ctype = $_SERVER['CONTENT_TYPE'] ?? '';
        if ($this->method === 'POST' || $this->method === 'PATCH' || $this->method === 'PUT' || $this->method === 'DELETE') {
            if (str_contains($ctype, 'application/json') && $raw !== '') {
                $this->input = json_decode($raw, true) ?: [];
            } elseif ($raw !== '' && str_contains($ctype, 'application/x-www-form-urlencoded')) {
                parse_str($raw, $this->input);
            } else {
                $this->input = $_POST;
            }
        }

        $uid = Session::get('user_id');
        $this->userId = $uid === null ? null : (int) $uid;
    }

    public function q(string $key, string $default = ''): string
    {
        $v = $this->query[$key] ?? $default;
        return is_scalar($v) ? trim((string) $v) : $default;
    }

    public function qInt(string $key, int $default = 0): int
    {
        $v = $this->query[$key] ?? $default;
        return is_numeric($v) ? (int) $v : $default;
    }

    public function str(string $key, string $default = ''): string
    {
        $v = $this->input[$key] ?? $default;
        return is_scalar($v) ? trim((string) $v) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $v = $this->input[$key] ?? $default;
        return is_numeric($v) ? (int) $v : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $v = $this->input[$key] ?? $default;
        if (is_bool($v)) {
            return $v;
        }
        return in_array(strtolower((string) $v), ['1', 'true', 'yes', 'on'], true);
    }

    public function array(string $key): array
    {
        $v = $this->input[$key] ?? [];
        return is_array($v) ? $v : [];
    }

    public function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
