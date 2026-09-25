<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

@set_time_limit(0);
echo "Importing countries, states and cities (dr5hn/countries-states-cities-database)…\n";
try {
    App\Core\Schema::install();
    App\Services\GeoService::importCountriesStates();
    echo "countries + states ok\n";
    App\Services\GeoService::importCities();
    $c = App\Core\Db::fetch('SELECT COUNT(*) c FROM geo_countries')['c'] ?? 0;
    $s = App\Core\Db::fetch('SELECT COUNT(*) c FROM geo_states')['c'] ?? 0;
    $i = App\Core\Db::fetch('SELECT COUNT(*) c FROM geo_cities')['c'] ?? 0;
    echo "geo_countries {$c}\ngeo_states {$s}\ngeo_cities {$i}\n";
    echo "done\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'GEO IMPORT FAILED: ' . $e->getMessage() . "\n");
    exit(1);
}
