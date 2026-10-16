<?php

namespace Src\Job\Infrastructure\Tasks;

use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Job\Domain\JobStatus;
use Src\Shared\Application\Tasks\TaskProvider;
use Src\Shared\Application\Tasks\TaskScope;

/** Active jobs whose deadline is within the next seven days (Jakarta days, the deadline day included). */
final readonly class JobsClosingTask implements TaskProvider
{
    private const int DAYS = 7;

    public function __construct(private ConnectionInterface $connection) {}

    public function key(): string
    {
        return 'jobs_closing';
    }

    public function order(): int
    {
        return 60;
    }

    public function permission(): ?string
    {
        return null;
    }

    public function filter(TaskScope $scope): string
    {
        return 'company/jobs?filter[status]=active&filter[deadline_to]='.$this->last($scope);
    }

    public function count(TaskScope $scope): int
    {
        return $this->connection->table('job_jobs')
            ->where('company_id', $scope->companyId)
            ->when($scope->jobIds !== null, fn ($query) => $query->whereIn('id', $scope->jobIds ?? []))
            ->where('status', JobStatus::Active->value)
            ->whereBetween('deadline', [$scope->now->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y-m-d'), $this->last($scope)])
            ->count();
    }

    private function last(TaskScope $scope): string
    {
        return $scope->now->setTimezone(new DateTimeZone('Asia/Jakarta'))->modify('+'.self::DAYS.' days')->format('Y-m-d');
    }
}
