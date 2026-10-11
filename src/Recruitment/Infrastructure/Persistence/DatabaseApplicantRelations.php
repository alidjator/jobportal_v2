<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Recruitment\ApplicantRelations;

final readonly class DatabaseApplicantRelations implements ApplicantRelations
{
    public function __construct(private ConnectionInterface $connection, private JobDirectory $jobs) {}

    public function hasAppliedToJob(int $jobId, int $applicantUserId): bool
    {
        return $this->connection->table('recruitment_applications')->where('job_id', $jobId)->where('user_id', $applicantUserId)->exists();
    }

    public function appliedJobIds(int $applicantUserId, array $jobIds): array
    {
        if ($jobIds === []) {
            return [];
        }

        return $this->connection->table('recruitment_applications')
            ->where('user_id', $applicantUserId)->whereIn('job_id', $jobIds)->distinct()->pluck('job_id')
            ->map(fn (mixed $id): int => (int) $id)->values()->all();
    }

    public function appliedJobIdsOf(array $applicantUserIds, array $jobIds): array
    {
        if ($applicantUserIds === [] || $jobIds === []) {
            return [];
        }

        $applied = [];

        foreach ($this->connection->table('recruitment_applications')->whereIn('user_id', $applicantUserIds)->whereIn('job_id', $jobIds)->distinct()->get(['user_id', 'job_id']) as $row) {
            $applied[(int) $row->user_id][] = (int) $row->job_id;
        }

        return $applied;
    }

    public function appliedUserIds(int $jobId, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return $this->connection->table('recruitment_applications')
            ->where('job_id', $jobId)->whereIn('user_id', $userIds)->distinct()->pluck('user_id')
            ->map(fn (mixed $id): int => (int) $id)->values()->all();
    }

    public function hasApplied(int $companyId, int $applicantUserId): bool
    {
        return $this->connection->table('recruitment_applications')
            ->where('company_id', $companyId)
            ->where('user_id', $applicantUserId)
            ->exists();
    }

    public function canSeeApplicant(int $companyId, int $memberUserId, bool $isCompanyAdmin, int $applicantUserId): bool
    {
        if ($isCompanyAdmin) {
            return $this->hasApplied($companyId, $applicantUserId);
        }

        $assigned = $this->jobs->assignedJobIds($companyId, $memberUserId);

        return $assigned !== [] && $this->connection->table('recruitment_applications')
            ->where('company_id', $companyId)
            ->where('user_id', $applicantUserId)
            ->whereIn('job_id', $assigned)
            ->exists();
    }

    public function applicantsVisibleToRecruiter(int $companyId, int $memberUserId): array
    {
        $assigned = $this->jobs->assignedJobIds($companyId, $memberUserId);

        if ($assigned === []) {
            return [];
        }

        return $this->connection->table('recruitment_applications')
            ->where('company_id', $companyId)
            ->whereIn('job_id', $assigned)
            ->distinct()
            ->pluck('user_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    public function applicantsOfApplications(int $jobId, array $applicationIds): array
    {
        if ($applicationIds === []) {
            return [];
        }

        $map = [];

        foreach ($this->connection->table('recruitment_applications')->where('job_id', $jobId)->whereIn('id', $applicationIds)->get(['id', 'user_id']) as $row) {
            $map[(int) $row->id] = (int) $row->user_id;
        }

        return $map;
    }

    public function assigneesOfLatestApplication(int $companyId, int $applicantUserId): array
    {
        $jobId = $this->connection->table('recruitment_applications')
            ->where('company_id', $companyId)
            ->where('user_id', $applicantUserId)
            ->orderByDesc('applied_at')
            ->orderByDesc('id')
            ->value('job_id');

        return $jobId === null ? [] : $this->jobs->assigneeUserIds((int) $jobId);
    }
}
