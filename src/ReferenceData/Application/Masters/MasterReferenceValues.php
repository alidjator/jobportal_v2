<?php

namespace Src\ReferenceData\Application\Masters;

use InvalidArgumentException;
use Src\Shared\Application\References\ReferenceValues;

final readonly class MasterReferenceValues implements ReferenceValues
{
    public function __construct(private MasterRepository $masters) {}

    public function isActive(string $master, int $id): bool
    {
        $item = $this->masters->find($this->definition($master), $id);

        return $item !== null && $item->isActive;
    }

    public function name(string $master, int $id, string $language): ?string
    {
        return $this->masters->find($this->definition($master), $id)?->name->resolve($language);
    }

    public function names(string $master, array $ids, string $language): array
    {
        return array_map(
            static fn (MasterItem $item): string => $item->name->resolve($language),
            $this->masters->findMany($this->definition($master), array_values(array_unique($ids))),
        );
    }

    public function neighbours(string $master, int $id, int $steps): array
    {
        $ids = array_map(
            static fn (MasterItem $item): int => $item->id,
            $this->masters->all($this->definition($master), new MasterQuery(onlyActive: true, language: 'en')),
        );
        $position = array_search($id, $ids, true);

        return $position === false ? [] : array_slice($ids, max(0, $position - $steps), min($steps, $position) + $steps + 1);
    }

    public function idBySlug(string $master, string $slug): ?int
    {
        return $this->masters->idBySlug($this->definition($master), $slug);
    }

    private function definition(string $master): MasterDefinition
    {
        return MasterCatalog::find($master) ?? throw new InvalidArgumentException("Unknown master {$master}.");
    }
}
