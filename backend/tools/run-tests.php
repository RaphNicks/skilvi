<?php
declare(strict_types=1);

$dir = dirname(__DIR__) . '/tests';
$files = glob($dir . '/test_*.php') ?: [];
sort($files);
$fail = 0;
foreach ($files as $f) {
    echo "\n== " . basename($f) . " ==\n";
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($f), $code);
    if ($code !== 0) {
        $fail++;
    }
}
echo $fail ? "\nFAILED $fail file(s)\n" : "\nALL TEST FILES PASSED\n";
exit($fail ? 1 : 0);
