<?php

namespace Src\Job\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Job\Domain\JobStatus;
use Src\Shared\Application\Job\PublishedJob;
use Src\Shared\Application\Job\PublishedJobs;

final readonly class DatabasePublishedJobs implements PublishedJobs
{
    private const int CHUNK = 1000;

    public function __construct(private ConnectionInterface $connection) {}

    public function among(array $jobIds): array
    {
        $jobs = [];

        foreach (array_chunk($jobIds, self::CHUNK) as $chunk) {
            foreach ($this->hydrate($this->published()->whereIn('id', $chunk)) as $job) {
                $jobs[$job->id] = $job;
            }
        }

        return $jobs;
    }

    public function liveOfCompany(int $companyId): array
    {
        return $this->hydrate($this->published()->where('company_id', $companyId)->where('status', JobStatus::Active->value));
    }

    private function published(): Builder
    {
        return $this->connection->table('job_jobs')->whereNotNull('published_at');
    }

    /** @return list<PublishedJob> */
    private function hydrate(Builder $query): array
    {
        $rows = $query->orderBy('id')->get(['id', 'company_id', 'title', 'category_id', 'published_at', 'vacancies']);
        $assignees = [];

        foreach (array_chunk($rows->pluck('id')->all(), self::CHUNK) as $chunk) {
            foreach ($this->connection->table('job_assignments')->whereIn('job_id', $chunk)->orderBy('user_id')->get(['job_id', 'user_id']) as $row) {
                $assignees[(int) $row->job_id][] = (int) $row->user_id;
            }
        }

        $jobs = [];

        foreach ($rows as $row) {
            $jobs[] = new PublishedJob(
                (int) $row->id,
                (int) $row->company_id,
                (string) $row->title,
                $row->category_id === null ? null : (int) $row->category_id,
                new DateTimeImmutable((string) $row->published_at, new DateTimeZone('UTC')),
                $row->vacancies === null ? null : (int) $row->vacancies,
                $assignees[(int) $row->id] ?? [],
            );
        }

        return $jobs;
    }
}
