<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use Illuminate\Database\Query\Builder;

/** The condition of a hiring manager bound to the stage boundary of some jobs: on those jobs only the applications that carry the marker count. */
final class HiringManagerBound
{
    /**
     * @param  list<int>  $boundJobIds  the jobs where the member is a bound hiring manager (none: the query stays as it is)
     * @param  string  $alias  the alias of the applications table in the query
     */
    public static function limit(Builder $query, array $boundJobIds, string $alias = 'a'): Builder
    {
        if ($boundJobIds === []) {
            return $query;
        }

        return $query->where(static fn (Builder $either) => $either->whereNotIn("{$alias}.job_id", $boundJobIds)->orWhereNotNull("{$alias}.hiring_manager_visible_at"));
    }
}
