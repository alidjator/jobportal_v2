<?php

namespace Src\ReferenceData\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Shared\Application\References\LocationNames;
use Src\Shared\Application\References\LocationReferences;

/** Table and column names are fixed in this class (never taken from input); the ids are bound parameters. */
final readonly class DatabaseLocationReferences implements LocationReferences
{
    public function __construct(private ConnectionInterface $connection) {}

    public function isValidChain(?int $provinceId, ?int $cityId, ?int $subdistrictId, ?int $villageId, ?int $postalCodeId): bool
    {
        // Each level is checked against the one above it; a level without the levels above it is not a chain.
        $levels = [
            [$provinceId, 'reference_provinces', null],
            [$cityId, 'reference_cities', 'province_id'],
            [$subdistrictId, 'reference_subdistricts', 'city_id'],
            [$villageId, 'reference_villages', 'subdistrict_id'],
            [$postalCodeId, 'reference_postal_codes', 'village_id'],
        ];

        $parent = null;
        $parentGiven = true;

        foreach ($levels as [$id, $table, $parentColumn]) {
            if ($id === null) {
                $parentGiven = false;
                $parent = null;

                continue;
            }

            if (! $parentGiven) {
                return false;
            }

            $query = $this->connection->table($table)->where('id', $id);

            if ($parentColumn !== null) {
                $query->where($parentColumn, $parent);
            }

            if (! $query->exists()) {
                return false;
            }

            $parent = $id;
        }

        return true;
    }

    public function names(?int $provinceId, ?int $cityId, ?int $subdistrictId, ?int $villageId, ?int $postalCodeId): LocationNames
    {
        return new LocationNames(
            $this->value('reference_provinces', 'name', $provinceId),
            $this->value('reference_cities', 'name', $cityId),
            $this->value('reference_subdistricts', 'name', $subdistrictId),
            $this->value('reference_villages', 'name', $villageId),
            $this->value('reference_postal_codes', 'code', $postalCodeId),
        );
    }

    public function provinceNames(array $provinceIds): array
    {
        return $this->namesOf('reference_provinces', $provinceIds);
    }

    public function provinceCodes(array $provinceIds): array
    {
        $codes = [];

        if ($provinceIds === []) {
            return $codes;
        }

        foreach ($this->connection->table('reference_provinces')->whereIn('id', array_values(array_unique($provinceIds)))->get(['id', 'bps_code']) as $row) {
            $codes[(int) $row->id] = (string) $row->bps_code;
        }

        return $codes;
    }

    public function provinceOfCity(int $cityId): ?int
    {
        $id = $this->connection->table('reference_cities')->where('id', $cityId)->value('province_id');

        return $id === null ? null : (int) $id;
    }

    public function cityNames(array $cityIds): array
    {
        return $this->namesOf('reference_cities', $cityIds);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function namesOf(string $table, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $names = [];

        foreach ($this->connection->table($table)->whereIn('id', array_values(array_unique($ids)))->get(['id', 'name']) as $row) {
            $names[(int) $row->id] = (string) $row->name;
        }

        return $names;
    }

    private function value(string $table, string $column, ?int $id): ?string
    {
        if ($id === null) {
            return null;
        }

        $value = $this->connection->table($table)->where('id', $id)->value($column);

        return $value === null ? null : (string) $value;
    }
}
