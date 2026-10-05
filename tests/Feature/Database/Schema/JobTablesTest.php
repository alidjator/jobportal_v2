<?php

namespace Tests\Feature\Database\Schema;

use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesReferenceRows;
use Tests\FeatureTestCase;

final class JobTablesTest extends FeatureTestCase
{
    use CreatesReferenceRows;

    /** @param  array<string, mixed>  $attributes */
    private function job(array $attributes = []): int
    {
        return (int) DB::table('job_jobs')->insertGetId([
            'company_id' => $this->companyRowId(), 'title' => 'Backend Developer', 'slug' => 'backend-developer', 'created_by_user_id' => 1,
            'created_at' => now(), 'updated_at' => now(), ...$attributes,
        ]);
    }

    /** @return array<string, int> every reference column of a job pointing to a real row */
    private function fullReferences(): array
    {
        return [
            'category_id' => $this->referenceId('reference_job_categories', 'engineering'),
            'role_id' => $this->referenceId('reference_job_roles', 'backend-developer'),
            'job_type_id' => $this->referenceId('reference_job_types', 'full-time'),
            'job_level_id' => $this->referenceId('reference_job_levels', 'junior'),
            'work_time_id' => $this->referenceId('reference_work_times', 'day-shift'),
            'experience_level_id' => $this->referenceId('reference_experience_levels', 'one-to-three-years'),
            'education_level_id' => $this->referenceId('reference_education_levels', 'bachelor', ['rank' => 5]),
            'salary_period_id' => $this->referenceId('reference_salary_periods', 'monthly'),
            ...$this->locationChain(),
        ];
    }

    /** @return list<string> */
    private function foreignKeyColumns(string $table): array
    {
        return collect(Schema::getForeignKeys($table))->flatMap(fn (array $key): array => $key['columns'])->sort()->values()->all();
    }

    public function test_a_job_needs_only_a_company_a_title_a_slug_and_a_creator_and_starts_as_a_draft(): void
    {
        $row = DB::table('job_jobs')->find($this->job());

        $this->assertSame('draft', $row->status);
        $this->assertSame(1, (int) $row->salary_visible);
        $this->assertNull($row->description);
        $this->assertNull($row->deadline);
        $this->assertNull($row->work_mode);
    }

    public function test_a_job_with_every_reference_filled_in_is_accepted(): void
    {
        $row = DB::table('job_jobs')->find($this->job($this->fullReferences()));

        $this->assertNotNull($row->category_id);
        $this->assertNotNull($row->postal_code_id);
    }

    public function test_the_database_refuses_an_unknown_status(): void
    {
        $this->expectException(QueryException::class);

        $this->job(['status' => 'published']);
    }

    public function test_every_status_of_the_design_is_accepted(): void
    {
        foreach (['draft', 'pending_review', 'active', 'closed', 'expired', 'rejected', 'taken_down'] as $status) {
            $this->assertSame($status, DB::table('job_jobs')->find($this->job(['status' => $status]))->status);
        }
    }

    public function test_the_database_refuses_an_unknown_work_mode_but_accepts_the_three_and_none(): void
    {
        foreach (['onsite', 'hybrid', 'remote', null] as $mode) {
            $this->assertSame($mode, DB::table('job_jobs')->find($this->job(['work_mode' => $mode]))->work_mode);
        }

        $this->expectException(QueryException::class);

        $this->job(['work_mode' => 'office']);
    }

    public function test_a_long_description_fits(): void
    {
        $description = str_repeat('Ünïcode tëxt ', 2000);

        $this->assertSame($description, DB::table('job_jobs')->find($this->job(['description' => $description]))->description);
    }

    public function test_the_relations_are_unique_per_job_and_item(): void
    {
        $job = $this->job();
        $items = [
            ['job_job_skills', 'skill_id', $this->skillId('a'), $this->skillId('b')],
            ['job_job_tags', 'tag_id', $this->tagId('a'), $this->tagId('b')],
            ['job_job_benefits', 'benefit_id', $this->benefitId('a'), $this->benefitId('b')],
            ['job_assignments', 'user_id', 5, 6],
        ];

        foreach ($items as [$table, $column, $first, $second]) {
            DB::table($table)->insert(['job_id' => $job, $column => $first]);
            DB::table($table)->insert(['job_id' => $job, $column => $second]);

            try {
                DB::table($table)->insert(['job_id' => $job, $column => $first]);
                $this->fail("{$table} accepted a duplicate");
            } catch (UniqueConstraintViolationException) {
                $this->assertSame(2, DB::table($table)->where('job_id', $job)->count(), $table);
            }
        }
    }

