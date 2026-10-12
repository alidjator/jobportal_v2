<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Application\InterviewResult;
use Src\Recruitment\Application\InterviewResults;
use Src\Recruitment\Domain\ScorecardStatus;
use stdClass;

final readonly class DatabaseInterviewResults implements InterviewResults
{
    public function __construct(private ConnectionInterface $connection) {}

    public function forInterviews(array $interviewIds): array
    {
        if ($interviewIds === []) {
            return [];
        }

        $rows = $this->connection->table('recruitment_interview_scorecards')
            ->whereIn('interview_id', $interviewIds)
            ->where('status', ScorecardStatus::Submitted->value)
            ->groupBy('interview_id')
            ->selectRaw(
                'interview_id, COUNT(*) as sent, AVG(score) as average, '
                ."SUM(CASE WHEN recommendation = 'strong_no' THEN 1 ELSE 0 END) as strong_no, "
                ."SUM(CASE WHEN recommendation = 'no' THEN 1 ELSE 0 END) as no, "
                ."SUM(CASE WHEN recommendation = 'yes' THEN 1 ELSE 0 END) as yes, "
                ."SUM(CASE WHEN recommendation = 'strong_yes' THEN 1 ELSE 0 END) as strong_yes",
            )
            ->get();

        $results = [];

        foreach ($rows as $row) {
            $results[(int) $row->interview_id] = new InterviewResult(
                $row->average === null ? null : round((float) $row->average, 2),
                (int) $row->sent,
                ['strong_no' => (int) $row->strong_no, 'no' => (int) $row->no, 'yes' => (int) $row->yes, 'strong_yes' => (int) $row->strong_yes],
            );
        }

        return $results;
    }

    public function latestForApplications(array $applicationIds, int $viewerId): array
    {
        if ($applicationIds === []) {
            return [];
        }

        $latest = $this->connection->table('recruitment_interviews as i')
            ->join('recruitment_interview_scorecards as s', function ($join): void {
                $join->on('s.interview_id', '=', 'i.id')->where('s.status', '=', ScorecardStatus::Submitted->value);
            })
            ->whereIn('i.application_id', $applicationIds)
            ->distinct()
            ->orderBy('i.application_id')
            ->orderByDesc('i.created_at')
            ->orderByDesc('i.id')
            ->get(['i.application_id', 'i.id', 'i.created_at'])
            ->unique('application_id')
            ->mapWithKeys(fn (stdClass $row): array => [(int) $row->application_id => (int) $row->id]);

        if ($latest->isEmpty()) {
            return [];
        }

        $results = $this->forInterviews($latest->values()->all());
        $locked = $this->lockedFor($latest->values()->all(), $viewerId);
        $byApplication = [];

        foreach ($latest as $applicationId => $interviewId) {
            if (isset($results[$interviewId]) && ! isset($locked[$interviewId])) {
                $byApplication[$applicationId] = $results[$interviewId];
            }
        }

        return $byApplication;
    }

    /**
     * The interviews whose panel the viewer is on without having sent their scorecard.
     *
     * @param  list<int>  $interviewIds
     * @return array<int, true>
     */
    private function lockedFor(array $interviewIds, int $viewerId): array
    {
        $locked = $this->connection->table('recruitment_interviews as i')
            ->whereIn('i.id', $interviewIds)
            ->where(function ($query) use ($viewerId): void {
                $query->where('i.interviewer_id', $viewerId)
                    ->orWhereIn('i.id', $this->connection->table('recruitment_interview_participants')->where('user_id', $viewerId)->select('interview_id'));
            })
            ->whereNotIn('i.id', $this->connection->table('recruitment_interview_scorecards')->where('member_id', $viewerId)->where('status', ScorecardStatus::Submitted->value)->select('interview_id'))
            ->pluck('i.id');

        $set = [];

        foreach ($locked as $id) {
            $set[(int) $id] = true;
        }

        return $set;
    }
}
