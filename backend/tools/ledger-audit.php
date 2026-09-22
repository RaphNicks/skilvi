<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
App\Core\Schema::install();
$r = App\Services\LedgerAudit::run();
echo json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
exit($r['ok'] ? 0 : 2);
