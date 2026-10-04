<?php

namespace Src\ReferenceData\Application\Masters;

use Src\Shared\Application\Pagination\Page;
use Src\Shared\Domain\TranslatedText;

/** Reads and writes the rows of any master table, described by its `MasterDefinition`. */
interface MasterRepository
{
    /** @return list<MasterItem> every matching row (for lists that are not paginated) */
    public function all(MasterDefinition $definition, MasterQuery $query): array;

    /** @return Page<MasterItem> */
    public function page(MasterDefinition $definition, MasterQuery $query): Page;

    public function find(MasterDefinition $definition, int $id): ?MasterItem;

    public function slugExists(MasterDefinition $definition, string $slug): bool;

    /** Whether another row already has this value in the extra column (for unique columns such as a language code). */
    public function extraValueTaken(MasterDefinition $definition, string $column, string $value, ?int $exceptId): bool;

    /** @param  array<string, int|string|null>  $extras */
    public function create(MasterDefinition $definition, string $slug, TranslatedText $name, int $sortOrder, bool $isActive, array $extras): MasterItem;

    /** @param  array<string, int|string|null>  $extras */
    public function update(MasterDefinition $definition, int $id, TranslatedText $name, int $sortOrder, bool $isActive, array $extras): MasterItem;
}
