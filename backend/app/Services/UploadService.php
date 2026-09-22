<?php
declare(strict_types=1);

namespace App\Services;

use App\AppError;
use App\Core\Config;
use App\Core\Db;

final class UploadService
{
    private const MIME = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /** @param array<string,mixed> $file from $_FILES */
    public static function avatar(int $userId, array $file): array
    {
        $path = self::store($file, 'avatars');
        Db::run('UPDATE profiles SET avatar_path = ?, updated_at = ? WHERE user_id = ?', [$path['stored'], now_iso(), $userId]);
        return ['avatar' => '/api/files/' . rawurlencode($path['token']), 'bytes' => $path['bytes']];
    }

    /** Proof docs — stored outside webroot; only the owner or an admin may fetch. */
    public static function proof(int $userId, array $file): array
    {
        return self::store($file, 'proof/' . $userId);
    }

    public static function stream(string $token, int $viewerId, bool $admin): void
    {
        $token = preg_replace('/[^a-f0-9]/', '', strtolower($token)) ?? '';
        $map = dirname(__DIR__, 2) . '/storage/uploads/index.json';
        $idx = is_file($map) ? (json_decode((string) file_get_contents($map), true) ?: []) : [];
        $meta = $idx[$token] ?? null;
        if (!is_array($meta) || !is_file((string) ($meta['abs'] ?? ''))) {
            throw new AppError('not_found', 'File not found.', 404);
        }
        $owner = (int) ($meta['user_id'] ?? 0);
        $kind = (string) ($meta['kind'] ?? '');
        if ($kind !== 'avatars' && $owner !== $viewerId && !$admin) {
            throw new AppError('forbidden', 'That file is private.', 403);
        }
        $mime = (string) ($meta['mime'] ?? 'application/octet-stream');
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=86400');
        readfile((string) $meta['abs']);
        exit;
    }

    /** @param array<string,mixed> $file */
    private static function store(array $file, string $kind): array
    {
        $max = (int) Config::get('files.max_bytes', 5 * 1024 * 1024);
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new AppError('invalid', 'Upload failed. Try a smaller JPG, PNG or WebP.', 422);
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($tmp === '' || !is_file($tmp) || $size < 32 || $size > $max) {
            throw new AppError('invalid', 'Use a JPG, PNG or WebP under 5 MB.', 422);
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);
        if (!isset(self::MIME[$mime])) {
            throw new AppError('invalid', 'Only JPG, PNG or WebP are accepted.', 422);
        }
        $ext = self::MIME[$mime];
        $token = bin2hex(random_bytes(16));
        $dir = dirname(__DIR__, 2) . '/storage/uploads/' . $kind;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $abs = $dir . '/' . $token . '.' . $ext;
        if (!@move_uploaded_file($tmp, $abs) && !@copy($tmp, $abs)) {
            throw new AppError('server', 'Could not store the file.', 500);
        }
        @chmod($abs, 0640);
        $mapFile = dirname(__DIR__, 2) . '/storage/uploads/index.json';
        $idx = is_file($mapFile) ? (json_decode((string) file_get_contents($mapFile), true) ?: []) : [];
        $uid = 0;
        if (preg_match('#proof/(\d+)#', $kind, $m)) {
            $uid = (int) $m[1];
        } elseif ($kind === 'avatars') {
            $uid = (int) (\App\Core\Session::userId() ?? 0);
        }
        $idx[$token] = [
            'abs'     => $abs,
            'mime'    => $mime,
            'kind'    => explode('/', $kind)[0],
            'user_id' => $uid,
            'bytes'   => $size,
            'at'      => now_iso(),
        ];
        file_put_contents($mapFile, json_encode($idx));
        return ['token' => $token, 'stored' => $kind . '/' . $token . '.' . $ext, 'bytes' => $size, 'mime' => $mime];
    }
}
