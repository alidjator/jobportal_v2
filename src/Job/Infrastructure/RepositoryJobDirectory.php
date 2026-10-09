<?php

namespace Src\Job\Infrastructure;

use Src\Job\Application\JobVisibility;
use Src\Job\Domain\JobAssignmentRepository;
use Src\Job\Domain\JobRepository;
use Src\Job\Domain\JobStatus;
use Src\Shared\Application\Company\PublicCompanies;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Job\JobSummary;

final readonly class RepositoryJobDirectory implements JobDirectory
{
    public function __construct(
        private JobRepository $jobs,
        private JobAssignmentRepository $assignments,
        private JobVisibility $visibility,
        private PublicCompanies $companies,
    ) {}

    public function visibleToMember(int $jobId, int $companyId, int $userId, bool $isCompanyAdmin): ?JobSummary
    {
        $job = $this->jobs->find($jobId);

        if ($job === null || $job->companyId !== $companyId) {
            return null;
        }

        if (! $isCompanyAdmin && ! in_array($userId, $this->assignments->userIds($jobId), true)) {
            return null;
        }

        return new JobSummary($jobId, $companyId, $job->status->isEditable());
    }

    public function assignedJobIds(int $companyId, int $userId): array
    {
        return array_values(array_filter(
            $this->assignments->jobIdsOf($userId),
            fn (int $jobId): bool => $this->jobs->find($jobId)?->companyId === $companyId,
        ));
    }

    public function publicCompanyId(int $jobId): ?int
    {
        $job = $this->jobs->find($jobId);

        if ($job === null || ! $this->visibility->isLive($job) || $this->companies->card($job->companyId) === null) {
            return null;
        }

        return $job->companyId;
    }

    public function liveJobIds(array $jobIds): array
    {
        return array_values(array_filter($jobIds, fn (int $jobId): bool => $this->publicCompanyId($jobId) !== null));
    }

    public function assigneeUserIds(int $jobId): array
    {
        return $this->assignments->userIds($jobId);
    }

    public function titles(array $jobIds): array
    {
        return $this->jobs->titles($jobIds);
    }

    public function isTakenDown(int $jobId): bool
    {
        return $this->jobs->find($jobId)?->status === JobStatus::TakenDown;
    }

    public function lock(int $jobId): void
    {
        $this->jobs->lock($jobId);
    }
}
