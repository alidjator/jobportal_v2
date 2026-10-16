<?php

namespace Src\Job\Infrastructure\Tasks;

use Illuminate\Database\ConnectionInterface;
use Src\Job\Domain\JobStatus;
use Src\Shared\Application\Tasks\TaskProvider;
use Src\Shared\Application\Tasks\TaskScope;

/** Active jobs that went live at least seven days ago and have no application. */
final readonly class JobsWithoutApplicantsTask implements TaskProvider
{
    private const int DAYS = 7;

    public function __construct(private ConnectionInterface $connection) {}

    public function key(): string
    {
        return 'jobs_without_applicants';
    }

    public function order(): int
    {
        return 62;
    }

    public function permission(): ?string
    {
        return null;
    }

    public function filter(TaskScope $scope): string
    {
        return 'company/jobs?filter[status]=active&filter[applications]=0';
    }

    public function count(TaskScope $scope): int
    {
        return $this->connection->table('job_jobs as j')
            ->where('j.company_id', $scope->companyId)
            ->when($scope->jobIds !== null, fn ($query) => $query->whereIn('j.id', $scope->jobIds ?? []))
            ->where('j.status', JobStatus::Active->value)
            ->where('j.published_at', '<=', $scope->now->modify('-'.self::DAYS.' days')->format('Y-m-d H:i:s'))
            ->whereNotExists(fn ($query) => $query->from('recruitment_applications as a')->whereColumn('a.job_id', 'j.id'))
            ->count();
    }
}
