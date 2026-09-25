<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Db;

final class GeoService
{
    private const CITIES_GZ = 'https://github.com/dr5hn/countries-states-cities-database/releases/download/v3.2-export.7/csv-cities.csv.gz';

    public static function dir(): string
    {
        return dirname(__DIR__, 2) . '/storage/geo';
    }

    public static function ensure(bool $withCities = false): void
    {
        self::ensureTables();
        $n = (int) (Db::fetch('SELECT COUNT(*) c FROM geo_countries')['c'] ?? 0);
        if ($n < 100) {
            self::importCountriesStates();
        }
        if ($withCities) {
            $c = (int) (Db::fetch('SELECT COUNT(*) c FROM geo_cities')['c'] ?? 0);
            if ($c < 1000) {
                self::importCities();
            }
        }
    }

    public static function countries(): array
    {
        self::ensure(false);
        $rows = Db::fetchAll('SELECT id, name, iso2, phonecode, capital, currency, currency_symbol, emoji, nationality FROM geo_countries ORDER BY CASE iso2 WHEN \'NG\' THEN 0 ELSE 1 END, name');
        return array_map(static fn ($r) => [
            'id'         => (int) $r['id'],
            'name'       => $r['name'],
            'iso2'       => $r['iso2'],
            'phonecode'  => $r['phonecode'],
            'capital'    => $r['capital'],
            'currency'   => $r['currency'],
            'symbol'     => $r['currency_symbol'],
            'emoji'      => $r['emoji'],
            'nationality'=> $r['nationality'],
        ], $rows);
    }

    public static function states(int $countryId): array
    {
        self::ensure(false);
        if ($countryId < 1) {
            return [];
        }
        $rows = Db::fetchAll(
            'SELECT id, name, iso2, type FROM geo_states WHERE country_id = ? ORDER BY name',
            [$countryId]
        );
        return array_map(static fn ($r) => [
            'id'   => (int) $r['id'],
            'name' => $r['name'],
            'iso2' => $r['iso2'],
            'type' => $r['type'],
        ], $rows);
    }

    public static function cities(int $stateId): array
    {
        self::ensure(true);
        if ($stateId < 1) {
            return [];
        }
        $rows = Db::fetchAll(
            'SELECT id, name FROM geo_cities WHERE state_id = ? ORDER BY name LIMIT 4000',
            [$stateId]
        );
        return array_map(static fn ($r) => [
            'id'   => (int) $r['id'],
            'name' => $r['name'],
        ], $rows);
    }

    /** @return array{country:?array,state:?array,city:?array} */
    public static function resolve(?string $country, ?string $state, ?string $city): array
    {
        self::ensure(false);
        $out = ['country' => null, 'state' => null, 'city' => null];
        $country = trim((string) $country);
        $state = trim((string) $state);
        $city = trim((string) $city);
        if ($country === '') {
            return $out;
        }
        $c = Db::fetch(
            'SELECT * FROM geo_countries WHERE iso2 = ? OR name = ? LIMIT 1',
            [strtoupper($country), $country]
        );
        if (!$c) {
            return $out;
        }
        $out['country'] = $c;
        if ($state === '') {
            return $out;
        }
        $s = Db::fetch(
            'SELECT * FROM geo_states WHERE country_id = ? AND (name = ? OR iso2 = ?) LIMIT 1',
            [(int) $c['id'], $state, $state]
        );
        if (!$s) {
            return $out;
        }
        $out['state'] = $s;
        if ($city === '') {
            return $out;
        }
        self::ensure(true);
        $ci = Db::fetch(
            'SELECT * FROM geo_cities WHERE state_id = ? AND name = ? LIMIT 1',
            [(int) $s['id'], $city]
        );
        $out['city'] = $ci;
        return $out;
    }

