<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
App\Core\Schema::install();
App\Core\Seed::run(true);

function assert_true($c, string $msg): void
{
    if (!$c) {
        fwrite(STDERR, "FAIL $msg\n");
        exit(1);
    }
    echo "ok  $msg\n";
}

$jobs = App\Services\DiscoveryService::jobs([]);
assert_true($jobs['total'] === 6, '6 open jobs');
$remote = App\Services\DiscoveryService::jobs(['mode' => 'remote']);
assert_true($remote['total'] === 3, '3 remote jobs');
$trades = App\Services\DiscoveryService::jobs(['category' => 'trades']);
assert_true($trades['total'] === 3, '3 trades jobs');
$j01 = App\Services\DiscoveryService::job('j01');
assert_true($j01['title'] !== '' && count($j01['proposals']) >= 3, 'j01 has proposals');
$w = App\Services\DiscoveryService::workers(['q' => 'plumber']);
assert_true($w['items'][0]['id'] === 'w03', 'plumber is Emeka');
$w01 = App\Services\DiscoveryService::worker('w01');
assert_true($w01['name'] === 'Chinedu Okafor' && count($w01['services']) === 2, 'w01 services');
$svc = App\Services\DiscoveryService::service('s-w01');
assert_true(str_contains($svc['title'], 'Website'), 'service s-w01');
$cat = App\Services\DiscoveryService::category('trades');
assert_true($cat['category']['name'] === 'Trades & Home Services' && $cat['category']['workers'] >= 5, 'trades category');
$land = App\Services\DiscoveryService::landing();
assert_true(count($land['jobs']) === 6 && count($land['workers']) >= 1, 'landing payload');
echo "ALL DISCOVERY TESTS PASSED\n";
