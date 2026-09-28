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
                    "CREATE TABLE IF NOT EXISTS cms_content (
                        k VARCHAR(191) NOT NULL PRIMARY KEY,
                        kind VARCHAR(32) NOT NULL DEFAULT 'text',
                        body TEXT NOT NULL,
                        updated_at VARCHAR(32) NOT NULL,
                        updated_by INT NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
                );
                return;
            }
            Db::exec(
                "CREATE TABLE IF NOT EXISTS cms_content (
                    k TEXT NOT NULL PRIMARY KEY,
                    kind TEXT NOT NULL DEFAULT 'text',
                    body TEXT NOT NULL,
                    updated_at TEXT NOT NULL,
                    updated_by INTEGER
                )"
            );
        } catch (\Throwable $e) {
            error_log('SKILVI CMS ensure ' . $e->getMessage());
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
                $row = [
                    'key'     => $key,
                    'label'   => $f['label'],
                    'type'    => $f['type'],
                    'default' => (string) $f['default'],
                    'value'   => $saved[$key] ?? (string) $f['default'],
                    'custom'  => array_key_exists($key, $saved),
                ];
                if (isset($f['options']) && is_array($f['options'])) {
                    $row['options'] = $f['options'];
                }
                $fields[] = $row;
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
        if ($type === 'select') {
            $opts = $field['options'] ?? [];
            if (!is_array($opts) || !array_key_exists($value, $opts)) {
                throw new AppError('invalid', 'Pick one of the listed options.', 422);
            }
        }
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
        try {
            $map = self::overrides();
            if ($map === []) {
                return $html;
            }
            $fields = CmsSchema::byKey();
            foreach ($fields as $key => $f) {
                if (!isset($map[$key]) || empty($f['paths']) || !is_array($f['paths'])) {
                    continue;
                }
                $url = ltrim((string) $map[$key], '/');
                foreach ($f['paths'] as $old) {
                    $old = ltrim((string) $old, '/');
                    if ($old !== '' && $old !== $url) {
                        $html = str_replace($old, $url, $html);
                    }
                }
            }
            foreach ($map as $key => $val) {
                $type = (string) ($fields[$key]['type'] ?? 'text');
                if (in_array($type, ['faqs', 'select', 'url'], true)) {
                    continue;
                }
                $html = self::swapNode($html, $key, $val, $type);
            }
            $html = self::applyFaqs($html, $map);
            $html = self::applyBanner($html, $map);
            if (str_contains($html, '</head>') && !str_contains($html, 'window.SKILVI_CMS')) {
                $json = json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (is_string($json)) {
                    $html = str_replace('</head>', '<script>window.SKILVI_CMS=' . $json . ';</script></head>', $html, 1);
                }
            }
            return $html;
        } catch (\Throwable $e) {
            error_log('SKILVI CMS apply ' . $e->getMessage());
            return $html;
        }
    }

    private static function swapNode(string $html, string $key, string $val, string $type): string
    {
        $qk = preg_quote($key, '/');
        if ($type === 'image' || $type === 'video') {
            $url = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
            $html = preg_replace(
                '/(<(?:img|video|source)\b[^>]*data-cms="' . $qk . '"[^>]*\ssrc=")[^"]*(")/i',
                '$1' . $url . '$2',
                $html,
                1
            ) ?? $html;
            return $html;
        }
        if (str_starts_with($key, 'landing.meta_desc') || str_contains($key, 'meta_desc')) {
            $safe = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
            $html = preg_replace(
                '/(<meta\b[^>]*data-cms="' . $qk . '"[^>]*\scontent=")[^"]*(")/i',
                '$1' . $safe . '$2',
                $html,
                1
            ) ?? $html;
        }
        $safe = ($type === 'html' || $type === 'textarea')
            ? str_replace(["\r\n", "\n"], '<br>', strip_tags($val, '<br><b><strong><em><i>'))
            : htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
        $html = preg_replace_callback(
            '/<(title|h1|h2|h3|h4|p|span|a|summary|label|button|b|div)(\s[^>]*data-cms="' . $qk . '"[^>]*)>.*?<\/\1>/is',
            static function (array $m) use ($safe): string {
                return '<' . $m[1] . $m[2] . '>' . $safe . '</' . $m[1] . '>';
            },
            $html,
            1
        ) ?? $html;
        $html = preg_replace(
            '/(<input\b[^>]*data-cms="' . $qk . '"[^>]*\splaceholder=")[^"]*(")/i',
            '$1' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '$2',
            $html,
            1
        ) ?? $html;
        return $html;
    }

    private static function clean(string $type, string $value): string
    {
        $value = trim($value);
        if ($type === 'html') {
            return strip_tags($value, '<br><b><strong><em><i>');
        }
        if ($type === 'select') {
            return $value;
        }
        if ($type === 'url') {
            if ($value === '') {
                return '';
            }
            if (str_starts_with($value, '/') && !str_starts_with($value, '//') && !str_contains($value, '..')) {
                return $value;
            }
            if (preg_match('#^https://[^\s<>\"\']+$#i', $value)) {
                return $value;
            }
            throw new AppError('invalid', 'Use a site path like /help.html or an https link.', 422);
        }
        if ($type === 'faqs') {
            return self::cleanFaqs($value);
        }
        if (in_array($type, ['image', 'video'], true)) {
            if ($value === '' || !str_starts_with($value, '/assets/') || str_contains($value, '..')) {
                throw new AppError('invalid', 'Media must be an on-site /assets/ path.', 422);
            }
            return $value;
        }
        return $value;
    }

    private static function cleanFaqs(string $value): string
    {
        $raw = json_decode($value, true);
        if (!is_array($raw)) {
            throw new AppError('invalid', 'Questions must be a list.', 422);
        }
        $out = [];
        $seen = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $q = trim(strip_tags((string) ($row['q'] ?? '')));
            $a = trim(strip_tags((string) ($row['a'] ?? ''), '<br><b><strong><em><i>'));
            if ($q === '' || $a === '') {
                continue;
            }
            $id = strtolower(trim((string) ($row['id'] ?? '')));
            $id = preg_replace('/[^a-z0-9-]+/', '-', $id) ?? '';
            $id = trim($id, '-');
            if ($id === '') {
                $id = preg_replace('/[^a-z0-9]+/', '-', strtolower($q)) ?? 'faq';
                $id = trim($id, '-') ?: 'faq';
            }
            $id = substr($id, 0, 40);
            if (isset($seen[$id])) {
                $id .= '-' . (count($out) + 1);
            }
            $seen[$id] = true;
            $item = ['id' => $id, 'q' => $q, 'a' => $a];
            $card = trim(strip_tags((string) ($row['card'] ?? '')));
            $teaser = trim(strip_tags((string) ($row['teaser'] ?? '')));
            if ($card !== '') {
                $item['card'] = $card;
            }
            if ($teaser !== '') {
                $item['teaser'] = $teaser;
            }
            $out[] = $item;
            if (count($out) >= 40) {
                break;
            }
        }
        if ($out === []) {
            throw new AppError('invalid', 'Add at least one question with an answer.', 422);
        }
        $json = json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new AppError('invalid', 'Could not save those questions.', 422);
        }
        return $json;
    }

    /** @param array<string,string> $map */
    private static function applyFaqs(string $html, array $map): string
    {
        if (!str_contains($html, '<!--cms:faq-list-->') || !isset($map['help.faqs'])) {
            return $html;
        }
        $items = json_decode($map['help.faqs'], true);
        if (!is_array($items) || $items === []) {
            return $html;
        }
        $cards = '';
        $list = '';
        $n = 0;
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = preg_replace('/[^a-z0-9-]+/', '-', strtolower((string) ($row['id'] ?? ''))) ?: ('faq-' . $n);
            $id = trim($id, '-') ?: ('item-' . $n);
            $q = htmlspecialchars((string) ($row['q'] ?? ''), ENT_QUOTES, 'UTF-8');
            $a = strip_tags((string) ($row['a'] ?? ''), '<br><b><strong><em><i>');
            $a = str_replace(["\r\n", "\n"], '<br>', $a);
            $open = $n === 0 ? ' open' : '';
            $list .= '<details class="acc" id="faq-' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"' . $open . '>'
                . '<summary>' . $q . '</summary>'
                . '<div class="acc-body">' . $a . '</div></details>';
            $card = trim((string) ($row['card'] ?? ''));
            $teaser = trim((string) ($row['teaser'] ?? ''));
            if ($card !== '') {
                $cards .= '<a class="topic-card" href="#faq-' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '">'
                    . '<span class="tc-ico">' . self::faqIcon($id) . '</span>'
                    . '<h3>' . htmlspecialchars($card, ENT_QUOTES, 'UTF-8') . '</h3>'
                    . '<p>' . htmlspecialchars($teaser !== '' ? $teaser : $card, ENT_QUOTES, 'UTF-8') . '</p></a>';
            }
            $n++;
        }
        if ($list === '') {
            return $html;
        }
        $grid = $cards !== ''
            ? '<div class="grid grid-3 mt-3" id="topicGrid">' . $cards . '</div>'
            : '';
        $html = preg_replace(
            '/<!--cms:faq-topics-->.*?<!--\/cms:faq-topics-->/s',
            '<!--cms:faq-topics-->' . $grid . '<!--/cms:faq-topics-->',
            $html,
            1
        ) ?? $html;
        $html = preg_replace(
            '/<!--cms:faq-list-->.*?<!--\/cms:faq-list-->/s',
            '<!--cms:faq-list--><div class="mt-2" id="faqList">' . $list . '</div><!--/cms:faq-list-->',
            $html,
            1
        ) ?? $html;
        return $html;
    }

    private static function faqIcon(string $id): string
    {
        $svg = [
            'escrow' => '<path d="M12 3 5 6v6c0 4.4 3 7.4 7 9 4-1.6 7-4.6 7-9V6l-7-3Z"/><path d="m9 12 2 2 4-4"/>',
            'fees' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/>',
            'verification' => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.5 2.5 5-5"/>',
            'promotion' => '<path d="M13 2 4 14h6l-1 8 9-12h-6l1-8Z"/>',
            'withdrawals' => '<path d="M3 9.5 12 4l9 5.5"/><path d="M5 10v8m4.5-8v8m5-8v8M19 10v8M3 20h18"/>',
            'disputes' => '<path d="M12 4v16m-7 0h14"/><path d="m5 7-3 6a3.5 3.5 0 0 0 6 0L5 7Zm14 0-3 6a3.5 3.5 0 0 0 6 0l-3-6Z"/><path d="M5 7h14"/>',
            'reporting' => '<path d="M5 21V4"/><path d="M5 4h12l-2.5 4L17 12H5"/>',
            'starting' => '<rect x="3" y="8" width="18" height="12" rx="2"/><path d="M9 8V6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M3 13h18"/>',
            'payments' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/>',
            'onsite' => '<path d="M4 20V9l8-5 8 5v11"/><path d="M10 20v-6h4v6"/>',
        ];
        $d = $svg[$id] ?? '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.4 2.3c-.8.3-.9 1-.9 1.7"/><path d="M12 17h.01"/>';
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $d . '</svg>';
    }

    /** @param array<string,string> $map */
    private static function applyBanner(string $html, array $map): string
    {
        if (str_contains($html, 'data-shell="admin"') || str_contains($html, 'class="shell admin"')) {
            return $html;
        }
        if (str_contains($html, 'class="site-banner')) {
            return $html;
        }
        $on = strtolower(trim($map['banner.on'] ?? 'off'));
        $text = trim($map['banner.text'] ?? '');
        if ($on !== 'on' || $text === '') {
            return $html;
        }
        $tone = (string) ($map['banner.tone'] ?? 'info');
        if (!in_array($tone, ['info', 'promo', 'warn', 'urgent'], true)) {
            $tone = 'info';
        }
        $link = trim($map['banner.link'] ?? '');
        $label = trim($map['banner.link_label'] ?? 'Learn more') ?: 'Learn more';
        $role = $tone === 'urgent' ? 'alert' : 'status';
        $inner = '<p>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</p>';
        if ($link !== '' && (str_starts_with($link, '/') || str_starts_with($link, 'https://'))) {
            $inner .= ' <a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
        }
        $block = '<div class="site-banner is-' . $tone . '" role="' . $role . '"><div class="site-banner-in">'
            . $inner . '</div></div>';
        if (preg_match('/<div id="skLoader"\b[^>]*>.*?<\/div>/is', $html, $m, PREG_OFFSET_CAPTURE)) {
            $end = $m[0][1] + strlen($m[0][0]);
            return substr($html, 0, $end) . $block . substr($html, $end);
        }
        return preg_replace('/<body([^>]*)>/i', '<body$1>' . $block, $html, 1) ?? $html;
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
        $dir = rtrim($frontend, "/\\") . '/assets/cms';
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
