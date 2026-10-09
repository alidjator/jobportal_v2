<?php

namespace Src\Shared\Infrastructure\Persistence;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Query\Builder;

/**
 * The one place that turns a typed search text into SQL: "the column contains the text", ignoring case and accents.
 *
 * `%`, `_` and `\` typed by the caller are searched for literally. The condition uses `immutable_unaccent` (created by
 * the first migration) so a trigram index on the same expression can serve it.
 */
final class TextSearch
{
    /**
     * @param  string|Expression  $column  a column name (wrapped for the connection) or an SQL expression such as `name->>'en'`
     * @param  'and'|'or'  $boolean
     */
    public static function contains(Builder $builder, string|Expression $column, string $text, string $boolean = 'and'): Builder
    {
        $grammar = $builder->getGrammar();
        $sql = $column instanceof Expression ? (string) $column->getValue($grammar) : $grammar->wrap($column);

        return $builder->whereRaw(
            "immutable_unaccent({$sql}) ILIKE immutable_unaccent(?)",
            ['%'.addcslashes($text, '%_\\').'%'],
            $boolean,
        );
    }
}
