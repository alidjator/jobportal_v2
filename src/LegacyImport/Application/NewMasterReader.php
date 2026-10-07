<?php

namespace Src\LegacyImport\Application;

/** Reads a new master (a `reference_*` table): its rows with the slug and the names in every language. */
interface NewMasterReader
{
    /** @return list<array{id: int, slug: string, names: list<string>}> */
    public function rows(string $table): array;

    /** The id of the item with this slug, or null when there is none. */
    public function idBySlug(string $table, string $slug): ?int;
}
