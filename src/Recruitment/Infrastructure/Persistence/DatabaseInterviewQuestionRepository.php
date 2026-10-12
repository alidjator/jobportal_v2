<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\InterviewQuestion;
use Src\Recruitment\Domain\InterviewQuestionRepository;
use Src\Recruitment\Domain\KitQuestion;
use stdClass;

final readonly class DatabaseInterviewQuestionRepository implements InterviewQuestionRepository
{
    public function __construct(private ConnectionInterface $connection) {}

    public function forInterview(int $interviewId): array
    {
        return $this->connection->table('recruitment_interview_questions')
            ->where('interview_id', $interviewId)
            ->orderBy('position')
            ->get()
            ->map(static fn (stdClass $row): KitQuestion => new KitQuestion((string) $row->text, $row->guidance === null ? null : (string) $row->guidance, (int) $row->weight))
            ->all();
    }

    public function withIds(int $interviewId): array
    {
        return $this->connection->table('recruitment_interview_questions')
            ->where('interview_id', $interviewId)
            ->orderBy('position')
            ->get()
            ->map(static fn (stdClass $row): InterviewQuestion => new InterviewQuestion((int) $row->id, new KitQuestion((string) $row->text, $row->guidance === null ? null : (string) $row->guidance, (int) $row->weight)))
            ->all();
    }

    public function replace(int $interviewId, array $questions): void
    {
        $this->connection->table('recruitment_interview_questions')->where('interview_id', $interviewId)->delete();

        if ($questions === []) {
            return;
        }

        $this->connection->table('recruitment_interview_questions')->insert(array_map(
            static fn (KitQuestion $question, int $index): array => ['interview_id' => $interviewId, 'position' => $index + 1, 'text' => $question->text, 'guidance' => $question->guidance, 'weight' => $question->weight],
            $questions,
            array_keys($questions),
        ));
    }

    public function copy(int $fromInterviewId, int $toInterviewId): void
    {
        $this->replace($toInterviewId, $this->forInterview($fromInterviewId));
    }

    public function scoringStarted(int $interviewId): bool
    {
        return $this->connection->table('recruitment_interview_scorecards')->where('interview_id', $interviewId)->exists();
    }
}
