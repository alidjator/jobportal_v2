<?php

namespace Src\LegacyImport\Application;

/** Writes new master items into the `reference_*` tables (the documented exception of this context): the legacy items that matched no existing master. */
interface MasterWriter
{
    /**
     * Inserts an active master item whose name is the same in every language, with a slug that is unique in the table (made from the name, numbered when taken).
     * `$kind` (`hard` or `soft`) is given for a skill only.
     *
     * @return int the id of the new item
     */
    public function create(MasterSpec $spec, string $name, ?string $kind, string $createdAt): int;
}
