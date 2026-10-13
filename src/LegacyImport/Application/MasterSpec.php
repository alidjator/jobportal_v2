<?php

namespace Src\LegacyImport\Application;

/**
 * One master of the legacy database and the new master it matches. The legacy names live in a translation table (`$translationTable`, its `$foreignKey`
 * pointing at the master row, one `name` per `locale`); a master without one (`$translationTable` null) keeps its name in `$nameColumn` of the table itself.
 * `$createsMissing` marks a master whose unmatched legacy items become new active items at a real run, merged through the normalised name.
 * `$needsCode` marks one whose table has a unique `code` (the legacy list has none, so a created item gets a code made from its name).
 */
final readonly class MasterSpec
{
    public function __construct(
        public string $key,
        public string $legacyTable,
        public ?string $translationTable,
        public ?string $foreignKey,
        public string $newTable,
        public string $nameColumn = 'name',
        public bool $createsMissing = false,
        public bool $needsCode = false,
    ) {}
}
