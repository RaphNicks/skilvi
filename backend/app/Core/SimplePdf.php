<?php
declare(strict_types=1);

namespace App\Core;

/** Minimal one-page PDF (Helvetica). No composer. */
final class SimplePdf
{
    /** @param array<int, array{0:string,1:string}> $rows */
    public static function receipt(string $title, array $rows, string $footer): string
    {
        $ops = [];
        $ops[] = '0.145 0.275 0.78 rg 0 780 595 62 re f';
        $ops[] = '1 1 1 rg';
        $ops[] = self::text(40, 808, 'Skilvi', 'F2', 20);
        $ops[] = self::text(40, 790, 'Technologies Ltd  ·  Escrow receipt', 'F1', 10);
        $ops[] = '0.12 0.16 0.24 rg';
        $ops[] = self::text(40, 748, $title, 'F2', 16);

        $y = 710;
        foreach ($rows as $row) {
            $k = self::latin($row[0]);
            $v = self::latin($row[1]);
            $ops[] = '0.45 0.48 0.55 rg';
            $ops[] = self::text(40, $y, $k, 'F1', 9);
            $ops[] = '0.12 0.16 0.24 rg';
            $ops[] = self::text(210, $y, $v, 'F2', 10);
            $y -= 8;
            $ops[] = '0.90 0.91 0.93 RG 0.4 w 40 ' . $y . ' 515 0 m 555 ' . $y . ' l S';
            $y -= 18;
            if ($y < 80) {
                break;
            }
        }
        $ops[] = '0.45 0.48 0.55 rg';
        foreach (self::wrap($footer, 88) as $line) {
            $ops[] = self::text(40, $y, $line, 'F1', 8);
            $y -= 12;
        }

        $stream = implode("\n", $ops) . "\n";
        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> >>';
        $objects[] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . 'endstream';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        $out = "%PDF-1.4\n";
        $offs = [0];
        foreach ($objects as $i => $body) {
            $offs[] = strlen($out);
            $out .= ($i + 1) . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($out);
        $n = count($objects) + 1;
        $out .= "xref\n0 {$n}\n0000000000 65535 f \n";
        for ($i = 1; $i < $n; $i++) {
            $out .= sprintf("%010d 00000 n \n", $offs[$i]);
        }
        $out .= "trailer\n<< /Size {$n} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
        return $out;
    }

    private static function text(float $x, float $y, string $s, string $font, int $size): string
    {
        $s = self::escape(self::latin($s));
        return "BT /{$font} {$size} Tf 1 0 0 1 {$x} {$y} Tm ({$s}) Tj ET";
    }

    private static function escape(string $s): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }

    private static function latin(string $s): string
    {
        $s = str_replace(['₦', '—', '–', '’', '‘', '“', '”'], ['NGN ', '-', '-', "'", "'", '"', '"'], $s);
        $out = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $s);
        return $out !== false ? $out : preg_replace('/[^\x20-\x7E]/', '?', $s) ?? $s;
    }

    /** @return list<string> */
    private static function wrap(string $s, int $width): array
    {
        $s = self::latin($s);
        $words = preg_split('/\s+/', $s) ?: [];
        $lines = [];
        $cur = '';
        foreach ($words as $w) {
            $try = $cur === '' ? $w : $cur . ' ' . $w;
            if (strlen($try) > $width && $cur !== '') {
                $lines[] = $cur;
                $cur = $w;
            } else {
                $cur = $try;
            }
        }
        if ($cur !== '') {
            $lines[] = $cur;
        }
        return $lines ?: [''];
    }
}