    public function test_a_relation_needs_an_existing_job_and_goes_away_with_it(): void
    {
        try {
            DB::table('job_job_skills')->insert(['job_id' => 999999, 'skill_id' => $this->skillId()]);
            $this->fail('A skill was attached to a job that does not exist.');
        } catch (QueryException) {
            $this->assertSame(0, DB::table('job_job_skills')->count());
        }

        $job = $this->job();
        DB::table('job_job_skills')->insert(['job_id' => $job, 'skill_id' => $this->skillId()]);
        DB::table('job_job_tags')->insert(['job_id' => $job, 'tag_id' => $this->tagId()]);
        DB::table('job_job_benefits')->insert(['job_id' => $job, 'benefit_id' => $this->benefitId()]);
        DB::table('job_assignments')->insert(['job_id' => $job, 'user_id' => 1]);

        DB::table('job_jobs')->where('id', $job)->delete();

        foreach (['job_job_skills', 'job_job_tags', 'job_job_benefits', 'job_assignments'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
    }

    // ----- foreign keys to reference data and companies -----

    public function test_the_job_table_has_foreign_keys_to_the_company_and_the_reference_data_only(): void
    {
        $this->assertSame(
            ['category_id', 'city_id', 'company_id', 'education_level_id', 'experience_level_id', 'job_level_id', 'job_type_id', 'postal_code_id',
                'province_id', 'role_id', 'salary_period_id', 'subdistrict_id', 'village_id', 'work_time_id'],
            $this->foreignKeyColumns('job_jobs'),
        );
    }

    public function test_the_columns_that_point_to_accounts_and_admins_have_no_foreign_key(): void
    {
        $this->assertNotContains('created_by_user_id', $this->foreignKeyColumns('job_jobs'));
        $this->assertNotContains('reviewed_by_admin_id', $this->foreignKeyColumns('job_jobs'));
        $this->assertSame(['job_id'], $this->foreignKeyColumns('job_assignments'), 'only the job; the member columns have none');
    }

    public function test_the_pivots_point_to_their_reference_tables(): void
    {
        $this->assertSame(['job_id', 'skill_id'], $this->foreignKeyColumns('job_job_skills'));
        $this->assertSame(['job_id', 'tag_id'], $this->foreignKeyColumns('job_job_tags'));
        $this->assertSame(['benefit_id', 'job_id'], $this->foreignKeyColumns('job_job_benefits'));
    }

    public function test_the_database_refuses_a_reference_that_does_not_exist(): void
    {
        $columns = array_keys($this->fullReferences());
        $columns[] = 'company_id';

        foreach ($columns as $column) {
            try {
                $this->job([$column => 999999]);
                $this->fail("{$column} accepted an id that does not exist");
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, DB::table('job_jobs')->count());

        $job = $this->job();

        foreach ([['job_job_skills', 'skill_id'], ['job_job_tags', 'tag_id'], ['job_job_benefits', 'benefit_id']] as [$table, $column]) {
            try {
                DB::table($table)->insert(['job_id' => $job, $column => 999999]);
                $this->fail("{$table} accepted an id that does not exist");
            } catch (QueryException) {
                $this->assertSame(0, DB::table($table)->count(), $table);
            }
        }
    }

    public function test_a_referenced_row_cannot_be_deleted_while_a_job_uses_it(): void
    {
        $references = $this->fullReferences();
        $this->job($references);

        foreach ([
            ['reference_job_categories', 'category_id'], ['reference_job_levels', 'job_level_id'], ['reference_provinces', 'province_id'],
            ['reference_postal_codes', 'postal_code_id'], ['company_companies', 'company_id'],
        ] as [$table, $column]) {
            try {
                DB::table($table)->where('id', $references[$column] ?? DB::table('job_jobs')->value($column))->delete();
                $this->fail("{$table} row was deleted while a job uses it");
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
