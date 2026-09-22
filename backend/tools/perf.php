<?php
declare(strict_types=1);

$base = rtrim(getenv('SMOKE_BASE') ?: 'http://127.0.0.1:8080', '/');
$n = (int) (getenv('PERF_N') ?: 40);
$path = getenv('PERF_PATH') ?: '/api/landing';
$times = [];
for ($i = 0; $i < $n; $i++) {
    $t0 = microtime(true);
    $raw = @file_get_contents($base . $path, false, stream_context_create([
        'http' => ['timeout' => 8, 'ignore_errors' => true, 'header' => "Accept: application/json\r\n"],
    ]));
    $times[] = (microtime(true) - $t0) * 1000;
    if ($raw === false) {
        fwrite(STDERR, "request failed on #$i\n");
        exit(1);
    }
}
sort($times);
$p95 = $times[(int) floor(0.95 * ($n - 1))];
$avg = array_sum($times) / $n;
echo sprintf("n=%d path=%s avg=%.1fms p50=%.1fms p95=%.1fms max=%.1fms\n", $n, $path, $avg, $times[(int) floor(0.5 * ($n - 1))], $p95, max($times));
echo $p95 < 300 ? "PERF OK (p95 < 300ms)\n" : "PERF FAIL p95={$p95}ms\n";
exit($p95 < 300 ? 0 : 2);
