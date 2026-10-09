<?php

namespace Src\Shared\Application\Analytics;

/**
 * One flat table of a report. `dimensions` say what a row is about, `measures` what was counted or averaged. When `countColumn` is set, a row whose count is below the
 * smallest group the report may show has its `guarded` measures hidden (all measures when `guarded` is empty).
 */
final readonly class AnalyticsTable
{
    /**
     * @param  list<string>  $dimensions
     * @param  list<string>  $measures
     * @param  list<array<string, mixed>>  $rows  every row has every column
     * @param  list<string>  $guarded
     */
    public function __construct(
        public string $name,
        public array $dimensions,
        public array $measures,
        public array $rows,
        public ?string $countColumn = null,
        public array $guarded = [],
    ) {}

    /** @return list<string> */
    public function columns(): array
    {
        return [...$this->dimensions, ...$this->measures];
    }
}
