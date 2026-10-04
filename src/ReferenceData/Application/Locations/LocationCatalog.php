<?php

namespace Src\ReferenceData\Application\Locations;

final class LocationCatalog
{
    /** @var array<string, LocationLevel>|null */
    private static ?array $levels = null;

    /** @return array<string, LocationLevel> keyed by URL name, from the largest area to the smallest */
    public static function all(): array
    {
        return self::$levels ??= [
            'provinces' => new LocationLevel('provinces', 'reference_provinces', null),
            'cities' => new LocationLevel('cities', 'reference_cities', 'province_id', extraColumns: ['type']),
            'subdistricts' => new LocationLevel('subdistricts', 'reference_subdistricts', 'city_id'),
            'villages' => new LocationLevel('villages', 'reference_villages', 'subdistrict_id'),
            'postal-codes' => new LocationLevel('postal-codes', 'reference_postal_codes', 'village_id', hasName: false, hasBpsCode: false, extraColumns: ['code']),
        ];
    }

    public static function find(string $key): ?LocationLevel
    {
        return self::all()[$key] ?? null;
    }
}
