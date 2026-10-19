<?php

namespace Src\Shared\Application\References;

/** Lets other contexts check a reference to a master (industry, organization type, ...) without reading ReferenceData's tables. Provided by ReferenceData. */
interface ReferenceValues
{
    /**
     * True when the master has an active item with that id. `$master` is the URL name of the master, for example `industries`.
     * An unknown master is a programming error and throws.
     */
    public function isActive(string $master, int $id): bool;

    /**
     * The name of the item in the language (the other language when that one is empty), whether or not the item is still active, so a
     * profile that refers to an item an admin later deactivated keeps showing its name. Null when there is no such item.
     */
    public function name(string $master, int $id, string $language): ?string;

    /**
     * The names of several items in one query, as `name` gives them, keyed by id (an id with no item is missing). For lists, where asking one by one
     * would take a query per row.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function names(string $master, array $ids, string $language): array;

    /**
     * The ids of the items at most `$steps` places away from this one in the curated order (itself included), among the active items; empty when the item is
     * unknown or not active. For a master whose order means something, such as the job levels.
     *
     * @return list<int>
     */
    public function neighbours(string $master, int $id, int $steps): array;

    /** The id of the item with that slug, active or not, or null when there is none. For a fixed, well-known item such as the monthly salary period. */
    public function idBySlug(string $master, string $slug): ?int;

    /**
     * The rank of several education levels in one query, keyed by id (an id with no level is missing), whether or not the level is still active. A higher rank is a
     * higher level of education.
     *
     * @param  list<int>  $levelIds
     * @return array<int, int>
     */
    public function educationRanks(array $levelIds): array;
}
