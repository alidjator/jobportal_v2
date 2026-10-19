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
            ->map(static fn (stdClass $row): KitQuestion => self::toQuestion($row))
            ->all();
    }

    public function withIds(int $interviewId): array
    {
        return $this->connection->table('recruitment_interview_questions')
            ->where('interview_id', $interviewId)
            ->orderBy('position')
            ->get()
            ->map(static fn (stdClass $row): InterviewQuestion => new InterviewQuestion((int) $row->id, self::toQuestion($row)))
            ->all();
    }

    public function replace(int $interviewId, array $questions): void
    {
        $this->connection->table('recruitment_interview_questions')->where('interview_id', $interviewId)->delete();

        if ($questions === []) {
            return;
        }

        $this->connection->table('recruitment_interview_questions')->insert(array_map(
            static fn (KitQuestion $question, int $index): array => ['interview_id' => $interviewId, 'position' => $index + 1, 'text' => $question->text, 'guidance' => $question->guidance, 'weight' => $question->weight, 'anchor_1' => $question->anchor1, 'anchor_3' => $question->anchor3, 'anchor_5' => $question->anchor5],
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

    private static function toQuestion(stdClass $row): KitQuestion
    {
        return new KitQuestion(
            (string) $row->text,
            $row->guidance === null ? null : (string) $row->guidance,
            (int) $row->weight,
            $row->anchor_1 === null ? null : (string) $row->anchor_1,
            $row->anchor_3 === null ? null : (string) $row->anchor_3,
            $row->anchor_5 === null ? null : (string) $row->anchor_5,
        );
    }
}
