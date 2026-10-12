<?php

namespace Src\Assessment\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Assessment\Domain\Question;
use Src\Assessment\Domain\QuestionOption;
use Src\Assessment\Domain\QuestionRepository;
use Src\Assessment\Domain\QuestionType;
use Src\Shared\Application\Clock;
use stdClass;

final readonly class DatabaseQuestionRepository implements QuestionRepository
{
    private const string TABLE = 'assessment_questions';

    private const string OPTIONS = 'assessment_question_options';

    public function __construct(
        private ConnectionInterface $connection,
        private Clock $clock,
    ) {}

    public function ofSection(int $sectionId): array
    {
        $rows = $this->connection->table(self::TABLE)->where('section_id', $sectionId)->orderBy('position')->orderBy('id')->get()->all();

        return $this->hydrate($rows);
    }

    public function find(int $id): ?Question
    {
        $row = $this->connection->table(self::TABLE)->where('id', $id)->first();

        return $row === null ? null : $this->hydrate([$row])[0];
    }

    public function manyById(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $questions = [];

        foreach ($this->hydrate($this->connection->table(self::TABLE)->whereIn('id', $ids)->get()->all()) as $question) {
            $questions[(int) $question->id] = $question;
        }

        return $questions;
    }

    public function add(Question $question): Question
    {
        $now = $this->clock->now();
        $position = (int) $this->connection->table(self::TABLE)->where('section_id', $question->sectionId)->max('position') + 1;

        $id = (int) $this->connection->table(self::TABLE)->insertGetId($this->values($question) + [
            'section_id' => $question->sectionId,
            'position' => $position,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->insertOptions($id, $question->options);

        return $this->find($id) ?? throw new \LogicException('The new question was not found.');
    }

    public function update(Question $question): void
    {
        $this->connection->table(self::TABLE)->where('id', $question->id)->update($this->values($question) + ['updated_at' => $this->clock->now()]);
        $this->connection->table(self::OPTIONS)->where('question_id', $question->id)->delete();
        $this->insertOptions((int) $question->id, $question->options);
    }

    public function delete(int $id): void
    {
        $question = $this->connection->table(self::TABLE)->where('id', $id)->first();

        if ($question === null) {
            return;
        }

        $this->connection->table(self::TABLE)->where('id', $id)->delete();
        $this->connection->table(self::TABLE)->where('section_id', $question->section_id)->where('position', '>', $question->position)->decrement('position');
    }

    public function reorder(int $sectionId, array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            $this->connection->table(self::TABLE)->where('section_id', $sectionId)->where('id', $id)->update(['position' => $index + 1]);
        }
    }

    public function countInSection(int $sectionId): int
    {
        return $this->connection->table(self::TABLE)->where('section_id', $sectionId)->count();
    }

    public function countsBySection(int $testId): array
    {
        $counts = [];

        $rows = $this->connection->table(self::TABLE)
            ->join('assessment_sections as sections', 'sections.id', '=', self::TABLE.'.section_id')
            ->where('sections.test_id', $testId)
            ->groupBy(self::TABLE.'.section_id')
            ->selectRaw(self::TABLE.'.section_id as section_id, count(*) as total')
            ->get();

        foreach ($rows as $row) {
            $counts[(int) $row->section_id] = (int) $row->total;
        }

        return $counts;
    }

    public function testHasType(int $testId, QuestionType $type): bool
    {
        return $this->connection->table(self::TABLE)
            ->join('assessment_sections as sections', 'sections.id', '=', self::TABLE.'.section_id')
            ->where('sections.test_id', $testId)
            ->where(self::TABLE.'.type', $type->value)
            ->exists();
    }

    public function sampleTaken(int $testId, QuestionType $type, ?int $exceptQuestionId): bool
    {
        $query = $this->connection->table(self::TABLE)
            ->join('assessment_sections as sections', 'sections.id', '=', self::TABLE.'.section_id')
            ->where('sections.test_id', $testId)
            ->where(self::TABLE.'.type', $type->value)
            ->where(self::TABLE.'.is_sample', true);

        if ($exceptQuestionId !== null) {
            $query->where(self::TABLE.'.id', '<>', $exceptQuestionId);
        }

        return $query->exists();
    }

    public function samplesOfTest(int $testId): array
    {
        $rows = $this->connection->table(self::TABLE)
            ->join('assessment_sections as sections', 'sections.id', '=', self::TABLE.'.section_id')
            ->where('sections.test_id', $testId)
            ->where(self::TABLE.'.is_sample', true)
            ->orderBy('sections.position')->orderBy(self::TABLE.'.position')
            ->select(self::TABLE.'.*')
            ->get()->all();

        return $this->hydrate($rows);
    }

    public function scalesOfTest(int $testId): array
    {
        $questionScales = $this->connection->table(self::TABLE)
            ->join('assessment_sections as sections', 'sections.id', '=', self::TABLE.'.section_id')
            ->where('sections.test_id', $testId)->whereNotNull(self::TABLE.'.scale_key')
            ->pluck(self::TABLE.'.scale_key')->all();

        $optionScales = $this->connection->table(self::OPTIONS)
            ->join(self::TABLE.' as questions', 'questions.id', '=', self::OPTIONS.'.question_id')
            ->join('assessment_sections as sections', 'sections.id', '=', 'questions.section_id')
            ->where('sections.test_id', $testId)->whereNotNull(self::OPTIONS.'.scale_key')
            ->pluck(self::OPTIONS.'.scale_key')->all();

        $scales = array_values(array_unique([...$questionScales, ...$optionScales]));
        sort($scales);

        return $scales;
    }

    /** @return array<string, mixed> */
    private function values(Question $question): array
    {
        return [
            'type' => $question->type->value,
            'text' => $question->text,
            'points' => $question->points,
            'scale_key' => $question->scaleKey,
            'is_sample' => $question->isSample,
            'think_seconds' => $question->thinkSeconds,
            'answer_seconds' => $question->answerSeconds,
            'max_retakes' => $question->maxRetakes,
        ];
    }

    /** @param  list<QuestionOption>  $options */
    private function insertOptions(int $questionId, array $options): void
    {
        if ($options === []) {
            return;
        }

        $this->connection->table(self::OPTIONS)->insert(array_map(static fn (QuestionOption $option, int $index): array => [
            'question_id' => $questionId,
            'position' => $index + 1,
            'label' => $option->label,
            'is_correct' => $option->isCorrect,
            'score_value' => $option->scoreValue,
            'scale_key' => $option->scaleKey,
        ], $options, array_keys($options)));
    }

    /**
     * @param  list<stdClass>  $rows
     * @return list<Question>
     */
    private function hydrate(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $options = [];

        $optionRows = $this->connection->table(self::OPTIONS)
            ->whereIn('question_id', array_map(static fn (stdClass $row): int => (int) $row->id, $rows))
            ->orderBy('position')->orderBy('id')->get();

        foreach ($optionRows as $option) {
            $options[(int) $option->question_id][] = new QuestionOption(
                (string) $option->label,
                (bool) $option->is_correct,
                $option->score_value === null ? null : (int) $option->score_value,
                $option->scale_key,
            );
        }

        return array_map(static fn (stdClass $row): Question => new Question(
            (int) $row->id,
            (int) $row->section_id,
            (int) $row->position,
            QuestionType::from($row->type),
            (string) $row->text,
            (int) $row->points,
            $row->scale_key,
            (bool) $row->is_sample,
            $options[(int) $row->id] ?? [],
            $row->think_seconds === null ? null : (int) $row->think_seconds,
            $row->answer_seconds === null ? null : (int) $row->answer_seconds,
            $row->max_retakes === null ? null : (int) $row->max_retakes,
        ), $rows);
    }
}
