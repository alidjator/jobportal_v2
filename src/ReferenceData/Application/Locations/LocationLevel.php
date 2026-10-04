<?php

namespace Src\ReferenceData\Application\Locations;

/**
 * One level of the Indonesian administrative hierarchy (BPS data, names are proper names and are not translated). Every level
 * except the provinces is listed per parent, so a list never grows past a few dozen items.
 */
final readonly class LocationLevel
{
    /**
     * @param  list<string>  $extraColumns  columns shown besides the id, BPS code and name
     * @param  string|null  $parentColumn  the column holding the parent id, which is also the name of the required `filter[...]`
     */
    public function __construct(
        public string $key,
        public string $table,
        public ?string $parentColumn,
        public bool $hasName = true,
        public bool $hasBpsCode = true,
        public array $extraColumns = [],
    ) {}
}
