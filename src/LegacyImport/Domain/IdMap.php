<?php

namespace Src\LegacyImport\Domain;

use DateTimeImmutable;

/**
 * Which new row each imported legacy row became. An importer asks it before it writes (a row already in the map is never imported again, which makes every
 * run safe to repeat) and later importers look their parents up in it.
 */
interface IdMap
{
    public function find(string $entity, int $legacyId): ?int;

    /**
     * The new ids of several legacy rows, keyed by legacy id; a legacy id that was not imported is missing.
     *
     * @param  list<int>  $legacyIds
     * @return array<int, int>
     */
    public function findMany(string $entity, array $legacyIds): array;

    /** Records that the legacy row became the new row. A legacy row that is already in the map keeps its first answer. */
    public function remember(string $entity, int $legacyId, int $newId, DateTimeImmutable $at): void;

    /**
     * Replaces the whole map of one entity (used for the masters, whose map is configuration: it is worked out again at every run, so a corrected mapping
     * file takes effect). Rows that were already imported with the old map are not changed.
     *
     * @param  array<int, int>  $newIdsByLegacyId
     */
    public function replace(string $entity, array $newIdsByLegacyId, DateTimeImmutable $at): void;
}
