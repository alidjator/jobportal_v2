<?php

namespace Src\LegacyImport\Infrastructure;

use Illuminate\Database\ConnectionInterface;
use Src\LegacyImport\Application\JobWriter;
use Src\LegacyImport\Application\NewJob;

final readonly class DatabaseJobWriter implements JobWriter
{
    public function __construct(private ConnectionInterface $connection) {}

    public function insert(NewJob $job): int
    {
        $id = (int) $this->connection->table('job_jobs')->insertGetId([
            'company_id' => $job->companyId,
            'title' => $job->title,
            'slug' => $job->slug,
            'status' => $job->status,
            'description' => $job->description,
            'category_id' => $job->categoryId,
            'role_id' => $job->roleId,
            'job_type_id' => $job->jobTypeId,
            'job_level_id' => $job->jobLevelId,
            'work_time_id' => $job->workTimeId,
            'experience_level_id' => $job->experienceLevelId,
            'education_level_id' => $job->educationLevelId,
            'vacancies' => $job->vacancies,
            'deadline' => $job->deadline,
            'salary_min' => $job->salaryMin,
            'salary_max' => $job->salaryMax,
            'salary_period_id' => $job->salaryPeriodId,
            'salary_visible' => $job->salaryVisible,
            'work_mode' => $job->workMode,
            'province_id' => $job->location->provinceId,
            'city_id' => $job->location->cityId,
            'subdistrict_id' => $job->location->subdistrictId,
            'village_id' => $job->location->villageId,
            'postal_code_id' => $job->location->postalCodeId,
            'address_line' => $job->addressLine,
            'published_at' => $job->publishedAt,
            'created_by_user_id' => $job->creatorUserId,
            'created_at' => $job->createdAt,
            'updated_at' => $job->updatedAt,
        ]);

        // The full text vector of the search (see `JobSearchVectors`): title, company name and description; the legacy jobs have no tags.
        $grammar = $this->connection->table('job_jobs')->getGrammar();
        $jobs = $grammar->wrapTable('job_jobs');
        $companies = $grammar->wrapTable('company_companies');
        $this->connection->update(
            "UPDATE {$jobs} AS j SET search_vector = setweight(to_tsvector('simple', immutable_unaccent(j.title)), 'A')"
            ." || setweight(to_tsvector('simple', immutable_unaccent(COALESCE((SELECT c.name FROM {$companies} c WHERE c.id = j.company_id), ''))), 'C')"
            ." || setweight(to_tsvector('simple', immutable_unaccent(regexp_replace(COALESCE(j.description, ''), '[#*_`>~\\[\\]()!|-]+', ' ', 'g'))), 'D') WHERE j.id = ?",
            [$id],
        );

        $this->insertPivot('job_job_skills', 'skill_id', $id, $job->skillIds, $job->createdAt);
        $this->insertPivot('job_job_benefits', 'benefit_id', $id, $job->benefitIds, $job->createdAt);

        $this->connection->table('job_assignments')->insert([
            'job_id' => $id,
            'user_id' => $job->creatorUserId,
            'assigned_by_user_id' => $job->creatorUserId,
            'created_at' => $job->createdAt,
        ]);

        return $id;
    }

    /** @param  list<int>  $itemIds */
    private function insertPivot(string $table, string $column, int $jobId, array $itemIds, string $at): void
    {
        if ($itemIds === []) {
            return;
        }

        $this->connection->table($table)->insert(array_map(
            static fn (int $itemId): array => ['job_id' => $jobId, $column => $itemId, 'created_at' => $at],
            $itemIds,
        ));
    }
}
