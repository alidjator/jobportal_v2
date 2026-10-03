<?php

namespace Database\Seeders\Reference;

use Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Seeds the Indonesian administrative hierarchy (province, city or regency, subdistrict, village and
 * postal code) from the CSV files in `database/data/locations`.
 *
 * The files were exported once from the legacy location tables, which follow the BPS codes. Names keep
 * the upper case of the source; the "KABUPATEN"/"KOTA" prefix of a city name is stored in `type` instead
 * (the two legacy "KAB ..." names are read as regencies). The legacy tables hold no coordinates, so those
 * columns stay empty, and one village (1101012001) has no postal code. Rows are inserted by BPS code, so
 * running the seeder again only adds what is missing.
 */
class LocationSeeder extends Seeder
{
    private const CHUNK_SIZE = 1000;

    public function run(): void
    {
        $this->insert('reference_provinces', $this->provinces());

        $provinces = $this->idsByCode('reference_provinces');
        $this->insert('reference_cities', $this->cities($provinces));

        $cities = $this->idsByCode('reference_cities');
        $this->insert('reference_subdistricts', $this->subdistricts($cities));

        $subdistricts = $this->idsByCode('reference_subdistricts');
        $this->insert('reference_villages', $this->villages($subdistricts));

        $villages = $this->idsByCode('reference_villages');
        $this->insert('reference_postal_codes', $this->postalCodes($villages));
    }

    private function provinces(): Generator
    {
        foreach ($this->read('provinces.csv') as $row) {
            yield ['bps_code' => $row['bps_code'], 'name' => $row['name']];
        }
    }

    /**
     * @param  array<int|string, int>  $provinces
     */
    private function cities(array $provinces): Generator
    {
        foreach ($this->read('cities.csv') as $row) {
            yield [
                'province_id' => $this->parentId($provinces, $row['province_bps_code'], $row['bps_code']),
                'bps_code' => $row['bps_code'],
                'name' => $row['name'],
                'type' => $row['type'],
            ];
        }
    }

    /**
     * @param  array<int|string, int>  $cities
     */
    private function subdistricts(array $cities): Generator
    {
        foreach ($this->read('subdistricts.csv') as $row) {
            yield [
                'city_id' => $this->parentId($cities, $row['city_bps_code'], $row['bps_code']),
                'bps_code' => $row['bps_code'],
                'name' => $row['name'],
            ];
        }
    }

    /**
     * @param  array<int|string, int>  $subdistricts
     */
    private function villages(array $subdistricts): Generator
    {
        foreach ($this->read('villages.csv') as $row) {
            yield [
                'subdistrict_id' => $this->parentId($subdistricts, $row['subdistrict_bps_code'], $row['bps_code']),
                'bps_code' => $row['bps_code'],
                'name' => $row['name'],
            ];
        }
    }

    /**
     * @param  array<int|string, int>  $villages
     */
    private function postalCodes(array $villages): Generator
    {
        foreach ($this->read('postal_codes.csv') as $row) {
            yield [
                'village_id' => $this->parentId($villages, $row['village_bps_code'], $row['code']),
                'code' => $row['code'],
            ];
        }
    }

    /**
     * @param  array<int|string, int>  $ids
     */
    private function parentId(array $ids, string $parentCode, string $childKey): int
    {
        return $ids[$parentCode] ?? throw new RuntimeException("Unknown parent BPS code {$parentCode} for {$childKey}.");
    }

    /**
     * @return array<int|string, int>
     */
    private function idsByCode(string $table): array
    {
        /** @var array<int|string, int> */
        return DB::table($table)->pluck('id', 'bps_code')->all();
    }

    /**
     * @param  iterable<array<string, int|string>>  $records
     */
    private function insert(string $table, iterable $records): void
    {
        $now = Carbon::now();
        $chunk = [];

        foreach ($records as $record) {
            $chunk[] = $record + ['created_at' => $now, 'updated_at' => $now];

            if (count($chunk) === self::CHUNK_SIZE) {
                DB::table($table)->insertOrIgnore($chunk);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            DB::table($table)->insertOrIgnore($chunk);
        }
    }

    /**
     * @return Generator<int, array<string, string>>
     */
    private function read(string $file): Generator
    {
        $path = database_path('data/locations/'.$file);
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Cannot open {$path}.");
        }

        try {
            $header = fgetcsv($handle, null, ',', '"', '');

            while (($values = fgetcsv($handle, null, ',', '"', '')) !== false) {
                yield array_combine($header, $values);
            }
        } finally {
            fclose($handle);
        }
    }
}
