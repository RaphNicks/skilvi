<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$dir = dirname(__DIR__) . '/storage/backups';
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}
$src = (string) App\Core\Config::get('db.sqlite_path');
if (!is_file($src)) {
    fwrite(STDERR, "no sqlite at $src\n");
    exit(1);
}
$dst = $dir . '/skilvi-' . gmdate('Ymd-His') . '.sqlite';
if (!copy($src, $dst)) {
    fwrite(STDERR, "copy failed\n");
    exit(1);
}
$keep = 30;
$files = glob($dir . '/skilvi-*.sqlite') ?: [];
rsort($files);
foreach (array_slice($files, $keep) as $old) {
    @unlink($old);
}
echo "backup $dst (" . filesize($dst) . " bytes)\n";
