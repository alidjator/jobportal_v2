<?php

namespace Src\Recruitment\Infrastructure\Tasks;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Src\Recruitment\Domain\InterviewStatus;
use Src\Recruitment\Domain\ScorecardStatus;
use Src\Recruitment\Domain\StageType;
use Src\Recruitment\Infrastructure\Persistence\HiringManagerBound;
use Src\Shared\Application\Tasks\TaskProvider;
use Src\Shared\Application\Tasks\TaskScope;

/**
 * Interviews where the member is on the panel (the interviewer or a participant), scoring is open (a confirmed or completed interview that has started) and their scorecard is not
 * sent. An application that became final needs no scorecard any more.
 */
final readonly class MyScorecardsTask implements TaskProvider
{
    public function __construct(private ConnectionInterface $connection) {}

    public function key(): string
    {
        return 'my_scorecards';
    }

    public function order(): int
    {
        return 30;
    }

    public function permission(): ?string
    {
        return null;
    }

    public function filter(TaskScope $scope): string
    {
        return 'company/interviews?filter[my_scorecard]=pending';
    }

    public function count(TaskScope $scope): int
    {
        return HiringManagerBound::limit($this->connection->table('recruitment_interviews as i')->join('recruitment_applications as a', 'a.id', '=', 'i.application_id'), $scope->boundJobIds)
            ->where('a.company_id', $scope->companyId)
            ->when($scope->jobIds !== null, fn (Builder $query) => $query->whereIn('a.job_id', $scope->jobIds ?? []))
            ->whereNotIn('a.stage_type', [StageType::Rejected->value, StageType::Withdrawn->value, StageType::Hired->value])
            ->whereIn('i.status', [InterviewStatus::Confirmed->value, InterviewStatus::Completed->value])
            ->where('i.starts_at', '<=', $scope->now->format('Y-m-d H:i:s'))
            ->where(function (Builder $query) use ($scope): void {
                $query->where('i.interviewer_id', $scope->userId)->orWhereExists(
                    fn (Builder $participant) => $participant->from('recruitment_interview_participants as p')->whereColumn('p.interview_id', 'i.id')->where('p.user_id', $scope->userId),
                );
            })
            ->whereNotExists(fn (Builder $sent) => $sent->from('recruitment_interview_scorecards as s')->whereColumn('s.interview_id', 'i.id')->where('s.member_id', $scope->userId)->where('s.status', ScorecardStatus::Submitted->value))
            ->count();
    }
}
