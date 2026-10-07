<?php

namespace Src\LegacyImport\Application;

/**
 * Imports one kind of legacy row. An importer only adds rows that are not in the id map yet and never overwrites a row it imported before; in a run
 * without `--write` it reads and reports but writes nothing at all (no row, no file, no map entry).
 */
interface Importer
{
    /** The name used on the command line and in the report (`accounts`, `companies`, ...). */
    public function entity(): string;

    /** @return list<string> the entities that must have been imported before this one */
    public function dependsOn(): array;

    public function run(ImportContext $context): void;
}
