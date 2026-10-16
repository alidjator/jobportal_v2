<?php

namespace Src\Job\Infrastructure\Tasks;

use Illuminate\Database\ConnectionInterface;
use Src\Job\Domain\JobStatus;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Tasks\TaskProvider;
use Src\Shared\Application\Tasks\TaskScope;

/** Drafts the member started, or every draft of the company for a company admin. */
final readonly class JobsDraftTask implements TaskProvider
{
    public function __construct(private ConnectionInterface $connection) {}

    public function key(): string
    {
        return 'jobs_draft';
    }

    public function order(): int
    {
        return 61;
    }

    public function permission(): string
    {
        return CompanyPermission::JobsCreate->value;
    }

    public function filter(TaskScope $scope): string
    {
        return 'company/jobs?filter[status]=draft';
    }

    public function count(TaskScope $scope): int
    {
        return $this->connection->table('job_jobs')
            ->where('company_id', $scope->companyId)
            ->where('status', JobStatus::Draft->value)
            ->when(! $scope->isCompanyAdmin, fn ($query) => $query->where('created_by_user_id', $scope->userId))
            ->when($scope->jobIds !== null, fn ($query) => $query->whereIn('id', $scope->jobIds ?? []))
            ->count();
    }
}
