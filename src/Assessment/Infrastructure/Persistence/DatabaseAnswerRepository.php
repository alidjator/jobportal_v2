<?php

namespace Src\Assessment\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Assessment\Domain\Answer;
use Src\Assessment\Domain\AnswerRepository;
use Src\Assessment\Domain\Drawing;
use Src\Assessment\Domain\DrawingSource;
use Src\Assessment\Domain\SpeedColumnResult;
use Src\Shared\Application\Clock;
use stdClass;

final readonly class DatabaseAnswerRepository implements AnswerRepository
{
    private const string ANSWERS = 'assessment_answers';

    private const string COLUMNS = 'assessment_speed_column_results';

    private const string DRAWINGS = 'assessment_drawings';

    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function answersOf(int $attemptId): array
    {
        $answers = [];

        foreach ($this->connection->table(self::ANSWERS)->where('attempt_id', $attemptId)->get() as $row) {
            $positions = $row->option_positions === null ? [] : json_decode((string) $row->option_positions, true, flags: JSON_THROW_ON_ERROR);

            $answers[(int) $row->question_id] = new Answer(
                (int) $row->question_id,
                array_values(array_map('intval', $positions)),
                $row->number_value === null ? null : (float) $row->number_value,
                $row->text_value,
                $row->score === null ? null : (float) $row->score,
            );
        }

        return $answers;
    }

    public function saveAnswer(int $attemptId, Answer $answer): void
    {
        $values = [
            'option_positions' => $answer->optionPositions === [] ? null : json_encode($answer->optionPositions, JSON_THROW_ON_ERROR),
            'number_value' => $answer->numberValue,
            'text_value' => $answer->textValue,
            'score' => null,
            'answered_at' => $this->clock->now(),
        ];

        $this->connection->table(self::ANSWERS)->upsert(
            [['attempt_id' => $attemptId, 'question_id' => $answer->questionId] + $values],
            ['attempt_id', 'question_id'],
            array_keys($values),
        );
    }

    public function saveVideoAnswer(int $attemptId, int $questionId, int $videoTakeId): void
    {
        $values = ['option_positions' => null, 'number_value' => null, 'text_value' => null, 'score' => null, 'video_take_id' => $videoTakeId, 'answered_at' => $this->clock->now()];

        $this->connection->table(self::ANSWERS)->upsert(
            [['attempt_id' => $attemptId, 'question_id' => $questionId] + $values],
            ['attempt_id', 'question_id'],
            array_keys($values),
        );
    }

    public function deleteAnswer(int $attemptId, int $questionId): void
    {
        $this->connection->table(self::ANSWERS)->where('attempt_id', $attemptId)->where('question_id', $questionId)->delete();
    }

    public function saveScores(int $attemptId, array $scores): void
    {
        foreach ($scores as $questionId => $score) {
            $this->connection->table(self::ANSWERS)->where('attempt_id', $attemptId)->where('question_id', $questionId)->update(['score' => $score]);
        }
    }

    public function saveAssessorScore(int $attemptId, int $questionId, float $score, int $scoredByUserId): void
    {
        $this->connection->table(self::ANSWERS)->where('attempt_id', $attemptId)->where('question_id', $questionId)->update(['score' => $score, 'scored_by_user_id' => $scoredByUserId]);
    }

    public function saveColumn(int $attemptId, SpeedColumnResult $result): void
    {
        $this->connection->table(self::COLUMNS)->insert([
            'attempt_id' => $attemptId,
            'section_id' => $result->sectionId,
            'column_no' => $result->columnNo,
            'attempted' => $result->attempted,
            'correct' => $result->correct,
        ]);
    }

    public function columnsOf(int $attemptId): array
    {
        return array_values(array_map(static fn (stdClass $row): SpeedColumnResult => new SpeedColumnResult((int) $row->section_id, (int) $row->column_no, (int) $row->attempted, (int) $row->correct), $this->connection->table(self::COLUMNS)
            ->where('attempt_id', $attemptId)->orderBy('section_id')->orderBy('column_no')->get()->all()));
    }

    public function saveDrawing(int $attemptId, Drawing $drawing): ?string
    {
        $previous = $this->connection->table(self::DRAWINGS)->where('attempt_id', $attemptId)->where('question_id', $drawing->questionId)->value('path');
        $now = $this->clock->now();

        $values = ['path' => $drawing->path, 'source' => $drawing->source->value, 'mime_type' => $drawing->mimeType, 'size_bytes' => $drawing->sizeBytes, 'updated_at' => $now];

        $this->connection->table(self::DRAWINGS)->upsert(
            [['attempt_id' => $attemptId, 'question_id' => $drawing->questionId, 'created_at' => $now] + $values],
            ['attempt_id', 'question_id'],
            array_keys($values),
        );

        return $previous === null ? null : (string) $previous;
    }

    public function drawingsOf(int $attemptId): array
    {
        $drawings = [];

        foreach ($this->connection->table(self::DRAWINGS)->where('attempt_id', $attemptId)->get() as $row) {
            $drawings[(int) $row->question_id] = new Drawing(
                (int) $row->question_id, (string) $row->path, DrawingSource::from((string) $row->source), (string) $row->mime_type, (int) $row->size_bytes,
                $row->score === null ? null : (float) $row->score, $row->note,
            );
        }

        return $drawings;
    }

    public function scoreDrawing(int $attemptId, int $questionId, ?float $score, ?string $note): void
    {
        $this->connection->table(self::DRAWINGS)->where('attempt_id', $attemptId)->where('question_id', $questionId)->update(['score' => $score, 'note' => $note]);
    }
}
