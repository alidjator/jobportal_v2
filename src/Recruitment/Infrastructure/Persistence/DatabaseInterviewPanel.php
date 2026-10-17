<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\InterviewPanel;

final readonly class DatabaseInterviewPanel implements InterviewPanel
{
    public function __construct(private ConnectionInterface $connection) {}

    public function forInterviews(array $interviewIds): array
    {
        $panels = [];

        foreach ($this->connection->table('recruitment_interview_participants')->whereIn('interview_id', $interviewIds)->orderBy('id')->get(['interview_id', 'user_id']) as $row) {
            $panels[(int) $row->interview_id][] = (int) $row->user_id;
        }

        return $panels;
    }

    public function includes(int $userId, int $applicationId): bool
    {
        return $this->connection->table('recruitment_interviews')->where('application_id', $applicationId)->where('interviewer_id', $userId)->exists()
            || $this->connection->table('recruitment_interview_participants as p')
                ->join('recruitment_interviews as i', 'i.id', '=', 'p.interview_id')
                ->where('i.application_id', $applicationId)->where('p.user_id', $userId)->exists();
    }

    public function replace(int $interviewId, array $userIds): void
    {
        $this->connection->table('recruitment_interview_participants')->where('interview_id', $interviewId)->delete();

        if ($userIds !== []) {
            $this->connection->table('recruitment_interview_participants')->insert(array_map(static fn (int $userId): array => ['interview_id' => $interviewId, 'user_id' => $userId], $userIds));
        }
    }

    public function copy(int $fromInterviewId, int $toInterviewId): void
    {
        $this->replace($toInterviewId, $this->forInterviews([$fromInterviewId])[$fromInterviewId] ?? []);
    }
}
