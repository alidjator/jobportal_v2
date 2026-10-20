<?php

namespace Src\LegacyImport\Application;

use Src\LegacyImport\Domain\IdMap;
use Src\Shared\Application\Clock;

/**
 * What an importer works with: the report, the id map, the clock, whether this run really writes (`$write`; a run without it only reads and reports) and the
 * optional mapping files the user filled in (by name, for example `masters` or `stages`).
 */
final readonly class ImportContext
{
    /** @param  array<string, array<string, mixed>>  $mappings */
    public function __construct(
        public bool $write,
        public ImportReport $report,
        public IdMap $idMap,
        public Clock $clock,
        public ImportSummaryRecorder $summary,
        public array $mappings = [],
    ) {}

    /** @return array<string, mixed> the mapping file of this name, or an empty mapping */
    public function mapping(string $name): array
    {
        return $this->mappings[$name] ?? [];
    }
}
