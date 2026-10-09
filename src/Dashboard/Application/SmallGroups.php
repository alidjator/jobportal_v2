<?php

namespace Src\Dashboard\Application;

use Src\Shared\Application\Analytics\AnalyticsTable;

/**
 * Applies the small-group rule to a table: a row whose count is below the smallest group shows no figures (null) and is marked `suppressed`. The count itself is hidden
 * too, so nobody reads "2 applicants" off a row.
 */
final class SmallGroups
{
    /** @return array{name: string, columns: list<string>, count_column: string|null, rows: list<array<string, mixed>>} */
    public static function apply(AnalyticsTable $table, int $minGroup): array
    {
        $guarded = $table->guarded === [] ? $table->measures : $table->guarded;
        $rows = [];

        foreach ($table->rows as $row) {
            $suppressed = $table->countColumn !== null && (int) $row[$table->countColumn] < $minGroup;

            if ($suppressed) {
                foreach ($guarded as $column) {
                    $row[$column] = null;
                }
            }

            $rows[] = array_intersect_key($row, array_flip($table->columns())) + ['suppressed' => $suppressed];
        }

        return ['name' => $table->name, 'columns' => $table->columns(), 'count_column' => $table->countColumn, 'rows' => $rows];
    }
}
