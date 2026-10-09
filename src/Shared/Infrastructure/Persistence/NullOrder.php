<?php

namespace Src\Shared\Infrastructure\Persistence;

/**
 * The ORDER BY expression for a column that can be NULL, with an explicit place for the NULLs: first when ascending,
 * last when descending. The order then does not depend on the database default (PostgreSQL puts NULLs last when ascending).
 *
 * The column is a fixed name from code (`table.column`), never an input, and is quoted here.
 */
final class NullOrder
{
    /** @param  'asc'|'desc'  $direction */
    public static function sql(string $column, string $direction): string
    {
        $quoted = implode('.', array_map(fn (string $part): string => '"'.str_replace('"', '""', $part).'"', explode('.', $column)));

        return $quoted.(strtolower($direction) === 'desc' ? ' desc nulls last' : ' asc nulls first');
    }
}
