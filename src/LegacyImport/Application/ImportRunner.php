<?php

namespace Src\LegacyImport\Application;

use InvalidArgumentException;
use Src\LegacyImport\Domain\IdMap;
use Src\Shared\Application\Clock;

/**
 * Runs the importers: one by name, or all in dependency order. A run without `$write` reads and reports only. The report is kept by the report writer.
 */
final readonly class ImportRunner
{
    /** @param  iterable<Importer>  $importers */
    public function __construct(
        private iterable $importers,
        private IdMap $idMap,
        private Clock $clock,
        private ReportWriter $reports,
    ) {}

    /** @return list<string> the entities that can be imported, in the order `--all` runs them */
    public function entities(): array
    {
        return array_map(static fn (Importer $importer): string => $importer->entity(), $this->ordered());
    }

    /**
     * @param  string|null  $entity  one entity, or null for all of them
     * @param  array<string, array<string, mixed>>  $mappings  the mapping files by name
     * @return array{report: ImportReport, path: string}
     *
     * @throws InvalidArgumentException when the entity is unknown
     */
    public function run(?string $entity, bool $write, array $mappings = []): array
    {
        $selected = $this->ordered();

        if ($entity !== null) {
            $selected = array_values(array_filter($selected, static fn (Importer $importer): bool => $importer->entity() === $entity));

            if ($selected === []) {
                throw new InvalidArgumentException("Unknown entity \"{$entity}\". Known: ".implode(', ', $this->entities()).'.');
            }
        }

        $context = new ImportContext($write, new ImportReport, $this->idMap, $this->clock, $mappings);

        foreach ($selected as $importer) {
            $importer->run($context);
        }

        $name = $this->clock->now()->format('Ymd-His').'-'.($entity ?? 'all').'-'.($write ? 'write' : 'dry-run');

        return ['report' => $context->report, 'path' => $this->reports->write($context->report->toArray($write), $name)];
    }

    /** @return list<Importer> every importer after the ones it depends on */
    private function ordered(): array
    {
        $byEntity = [];

        foreach ($this->importers as $importer) {
            $byEntity[$importer->entity()] = $importer;
        }

        $ordered = [];
        $visiting = [];

        $visit = function (string $entity) use (&$visit, &$ordered, &$visiting, $byEntity): void {
            if (isset($ordered[$entity])) {
                return;
            }

            if (isset($visiting[$entity])) {
                throw new InvalidArgumentException("The importers depend on each other in a circle at \"{$entity}\".");
            }

            $importer = $byEntity[$entity] ?? throw new InvalidArgumentException("An importer depends on \"{$entity}\", which does not exist.");
            $visiting[$entity] = true;

            foreach ($importer->dependsOn() as $dependency) {
                $visit($dependency);
            }

            unset($visiting[$entity]);
            $ordered[$entity] = $importer;
        };

        foreach (array_keys($byEntity) as $entity) {
            $visit($entity);
        }

        return array_values($ordered);
    }
}
