<?php
declare(strict_types=1);

namespace App\Services;

use App\AppError;
use App\Core\Config;
use App\Core\Db;

final class CmsService
{
    /** @var array<string,string>|null */
    private static ?array $cache = null;

    public static function ensure(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
        if (Db::isMysql()) {
            Db::exec(
                'CREATE TABLE IF NOT EXISTS cms_content (
                    k VARCHAR(191) NOT NULL PRIMARY KEY,
                    kind VARCHAR(32) NOT NULL DEFAULT \'text\',
                    body TEXT NOT NULL,
                    updated_at VARCHAR(32) NOT NULL,
                    updated_by INT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            return;
        }
        Db::exec(
            'CREATE TABLE IF NOT EXISTS cms_content (
                k TEXT NOT NULL PRIMARY KEY,
                kind TEXT NOT NULL DEFAULT \'text\',
                body TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                updated_by INTEGER
            )'
        );
        } catch (\Throwable $e) {
            /* table may already exist, or DB is not up yet */
        }
    }

    /** @return array{pages:list<array<string,mixed>>,values:array<string,string>} */
    public static function adminPayload(): array
    {
        self::ensure();
        $saved = self::overrides();
        $pages = [];
        foreach (CmsSchema::pages() as $page) {
            $fields = [];
            foreach ($page['fields'] as $f) {
                $key = (string) $f['key'];
                $fields[] = [
                    'key'     => $key,
                    'label'   => $f['label'],
                    'type'    => $f['type'],
                    'default' => (string) $f['default'],
                    'value'   => $saved[$key] ?? (string) $f['default'],
                    'custom'  => array_key_exists($key, $saved),
                ];
            }
            $pages[] = ['id' => $page['id'], 'label' => $page['label'], 'fields' => $fields];
        }
        return ['pages' => $pages, 'values' => $saved];
    }

    /** @return array<string,string> */
    public static function overrides(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        self::ensure();
        $out = [];
        try {
            foreach (Db::fetchAll('SELECT k, body FROM cms_content') as $row) {
                $k = (string) ($row['k'] ?? '');
                if ($k !== '') {
                    $out[$k] = (string) ($row['body'] ?? '');
                }
            }
        } catch (\Throwable $e) {
            $out = [];
        }
        self::$cache = $out;
        return $out;
    }

    public static function save(int $adminId, string $key, string $value, string $ip): array
    {
        self::ensure();
        $field = CmsSchema::byKey()[$key] ?? null;
        if ($field === null) {
            throw new AppError('invalid', 'Unknown content field.', 422);
        }
        $type = (string) $field['type'];
        $value = self::clean($type, $value);
        Db::run(
            'INSERT OR REPLACE INTO cms_content (k, kind, body, updated_at, updated_by) VALUES (?,?,?,?,?)',
            [$key, $type, $value, now_iso(), $adminId]
        );
        self::$cache = null;
        AdminService::audit($adminId, 'cms.save', $key, ['bytes' => strlen($value)], $ip);
        return self::adminPayload();
    }

    public static function revert(int $adminId, string $key, string $ip): array
    {
        self::ensure();
        if (!isset(CmsSchema::byKey()[$key])) {
            throw new AppError('invalid', 'Unknown content field.', 422);
        }
        Db::run('DELETE FROM cms_content WHERE k = ?', [$key]);
        self::$cache = null;
        AdminService::audit($adminId, 'cms.revert', $key, [], $ip);
        return self::adminPayload();
    }

    /** @param array<string,mixed> $file */
    public static function upload(int $adminId, string $key, array $file, string $ip): array
    {
        self::ensure();
        $field = CmsSchema::byKey()[$key] ?? null;
        if ($field === null || !in_array($field['type'], ['image', 'video'], true)) {
            throw new AppError('invalid', 'That field is not a media slot.', 422);
        }
        $url = self::storePublic($key, (string) $field['type'], $file);
        return self::save($adminId, $key, $url, $ip);
    }

    public static function apply(string $html): string
    {
        $map = self::overrides();
        if ($map === []) {
            return $html;
        }
        if (str_contains($html, '</head>') && !str_contains($html, 'window.SKILVI_CMS')) {
            $json = json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $html = str_replace('</head>', '<script>window.SKILVI_CMS=' . $json . ';</script></head>', $html, 1);
        }
        $fields = CmsSchema::byKey();
        foreach ($fields as $key => $f) {
            if (!isset($map[$key]) || empty($f['paths']) || !is_array($f['paths'])) {
                continue;
            }
            $url = ltrim((string) $map[$key], '/');
            foreach ($f['paths'] as $old) {
                $old = ltrim((string) $old, '/');
                if ($old === '' || $old === $url) {
                    continue;
                }
                $html = str_replace($old, $url, $html);
            }
        }
        $html = preg_replace_callback(
            '/<(title|h1|h2|h3|h4|p|span|a|summary|label|button|b|div)(\s[^>]*data-cms="([a-z0-9._-]+)"[^>]*)>(.*?)<\/\1>/is',
            static function (array $m) use ($map, $fields): string {
                $key = $m[3];
                if (!isset($map[$key])) {
                    return $m[0];
                }
                $type = (string) ($fields[$key]['type'] ?? 'text');
                $val = $map[$key];
                if ($type === 'html' || $type === 'textarea') {
                    $safe = strip_tags($val, '<br><b><strong><em><i>');
                    $safe = str_replace(["\r\n", "\n"], '<br>', $safe);
                    return '<' . $m[1] . $m[2] . '>' . $safe . '</' . $m[1] . '>';
                }
                return '<' . $m[1] . $m[2] . '>' . htmlspecialchars($val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</' . $m[1] . '>';
            },
            $html
        ) ?? $html;
        $html = preg_replace_callback(
            '/<meta\b([^>]*\sdata-cms="([a-z0-9._-]+)"[^>]*)>/i',
            static function (array $m) use ($map): string {
                $key = $m[2];
                if (!isset($map[$key])) {
                    return $m[0];
                }
                $val = htmlspecialchars($map[$key], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $attrs = $m[1];
                if (preg_match('/\scontent="/i', $attrs)) {
                    $attrs = preg_replace('/\scontent="[^"]*"/i', ' content="' . $val . '"', $attrs, 1) ?? $attrs;
                }
                return '<meta' . $attrs . '>';
            },
            $html
        ) ?? $html;
        $html = preg_replace_callback(
            '/<input\b([^>]*\sdata-cms="([a-z0-9._-]+)"[^>]*)>/i',
            static function (array $m) use ($map): string {
                $key = $m[2];
                if (!isset($map[$key])) {
                    return $m[0];
                }
                $val = htmlspecialchars($map[$key], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $attrs = $m[1];
                if (preg_match('/\splaceholder="/i', $attrs)) {
                    $attrs = preg_replace('/\splaceholder="[^"]*"/i', ' placeholder="' . $val . '"', $attrs, 1) ?? $attrs;
                }
                if (preg_match('/\svalue="/i', $attrs) && !preg_match('/\stype="(search|text|email)"/i', $attrs)) {
                    /* leave value */
                }
                return '<input' . $attrs . '>';
            },
            $html
        ) ?? $html;
        $html = preg_replace_callback(
            '/<(img|video|source)\b([^>]*\sdata-cms="([a-z0-9._-]+)"[^>]*)>/i',
            static function (array $m) use ($map): string {
                $key = $m[3];
                if (!isset($map[$key])) {
                    return $m[0];
                }
                $url = htmlspecialchars($map[$key], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $attrs = $m[2];
                if (preg_match('/\ssrc="/i', $attrs)) {
                    $attrs = preg_replace('/\ssrc="[^"]*"/i', ' src="' . $url . '"', $attrs, 1) ?? $attrs;
                } else {
                    $attrs .= ' src="' . $url . '"';
                }
                return '<' . $m[1] . $attrs . '>';
            },
            $html
        ) ?? $html;
        return $html;
    }

    private static function clean(string $type, string $value): string
    {
        $value = trim($value);
        if ($type === 'html') {
            return strip_tags($value, '<br><b><strong><em><i>');
        }
        if (in_array($type, ['image', 'video'], true)) {
            if ($value === '' || !str_starts_with($value, '/assets/')) {
                throw new AppError('invalid', 'Media must be an on-site /assets/ path.', 422);
            }
            if (str_contains($value, '..')) {
                throw new AppError('invalid', 'Invalid media path.', 422);
            }
            return $value;
        }
        return $value;
    }

    /** @param array<string,mixed> $file */
    private static function storePublic(string $key, string $type, array $file): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new AppError('invalid', 'Upload failed. Try a smaller file.', 422);
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        $max = $type === 'video' ? 20 * 1024 * 1024 : (int) Config::get('files.max_bytes', 5 * 1024 * 1024);
        if ($tmp === '' || !is_file($tmp) || $size < 32 || $size > $max) {
            throw new AppError('invalid', $type === 'video' ? 'Use an MP4 or WebM under 20 MB.' : 'Use a JPG, PNG or WebP under 5 MB.', 422);
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);
        $allow = $type === 'video'
            ? ['video/mp4' => 'mp4', 'video/webm' => 'webm']
            : ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!isset($allow[$mime])) {
            throw new AppError('invalid', $type === 'video' ? 'Only MP4 or WebM are accepted.' : 'Only JPG, PNG or WebP are accepted.', 422);
        }
        $ext = $allow[$mime];
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($key)) ?: 'file';
        $name = $slug . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $frontend = (string) Config::get('frontend_root');
        $dir = rtrim($frontend, '/\\') . '/assets/cms';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new AppError('server', 'Could not create the media folder.', 500);
        }
        $abs = $dir . '/' . $name;
        if (!@move_uploaded_file($tmp, $abs) && !@copy($tmp, $abs)) {
            throw new AppError('server', 'Could not store the file.', 500);
        }
        return '/assets/cms/' . $name;
    }
}
