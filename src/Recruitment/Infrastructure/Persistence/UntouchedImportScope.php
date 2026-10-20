<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use Illuminate\Database\Query\Builder;
use Src\Recruitment\Application\UntouchedImport;

/** The SQL form of `UntouchedImport`, for queries on `recruitment_applications` under the alias `a`. */
final class UntouchedImportScope
{
    /** Keeps only the applications that are not an untouched import. */
    public static function exclude(Builder $query): Builder
    {
        return $query->whereRaw('NOT (a.migrated_at IS NOT NULL AND a.stage_entered_at <= a.migrated_at)');
    }
}
