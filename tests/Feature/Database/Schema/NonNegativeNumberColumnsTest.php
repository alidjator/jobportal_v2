<?php

namespace Tests\Feature\Database\Schema;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\FeatureTestCase;

final class NonNegativeNumberColumnsTest extends FeatureTestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function columns(): array
    {
        return [
            'experience min_years' => ['reference_experience_levels', 'min_years'],
            'experience max_years' => ['reference_experience_levels', 'max_years'],
            'education rank' => ['reference_education_levels', 'rank'],
            'team size min_size' => ['reference_team_sizes', 'min_size'],
            'team size max_size' => ['reference_team_sizes', 'max_size'],
        ];
    }

    #[DataProvider('columns')]
    public function test_the_database_rejects_a_negative_value(string $table, string $column): void
    {
        $row = ['name' => json_encode(['id' => 'Uji', 'en' => 'Test']), 'slug' => 'negative-value-test'];

        if ($table === 'reference_education_levels') {
            $row['rank'] = 1;
        }

        $row[$column] = -1;

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('violates check constraint');

        DB::table($table)->insert($row);
    }
}
