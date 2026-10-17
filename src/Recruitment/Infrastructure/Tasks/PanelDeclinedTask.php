<?php

namespace Src\Recruitment\Infrastructure\Tasks;

use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Recruitment\Domain\InterviewStatus;
use Src\Recruitment\Domain\Rsvp;
use Src\Recruitment\Domain\StageType;
use Src\Recruitment\Infrastructure\Persistence\HiringManagerBound;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Tasks\TaskProvider;
use Src\Shared\Application\Tasks\TaskScope;

/** Upcoming interviews the member scheduled where a panel member answered that they cannot attend: the panel may need a replacement. */
final readonly class PanelDeclinedTask implements TaskProvider
{
    public function __construct(private ConnectionInterface $connection) {}

    public function key(): string
    {
        return 'panel_declined';
    }

    public function order(): int
    {
        return 32;
    }

    public function permission(): string
    {
        return CompanyPermission::InterviewsManage->value;
    }

    public function filter(TaskScope $scope): string
    {
        return 'company/interviews?filter[rsvp]=declined';
    }

    public function count(TaskScope $scope): int
    {
        return HiringManagerBound::limit($this->connection->table('recruitment_interviews as i')->join('recruitment_applications as a', 'a.id', '=', 'i.application_id'), $scope->boundJobIds)
            ->where('a.company_id', $scope->companyId)
            ->when($scope->jobIds !== null, fn (Builder $query) => $query->whereIn('a.job_id', $scope->jobIds ?? []))
            ->whereNotIn('a.stage_type', [StageType::Rejected->value, StageType::Withdrawn->value, StageType::Hired->value])
            ->whereIn('i.status', [InterviewStatus::Pending->value, InterviewStatus::Confirmed->value])
            ->where('i.starts_at', '>', $scope->now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))
            ->where('i.created_by', $scope->userId)
            ->where(fn (Builder $declined) => $declined->where('i.interviewer_rsvp', Rsvp::Declined->value)->orWhereExists(
                fn (Builder $participant) => $participant->from('recruitment_interview_participants as p')->whereColumn('p.interview_id', 'i.id')->where('p.rsvp', Rsvp::Declined->value),
            ))
            ->count();
    }
}
