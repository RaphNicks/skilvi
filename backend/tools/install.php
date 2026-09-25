<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$driver = App\Core\Config::get('db.driver');
echo "db driver: {$driver}\n";
if ($driver === 'mysql') {
    $m = App\Core\Config::get('db.mysql');
    echo "mysql: {$m['user']}@{$m['host']}:{$m['port']} / {$m['database']}\n";
    echo "phpMyAdmin: http://localhost/phpmyadmin  (open database “{$m['database']}” after this script)\n";
}

try {
    App\Core\Schema::install();
    try {
        echo "importing world locations…\n";
        App\Services\GeoService::importCountriesStates();
        App\Services\GeoService::importCities();
        echo "geo import ok\n";
    } catch (Throwable $e) {
        echo 'geo import skipped: ' . $e->getMessage() . "\n";
        echo "run later: php tools/import-geo.php\n";
    }
    App\Core\Seed::run();
} catch (Throwable $e) {
    fwrite(STDERR, 'INSTALL FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}

if ($driver === 'mysql') {
    $tables = App\Core\Db::fetchAll('SHOW TABLES');
    echo 'mysql tables: ' . count($tables) . "\n";
    foreach ($tables as $row) {
        echo '  ' . (array_values($row)[0] ?? '?') . "\n";
    }
}

echo "schema + seed ok\n";
echo "seed logins (password: password1) — email + OTP\n";
echo "  chinedu@okafordev.ng   (client,worker)\n";
echo "  ifeanyi@okoro.ng       (client,worker)\n";
echo "  ada@w08.ng             (worker)\n";
echo "  admin@skilvi.ng        (admin)\n";
echo "If an older install still shows fake jobs, run: php tools/purge-demo.php\n";
