<?php

namespace Src\LegacyImport\Application;

/** Finds the new location rows for the BPS codes the legacy database stores. Each level must belong to the one above it; the chain is cut at the first level that does not. */
interface LocationLookup
{
    public function resolve(?string $province, ?string $city, ?string $subdistrict, ?string $village, ?string $postalCode): ResolvedLocation;
}
