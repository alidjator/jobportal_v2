<?php

namespace Src\Job\Infrastructure;

use Src\Job\Application\Browse\JobContentSnapshot;
use Src\Job\Application\JobListItem;
use Src\Job\Application\JobNotificationRecipients;
use Src\Job\Application\JobReader;
use Src\Job\Application\JobVisibility;
use Src\Job\Domain\JobAssignee;
use Src\Job\Domain\JobAssignmentRepository;
use Src\Job\Domain\JobAssignmentRole;
use Src\Job\Domain\JobRepository;
use Src\Job\Domain\JobStatus;
use Src\Shared\Application\Company\PublicCompanies;
use Src\Shared\Application\Job\AssignedJob;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Job\JobSummary;

final readonly class RepositoryJobDirectory implements JobDirectory
{
    public function __construct(
        private JobRepository $jobs,
        private JobAssignmentRepository $assignments,
        private JobVisibility $visibility,
        private PublicCompanies $companies,
        private JobReader $reader,
        private JobNotificationRecipients $recipients,
        private JobContentSnapshot $contentSnapshots,
    ) {}

    public function visibleToMember(int $jobId, int $companyId, int $userId, bool $seesAllJobs): ?JobSummary
    {
        $job = $this->jobs->find($jobId);

        if ($job === null || $job->companyId !== $companyId) {
            return null;
        }

        if (! $seesAllJobs && ! in_array($userId, $this->assignments->userIds($jobId), true)) {
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

    public function hiringManagerJobIds(int $companyId, int $userId): array
    {
        return array_values(array_filter(
            $this->assignments->hiringManagerJobIdsOf($userId),
            fn (int $jobId): bool => $this->jobs->find($jobId)?->companyId === $companyId,
        ));
    }

    public function hiringManagerUserIds(int $jobId): array
    {
        return array_values(array_map(
            static fn (JobAssignee $assignee): int => $assignee->userId,
            array_filter($this->assignments->assignees($jobId), static fn (JobAssignee $assignee): bool => $assignee->role === JobAssignmentRole::HiringManager),
        ));
    }

    public function hiringManagerBoundary(int $jobId): ?int
    {
        return $this->jobs->find($jobId)?->hiringManagerFromStageId;
    }

    public function hiringManagerBoundaries(int $companyId): array
    {
        return $this->reader->hiringManagerBoundaries($companyId);
    }

    public function assignedJobs(int $companyId, int $userId): array
    {
        return array_map(
            fn (JobListItem $job): AssignedJob => new AssignedJob($job->id, $job->title, $job->status->value, $job->deadline),
            $this->reader->assignedTo($companyId, $userId),
        );
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

    public function notificationRecipients(int $jobId): array
    {
        return $this->recipients->ofJob($jobId);
    }

    public function titles(array $jobIds): array
    {
        return $this->jobs->titles($jobIds);
    }

    public function isOnHold(int $jobId): bool
    {
        $job = $this->jobs->find($jobId);

        return $job !== null && $job->status === JobStatus::Active && $job->isOnHold();
    }

    public function isTakenDown(int $jobId): bool
    {
        return $this->jobs->find($jobId)?->status === JobStatus::TakenDown;
    }

    public function vacancies(int $jobId): ?int
    {
        return $this->jobs->find($jobId)?->content->vacancies;
    }

    public function statusOf(int $jobId): ?string
    {
        return $this->jobs->find($jobId)?->status->value;
    }

    public function lock(int $jobId): void
    {
        $this->jobs->lock($jobId);
    }

    public function contentSnapshot(int $jobId): ?array
    {
        return $this->contentSnapshots->of($jobId);
    }
}
