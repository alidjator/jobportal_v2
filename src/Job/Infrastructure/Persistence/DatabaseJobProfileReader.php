<?php

namespace Src\Job\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Job\Application\Browse\JobProfileReader;
use Src\Job\Application\Browse\JobProfileRecord;

final readonly class DatabaseJobProfileReader implements JobProfileReader
{
    /** Name of the search filter => column of `job_jobs`. */
    private const array ID_COLUMNS = [
        'category_id' => 'category_id',
        'role_id' => 'role_id',
        'job_type_id' => 'job_type_id',
        'job_level_id' => 'job_level_id',
        'experience_level_id' => 'experience_level_id',
        'education_level_id' => 'education_level_id',
        'work_time_id' => 'work_time_id',
        'province_id' => 'province_id',
        'city_id' => 'city_id',
        'company_id' => 'company_id',
    ];

    public function __construct(private ConnectionInterface $connection) {}

    public function find(int $jobId): ?JobProfileRecord
    {
        $row = $this->connection->table('job_jobs')->where('id', $jobId)->first();

        if ($row === null) {
            return null;
        }

        $ids = [];

        foreach (self::ID_COLUMNS as $filter => $column) {
            $ids[$filter] = $row->{$column} === null ? null : (int) $row->{$column};
        }

        // A job with several company locations is in every one of their provinces and cities.
        foreach (['province_id', 'city_id'] as $filter) {
            $others = array_map('intval', $this->connection->table('job_job_locations')->where('job_id', $jobId)->whereNotNull($filter)->pluck($filter)->all());

            if ($others !== []) {
                $ids[$filter] = array_values(array_unique([...($ids[$filter] === null ? [] : [$ids[$filter]]), ...$others]));
            }
        }

        return new JobProfileRecord(
            (int) $row->id,
            (string) $row->title,
            (int) $row->company_id,
            $row->published_at === null ? null : new DateTimeImmutable((string) $row->published_at, new DateTimeZone('UTC')),
            $ids,
            array_map('intval', $this->connection->table('job_job_skills')->where('job_id', $jobId)->pluck('skill_id')->all()),
            array_map('intval', $this->connection->table('job_job_tags')->where('job_id', $jobId)->pluck('tag_id')->all()),
            $row->work_mode === null ? null : (string) $row->work_mode,
            (bool) $row->salary_visible,
            $row->salary_period_id === null ? null : (int) $row->salary_period_id,
            $row->salary_max !== null ? (int) $row->salary_max : ($row->salary_min !== null ? (int) $row->salary_min : null),
            (string) ($row->search_vector ?? ''),
        );
    }
}
