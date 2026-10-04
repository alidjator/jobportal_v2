<?php

namespace Src\Platform\Application\Settings;

/** Everything the database holds: the global values and the company overrides, by setting key. Settings at their default have no entry. */
final readonly class StoredValues
{
    /**
     * @param  array<string, StoredSetting>  $globals
     * @param  array<string, array<int, mixed>>  $companyOverrides  setting key, then company id, mapped to the value
     */
    public function __construct(
        public array $globals = [],
        public array $companyOverrides = [],
    ) {}
}
