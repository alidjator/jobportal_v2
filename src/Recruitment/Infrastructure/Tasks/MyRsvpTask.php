<?php

namespace Src\Recruitment\Infrastructure\Tasks;

use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Recruitment\Domain\InterviewStatus;
use Src\Recruitment\Domain\Rsvp;
use Src\Recruitment\Domain\StageType;
use Src\Recruitment\Infrastructure\Persistence\HiringManagerBound;
use Src\Shared\Application\Tasks\TaskProvider;
use Src\Shared\Application\Tasks\TaskScope;

/**
 * Upcoming interviews where the member is the main interviewer or an extra member and has not answered the invitation yet. An application that became final needs no answer
 * any more.
 */
final readonly class MyRsvpTask implements TaskProvider
{
    public function __construct(private ConnectionInterface $connection) {}

    public function key(): string
    {
        return 'my_rsvp';
    }

    public function order(): int
    {
        return 31;
    }

    public function permission(): ?string
    {
        return null;
    }

    public function filter(TaskScope $scope): string
    {
        return 'company/interviews?filter[rsvp]=pending';
    }

    public function count(TaskScope $scope): int
    {
        return HiringManagerBound::limit($this->connection->table('recruitment_interviews as i')->join('recruitment_applications as a', 'a.id', '=', 'i.application_id'), $scope->boundJobIds)
            ->where('a.company_id', $scope->companyId)
            ->when($scope->jobIds !== null, fn (Builder $query) => $query->whereIn('a.job_id', $scope->jobIds ?? []))
            ->whereNotIn('a.stage_type', [StageType::Rejected->value, StageType::Withdrawn->value, StageType::Hired->value])
            ->whereIn('i.status', [InterviewStatus::Pending->value, InterviewStatus::Confirmed->value])
            ->where('i.starts_at', '>', $scope->now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))
            ->where(function (Builder $query) use ($scope): void {
                $query->where(fn (Builder $main) => $main->where('i.interviewer_id', $scope->userId)->where('i.interviewer_rsvp', Rsvp::Pending->value))->orWhereExists(
                    fn (Builder $participant) => $participant->from('recruitment_interview_participants as p')->whereColumn('p.interview_id', 'i.id')->where('p.user_id', $scope->userId)->where('p.rsvp', Rsvp::Pending->value),
                );
            })
            ->count();
    }
}
