<?php

namespace Tests\Feature\Database\Schema;

use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\FeatureTestCase;

final class JobTablesTest extends FeatureTestCase
{
    private function job(array $attributes = []): int
    {
        return (int) DB::table('job_jobs')->insertGetId([
            'company_id' => 1, 'title' => 'Backend Developer', 'slug' => 'backend-developer', 'created_by_user_id' => 1,
            'created_at' => now(), 'updated_at' => now(), ...$attributes,
        ]);
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

        foreach ([['job_job_skills', 'skill_id'], ['job_job_tags', 'tag_id'], ['job_job_benefits', 'benefit_id'], ['job_assignments', 'user_id']] as [$table, $column]) {
            DB::table($table)->insert(['job_id' => $job, $column => 5]);
            DB::table($table)->insert(['job_id' => $job, $column => 6]);

            try {
                DB::table($table)->insert(['job_id' => $job, $column => 5]);
                $this->fail("{$table} accepted a duplicate");
            } catch (UniqueConstraintViolationException) {
                $this->assertSame(2, DB::table($table)->where('job_id', $job)->count(), $table);
            }
        }
    }

    public function test_a_relation_needs_an_existing_job_and_goes_away_with_it(): void
    {
        try {
            DB::table('job_job_skills')->insert(['job_id' => 999999, 'skill_id' => 1]);
            $this->fail('A skill was attached to a job that does not exist.');
        } catch (QueryException) {
            $this->assertSame(0, DB::table('job_job_skills')->count());
        }

        $job = $this->job();
        DB::table('job_job_skills')->insert(['job_id' => $job, 'skill_id' => 1]);
        DB::table('job_job_tags')->insert(['job_id' => $job, 'tag_id' => 1]);
        DB::table('job_job_benefits')->insert(['job_id' => $job, 'benefit_id' => 1]);
        DB::table('job_assignments')->insert(['job_id' => $job, 'user_id' => 1]);

        DB::table('job_jobs')->where('id', $job)->delete();

        foreach (['job_job_skills', 'job_job_tags', 'job_job_benefits', 'job_assignments'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
    }

    public function test_the_columns_that_point_to_other_contexts_have_no_foreign_keys(): void
    {
        $foreignKeys = collect(Schema::getForeignKeys('job_jobs'))->flatMap(fn (array $key): array => $key['columns'])->all();

        $this->assertSame([], $foreignKeys);
    }
}
