<?php

namespace Src\Job\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Job\Domain\Job;
use Src\Job\Domain\JobContent;
use Src\Job\Domain\JobRepository;
use Src\Job\Domain\JobStatus;
use Src\Job\Domain\WorkMode;
use Src\Shared\Application\Clock;
use Src\Shared\Application\References\TagRegistry;
use stdClass;

final readonly class DatabaseJobRepository implements JobRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    private const string DATE_FORMAT = 'Y-m-d';

    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
        private TagRegistry $tags,
        private JobSearchVectors $vectors,
    ) {}

    public function find(int $id): ?Job
    {
        $row = $this->connection->table('job_jobs')->where('id', $id)->first();

        return $row === null ? null : $this->toJob($row);
    }

    public function titles(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $titles = [];

        foreach ($this->connection->table('job_jobs')->whereIn('id', $ids)->get(['id', 'title']) as $row) {
            $titles[(int) $row->id] = (string) $row->title;
        }

        return $titles;
    }

    public function lock(int $id): void
    {
        $this->connection->table('job_jobs')->where('id', $id)->lockForUpdate()->first(['id']);
    }

    public function create(Job $job): Job
    {
        $now = $this->clock->now()->format(self::TIME_FORMAT);

        $id = (int) $this->connection->table('job_jobs')->insertGetId([
            ...$this->columns($job),
            'company_id' => $job->companyId,
            'created_by_user_id' => $job->createdByUserId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->syncLists($id, $job->content);
        $this->vectors->refresh($id);

        return $this->find($id) ?? throw new \LogicException("Job {$id} was just created.");
    }

    public function save(Job $job): void
    {
        $this->connection->table('job_jobs')->where('id', $job->id)->update([
            ...$this->columns($job),
            'updated_at' => $this->clock->now()->format(self::TIME_FORMAT),
        ]);

        $this->syncLists((int) $job->id, $job->content);
        $this->vectors->refresh((int) $job->id);
    }

    public function delete(int $id): void
    {
        // The lists and the assignments go with the job (cascade).
        $this->connection->table('job_jobs')->where('id', $id)->delete();
    }

    public function idsDueForExpiry(DateTimeImmutable $today, int $limit): array
    {
        return array_map('intval', $this->connection->table('job_jobs')
            ->where('status', JobStatus::Active->value)
            ->where('deadline', '<', $today->format(self::DATE_FORMAT))
            ->orderBy('deadline')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->all());
    }

    /** @return array<string, mixed> */
    private function columns(Job $job): array
    {
        $content = $job->content;

        return [
            'title' => $content->title,
            'slug' => $job->slug,
            'status' => $job->status->value,
            'status_reason' => $job->statusReason,
            'description' => $content->description,
            'category_id' => $content->categoryId,
            'role_id' => $content->roleId,
            'job_type_id' => $content->jobTypeId,
            'job_level_id' => $content->jobLevelId,
            'work_time_id' => $content->workTimeId,
            'experience_level_id' => $content->experienceLevelId,
            'education_level_id' => $content->educationLevelId,
            'vacancies' => $content->vacancies,
            'deadline' => $content->deadline?->format(self::DATE_FORMAT),
            'salary_min' => $content->salaryMin,
            'salary_max' => $content->salaryMax,
            'salary_period_id' => $content->salaryPeriodId,
            'salary_visible' => $content->salaryVisible,
            'work_mode' => $content->workMode?->value,
            'province_id' => $content->provinceId,
            'city_id' => $content->cityId,
            'subdistrict_id' => $content->subdistrictId,
            'village_id' => $content->villageId,
            'postal_code_id' => $content->postalCodeId,
            'address_line' => $content->addressLine,
            'published_at' => $job->publishedAt?->format(self::TIME_FORMAT),
            'reviewed_at' => $job->reviewedAt?->format(self::TIME_FORMAT),
            'reviewed_by_admin_id' => $job->reviewedByAdminId,
            'report_hold_at' => $job->reportHoldAt?->format(self::TIME_FORMAT),
            'requisition_id' => $job->requisitionId,
        ];
    }

    /** Brings the skills, tags and benefits of the job to the given lists: removes what is gone and adds what is new, so unchanged rows keep their time. */
    private function syncLists(int $jobId, JobContent $content): void
    {
        $this->sync('job_job_skills', 'skill_id', $jobId, $content->skillIds);
        $this->sync('job_job_tags', 'tag_id', $jobId, $this->tags->idsFor($content->tags));
        $this->sync('job_job_benefits', 'benefit_id', $jobId, $content->benefitIds);
    }

    /** @param  list<int>  $wanted */
    private function sync(string $table, string $column, int $jobId, array $wanted): void
    {
        $current = $this->listOf($table, $column, $jobId);

        $gone = array_values(array_diff($current, $wanted));

        if ($gone !== []) {
            $this->connection->table($table)->where('job_id', $jobId)->whereIn($column, $gone)->delete();
        }

        $time = $this->clock->now()->format(self::TIME_FORMAT);
        $new = array_values(array_diff($wanted, $current));

        if ($new !== []) {
            $this->connection->table($table)->insert(array_map(
                static fn (int $id): array => ['job_id' => $jobId, $column => $id, 'created_at' => $time],
                $new,
            ));
        }
    }

    /** @return list<int> */
    private function listOf(string $table, string $column, int $jobId): array
    {
        return array_map('intval', $this->connection->table($table)->where('job_id', $jobId)->orderBy('id')->pluck($column)->all());
    }

    private function toJob(stdClass $row): Job
    {
        $id = (int) $row->id;
        $tagIds = $this->listOf('job_job_tags', 'tag_id', $id);
        $tagNames = $this->tags->names($tagIds);

        return new Job(
            $id,
            (int) $row->company_id,
            (string) $row->slug,
            JobStatus::from((string) $row->status),
            $row->status_reason === null ? null : (string) $row->status_reason,
            new JobContent(
                (string) $row->title,
                $row->description === null ? null : (string) $row->description,
                $this->nullableInt($row->category_id),
                $this->nullableInt($row->role_id),
                $this->nullableInt($row->job_type_id),
                $this->nullableInt($row->job_level_id),
                $this->nullableInt($row->work_time_id),
                $this->nullableInt($row->experience_level_id),
                $this->nullableInt($row->education_level_id),
                $this->nullableInt($row->vacancies),
                $row->deadline === null ? null : $this->time((string) $row->deadline),
                $this->nullableInt($row->salary_min),
                $this->nullableInt($row->salary_max),
                $this->nullableInt($row->salary_period_id),
                (bool) $row->salary_visible,
                $row->work_mode === null ? null : WorkMode::from((string) $row->work_mode),
                $this->nullableInt($row->province_id),
                $this->nullableInt($row->city_id),
                $this->nullableInt($row->subdistrict_id),
                $this->nullableInt($row->village_id),
                $this->nullableInt($row->postal_code_id),
                $row->address_line === null ? null : (string) $row->address_line,
                $this->listOf('job_job_skills', 'skill_id', $id),
                array_values(array_filter(array_map(static fn (int $tagId): ?string => $tagNames[$tagId] ?? null, $tagIds), static fn (?string $name): bool => $name !== null)),
                $this->listOf('job_job_benefits', 'benefit_id', $id),
            ),
            (int) $row->created_by_user_id,
            $row->published_at === null ? null : $this->time((string) $row->published_at),
            $row->reviewed_at === null ? null : $this->time((string) $row->reviewed_at),
            $this->nullableInt($row->reviewed_by_admin_id),
            $row->created_at === null ? null : $this->time((string) $row->created_at),
            $row->updated_at === null ? null : $this->time((string) $row->updated_at),
            $row->report_hold_at === null ? null : $this->time((string) $row->report_hold_at),
            $this->nullableInt($row->requisition_id),
        );
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    private function time(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
