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

    /**
     * Several items in one query, keyed by id (an id with no item is missing); the same rows `find` would find.
     *
     * @param  list<int>  $ids
     * @return array<int, MasterItem>
     */
    public function findMany(MasterDefinition $definition, array $ids): array;

    public function slugExists(MasterDefinition $definition, string $slug): bool;

    /** The id of the row with this slug, or null when there is none. */
    public function idBySlug(MasterDefinition $definition, string $slug): ?int;

    /** Whether another row already has this value in the extra column (for unique columns such as a language code). */
    public function extraValueTaken(MasterDefinition $definition, string $column, string $value, ?int $exceptId): bool;

    /** @param  array<string, int|string|null>  $extras */
    public function create(MasterDefinition $definition, string $slug, TranslatedText $name, int $sortOrder, bool $isActive, array $extras): MasterItem;

    /** @param  array<string, int|string|null>  $extras */
    public function update(MasterDefinition $definition, int $id, TranslatedText $name, int $sortOrder, bool $isActive, array $extras): MasterItem;
}
