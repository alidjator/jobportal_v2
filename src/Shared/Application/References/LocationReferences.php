<?php

namespace Src\Shared\Application\References;

/** Lets other contexts check and read an administrative location without reading ReferenceData's tables. Provided by ReferenceData. */
interface LocationReferences
{
    /**
     * True when the ids form one matching chain: every given id exists, each belongs to the one above it (the city to the province, the
     * subdistrict to the city, the village to the subdistrict, the postal code to the village), and no level is given without the levels
     * above it. A chain with nothing in it is valid.
     */
    public function isValidChain(?int $provinceId, ?int $cityId, ?int $subdistrictId, ?int $villageId, ?int $postalCodeId): bool;

    /** The names of the chain. The ids must form a valid chain; a level that is not given (or not found) is null. */
    public function names(?int $provinceId, ?int $cityId, ?int $subdistrictId, ?int $villageId, ?int $postalCodeId): LocationNames;

    /**
     * The names of several provinces in one query, keyed by id (an id with no province is missing). For lists, where asking one by one would take a query per row.
     *
     * @param  list<int>  $provinceIds
     * @return array<int, string>
     */
    public function provinceNames(array $provinceIds): array;

    /** The province a city belongs to, or null when there is no such city. */
    public function provinceOfCity(int $cityId): ?int;

    /**
     * The names of several cities (or regencies) in one query, keyed by id (an id with no city is missing).
     *
     * @param  list<int>  $cityIds
     * @return array<int, string>
     */
    public function cityNames(array $cityIds): array;
}
