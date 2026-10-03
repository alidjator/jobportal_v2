<?php

namespace Tests\Feature\Database\Seeders\Reference;

use Database\Seeders\Reference\ReferenceDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\FeatureTestCase;

final class ReferenceDataSeederTest extends FeatureTestCase
{
    private const EXPECTED_COUNTS = [
        'reference_job_types' => 4,
        'reference_job_levels' => 3,
        'reference_work_times' => 3,
        'reference_experience_levels' => 6,
        'reference_education_levels' => 5,
        'reference_salary_periods' => 3,
        'reference_team_sizes' => 8,
        'reference_religions' => 6,
        'reference_organization_types' => 6,
        'reference_languages' => 25,
        'reference_job_categories' => 8,
        'reference_job_roles' => 24,
        'reference_industries' => 25,
        'reference_skills' => 29,
        'reference_nationalities' => 25,
        'reference_benefits' => 12,
    ];

    public function test_it_fills_every_seeded_master(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        foreach (self::EXPECTED_COUNTS as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table);
        }
    }

    public function test_tags_and_locations_are_not_part_of_it(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        foreach (['reference_tags', 'reference_provinces', 'reference_cities', 'reference_subdistricts', 'reference_villages', 'reference_postal_codes'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
    }

    public function test_seeding_twice_does_not_duplicate_any_row(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(ReferenceDataSeeder::class);

        foreach (self::EXPECTED_COUNTS as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table);
        }
    }
}
