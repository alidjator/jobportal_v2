<?php

namespace Src\LegacyImport\Application;

use InvalidArgumentException;

/** An importer that reads a mapping file and can check it before anything runs, so a wrong file stops the run before any row is written. */
interface ValidatesMappings
{
    /**
     * @param  array<string, array<string, mixed>>  $mappings  the mapping files by name
     *
     * @throws InvalidArgumentException with a message naming the entry that is not valid
     */
    public function validateMappings(array $mappings): void;
}
