<?php

namespace Src\LegacyImport\Application;

use stdClass;

/** Reads the legacy database (read only). The only way an importer reads legacy rows. */
interface LegacySource
{
    /**
     * Calls `$each` with the rows of the table in batches of `$size`, ordered by `id`.
     *
     * @param  callable(list<stdClass>): void  $each
     */
    public function chunk(string $table, int $size, callable $each): void;

    /**
     * Every row of a small table (a master, a configuration table), optionally only some columns.
     *
     * @param  list<string>  $columns
     * @return list<stdClass>
     */
    public function all(string $table, array $columns = ['*']): array;

    /**
     * The rows whose `$column` holds one of the values (for example the candidates of a batch of users), optionally only some columns.
     *
     * @param  list<int|string>  $values
     * @param  list<string>  $columns
     * @return list<stdClass>
     */
    public function whereIn(string $table, string $column, array $values, array $columns = ['*']): array;
}
