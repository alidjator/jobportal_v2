<?php

namespace Src\Recruitment\Infrastructure\Tasks;

use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Recruitment\Domain\InterviewStatus;
use Src\Recruitment\Domain\StageType;
use Src\Recruitment\Infrastructure\Persistence\HiringManagerBound;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Tasks\TaskProvider;
use Src\Shared\Application\Tasks\TaskScope;

/** Upcoming interviews the member scheduled or leads where the job seeker's proposed time waits for an answer (`docs/design/phase-8-interview-reschedule.md` section 2). */
final readonly class RescheduleRequestsTask implements TaskProvider
{
    public function __construct(private ConnectionInterface $connection) {}

    public function key(): string
    {
        return 'reschedule_requests';
    }

    public function order(): int
    {
        return 29;
    }

    public function permission(): string
    {
        return CompanyPermission::InterviewsManage->value;
    }

    public function filter(TaskScope $scope): string
    {
        return 'company/interviews?filter[proposal]=open';
    }

    public function count(TaskScope $scope): int
    {
        return HiringManagerBound::limit($this->connection->table('recruitment_interviews as i')->join('recruitment_applications as a', 'a.id', '=', 'i.application_id'), $scope->boundJobIds)
            ->where('a.company_id', $scope->companyId)
            ->when($scope->jobIds !== null, fn (Builder $query) => $query->whereIn('a.job_id', $scope->jobIds ?? []))
            ->whereNotIn('a.stage_type', [StageType::Rejected->value, StageType::Withdrawn->value, StageType::Hired->value])
            ->whereIn('i.status', [InterviewStatus::Pending->value, InterviewStatus::Confirmed->value])
            ->where('i.starts_at', '>', $scope->now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))
            ->whereNotNull('i.proposed_starts_at')
            ->where(fn (Builder $mine) => $mine->where('i.created_by', $scope->userId)->orWhere('i.interviewer_id', $scope->userId))
            ->count();
    }
}