    public static function importCountriesStates(): void
    {
        $dir = self::dir();
        $cf = $dir . '/countries.csv';
        $sf = $dir . '/states.csv';
        if (!is_file($cf) || !is_file($sf)) {
            throw new \RuntimeException('Missing geo CSV files in storage/geo.');
        }
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $fh = fopen($cf, 'r');
            $head = fgetcsv($fh) ?: [];
            $map = array_flip($head);
            $ins = $pdo->prepare(
                'INSERT INTO geo_countries (id,name,iso2,iso3,phonecode,capital,currency,currency_name,currency_symbol,region,nationality,emoji)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE name=VALUES(name), phonecode=VALUES(phonecode), capital=VALUES(capital), currency=VALUES(currency), emoji=VALUES(emoji)'
            );
            if (!Db::isMysql()) {
                $ins = $pdo->prepare(
                    'INSERT OR REPLACE INTO geo_countries (id,name,iso2,iso3,phonecode,capital,currency,currency_name,currency_symbol,region,nationality,emoji)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
                );
            }
            while (($row = fgetcsv($fh)) !== false) {
                if (!isset($row[$map['id']])) {
                    continue;
                }
                $ins->execute([
                    (int) $row[$map['id']],
                    $row[$map['name']] ?? '',
                    $row[$map['iso2']] ?? '',
                    $row[$map['iso3']] ?? '',
                    $row[$map['phonecode']] ?? '',
                    $row[$map['capital']] ?? '',
                    $row[$map['currency']] ?? '',
                    $row[$map['currency_name']] ?? '',
                    $row[$map['currency_symbol']] ?? '',
                    $row[$map['region']] ?? '',
                    $row[$map['nationality']] ?? '',
                    $row[$map['emoji']] ?? '',
                ]);
            }
            fclose($fh);

            $fh = fopen($sf, 'r');
            $head = fgetcsv($fh) ?: [];
            $map = array_flip($head);
            $ins = $pdo->prepare(
                'INSERT INTO geo_states (id,country_id,country_code,name,iso2,type)
                 VALUES (?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE name=VALUES(name), type=VALUES(type)'
            );
            if (!Db::isMysql()) {
                $ins = $pdo->prepare(
                    'INSERT OR REPLACE INTO geo_states (id,country_id,country_code,name,iso2,type) VALUES (?,?,?,?,?,?)'
                );
            }
            while (($row = fgetcsv($fh)) !== false) {
                if (!isset($row[$map['id']])) {
                    continue;
                }
                $ins->execute([
                    (int) $row[$map['id']],
                    (int) ($row[$map['country_id']] ?? 0),
                    $row[$map['country_code']] ?? '',
                    $row[$map['name']] ?? '',
                    $row[$map['iso2']] ?? '',
                    $row[$map['type']] ?? '',
                ]);
            }
            fclose($fh);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function importCities(): void
    {
        @set_time_limit(0);
        $dir = self::dir();
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $gz = $dir . '/cities.csv.gz';
        if (!is_file($gz) || filesize($gz) < 1000) {
            $data = self::download(self::CITIES_GZ);
            file_put_contents($gz, $data);
        }
        $fh = gzopen($gz, 'r');
        if ($fh === false) {
            throw new \RuntimeException('Could not read cities.csv.gz');
        }
        $head = str_getcsv((string) gzgets($fh));
        $map = array_flip($head);
        $pdo = Db::pdo();
        $sql = 'INSERT INTO geo_cities (id,name,state_id,country_id,country_code) VALUES (?,?,?,?,?)';
        if (Db::isMysql()) {
            $sql .= ' ON DUPLICATE KEY UPDATE name=VALUES(name)';
        } else {
            $sql = 'INSERT OR REPLACE INTO geo_cities (id,name,state_id,country_id,country_code) VALUES (?,?,?,?,?)';
        }
        $ins = $pdo->prepare($sql);
        $n = 0;
        $pdo->beginTransaction();
        try {
            while (($line = gzgets($fh)) !== false) {
                $row = str_getcsv($line);
                if (!isset($row[$map['id']])) {
                    continue;
                }
                $ins->execute([
                    (int) $row[$map['id']],
                    $row[$map['name']] ?? '',
                    (int) ($row[$map['state_id']] ?? 0),
                    (int) ($row[$map['country_id']] ?? 0),
                    $row[$map['country_code']] ?? '',
                ]);
                $n++;
                if ($n % 500 === 0) {
                    $pdo->commit();
                    $pdo->beginTransaction();
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            gzclose($fh);
            throw $e;
        }
        gzclose($fh);
    }

    private static function download(string $url): string
    {
        $ctx = stream_context_create([
            'http' => ['timeout' => 120, 'header' => "User-Agent: SkilviGeo/1.0\r\n"],
            'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false || strlen($raw) < 1000) {
            throw new \RuntimeException('Could not download world cities list.');
        }
        return $raw;
    }

    private static function ensureTables(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        \App\Core\Schema::install();
    }
}
