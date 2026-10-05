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
}
