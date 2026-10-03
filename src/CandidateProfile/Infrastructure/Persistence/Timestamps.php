<?php

namespace Src\CandidateProfile\Infrastructure\Persistence;

use Carbon\Carbon;

/** Format timestamp seperti serialisasi model Eloquent aplikasi lama (ISO 8601 UTC). */
final class Timestamps
{
    public static function iso(?string $value): ?string
    {
        return $value === null ? null : Carbon::parse($value)->toJSON();
    }

    /**
     * @param  array<string,mixed>  $row
     * @return array<string,mixed>
     */
    public static function convert(array $row, string ...$columns): array
    {
        foreach ($columns as $column) {
            if (array_key_exists($column, $row)) {
                $row[$column] = self::iso($row[$column]);
            }
        }

        return $row;
    }
}
