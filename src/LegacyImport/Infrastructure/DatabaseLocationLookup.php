<?php

namespace Src\LegacyImport\Infrastructure;

use Illuminate\Database\ConnectionInterface;
use Src\LegacyImport\Application\LocationLookup;
use Src\LegacyImport\Application\ResolvedLocation;

/** Reads the `reference_*` location tables directly (the documented exception of this context). */
final class DatabaseLocationLookup implements LocationLookup
{
    /** @var array<string, ResolvedLocation> */
    private array $cache = [];

    public function __construct(private readonly ConnectionInterface $connection) {}

    public function resolve(?string $province, ?string $city, ?string $subdistrict, ?string $village, ?string $postalCode): ResolvedLocation
    {
        $key = implode('|', [$province, $city, $subdistrict, $village, $postalCode]);

        return $this->cache[$key] ??= $this->lookup($this->code($province), $this->code($city), $this->code($subdistrict), $this->code($village), $this->code($postalCode));
    }

    private function lookup(?string $province, ?string $city, ?string $subdistrict, ?string $village, ?string $postalCode): ResolvedLocation
    {
        $provinceId = $province === null ? null : $this->id('reference_provinces', ['bps_code' => $province]);

        if ($provinceId === null) {
            return new ResolvedLocation;
        }

        $cityId = $city === null ? null : $this->id('reference_cities', ['bps_code' => $city, 'province_id' => $provinceId]);

        if ($cityId === null) {
            return new ResolvedLocation($provinceId);
        }

        $subdistrictId = $subdistrict === null ? null : $this->id('reference_subdistricts', ['bps_code' => $subdistrict, 'city_id' => $cityId]);

        if ($subdistrictId === null) {
            return new ResolvedLocation($provinceId, $cityId);
        }

        $villageId = $village === null ? null : $this->id('reference_villages', ['bps_code' => $village, 'subdistrict_id' => $subdistrictId]);

        if ($villageId === null) {
            return new ResolvedLocation($provinceId, $cityId, $subdistrictId);
        }

        $postalCodeId = $postalCode === null ? null : $this->id('reference_postal_codes', ['code' => $postalCode, 'village_id' => $villageId]);

        return new ResolvedLocation($provinceId, $cityId, $subdistrictId, $villageId, $postalCodeId);
    }

    /** @param  array<string, string|int>  $where */
    private function id(string $table, array $where): ?int
    {
        $id = $this->connection->table($table)->where($where)->value('id');

        return $id === null ? null : (int) $id;
    }

    /** A legacy code as text, or null when it is empty or zero. */
    private function code(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === null || $value === '' || $value === '0' ? null : $value;
    }
}
