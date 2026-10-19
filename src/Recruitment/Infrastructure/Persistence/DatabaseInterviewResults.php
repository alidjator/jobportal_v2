<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Application\InterviewResult;
use Src\Recruitment\Application\InterviewResults;
use Src\Recruitment\Application\LatestInterview;
use Src\Recruitment\Application\QuestionScore;
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

    public function latestInterviews(array $applicationIds, int $viewerId): array
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
            ->get(['i.application_id', 'i.id', 'i.created_at', 'i.starts_at', 'i.kit_id', 'i.kit_name'])
            ->unique('application_id');

        if ($latest->isEmpty()) {
            return [];
        }

        $interviewIds = $latest->map(static fn (stdClass $row): int => (int) $row->id)->values()->all();
        $results = $this->forInterviews($interviewIds);
        $locked = $this->lockedFor($interviewIds, $viewerId);
        $byApplication = [];

        foreach ($latest as $row) {
            $id = (int) $row->id;
            $isLocked = isset($locked[$id]);

            $byApplication[(int) $row->application_id] = new LatestInterview(
                (int) $row->application_id,
                $id,
                new DateTimeImmutable((string) $row->starts_at, new DateTimeZone('UTC')),
                $row->kit_id === null ? null : (int) $row->kit_id,
                $row->kit_name === null ? null : (string) $row->kit_name,
                $isLocked,
                $isLocked ? null : ($results[$id] ?? InterviewResult::none()),
            );
        }

        return $byApplication;
    }

    public function latestForApplications(array $applicationIds, int $viewerId): array
    {
        $results = [];

        foreach ($this->latestInterviews($applicationIds, $viewerId) as $applicationId => $latest) {
            if ($latest->result instanceof InterviewResult) {
                $results[$applicationId] = $latest->result;
            }
        }

        return $results;
    }

    public function questionScores(array $interviewIds): array
    {
        if ($interviewIds === []) {
            return [];
        }

        $rows = $this->connection->table('recruitment_interview_questions as q')
            ->leftJoin('recruitment_interview_scorecard_answers as a', function ($join): void {
                $join->on('a.question_id', '=', 'q.id')->whereNotNull('a.score');
            })
            ->leftJoin('recruitment_interview_scorecards as s', function ($join): void {
                $join->on('s.id', '=', 'a.scorecard_id')->where('s.status', '=', ScorecardStatus::Submitted->value);
            })
            ->whereIn('q.interview_id', $interviewIds)
            ->groupBy('q.id', 'q.interview_id', 'q.position', 'q.text')
            ->orderBy('q.interview_id')
            ->orderBy('q.position')
            ->selectRaw('q.interview_id, q.text, AVG(a.score) FILTER (WHERE s.id IS NOT NULL) as average, COUNT(s.id) as scores')
            ->get();

        $byInterview = [];

        foreach ($rows as $row) {
            $byInterview[(int) $row->interview_id][] = new QuestionScore((string) $row->text, $row->average === null ? null : round((float) $row->average, 2), (int) $row->scores);
        }

        return $byInterview;
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
