<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\QuestionOption;
use Src\Recruitment\Domain\QuestionType;
use Src\Recruitment\Domain\ScreeningQuestion;
use Src\Recruitment\Domain\ScreeningQuestionRepository;
use stdClass;

final readonly class DatabaseScreeningQuestionRepository implements ScreeningQuestionRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection) {}

    public function find(int $id): ?ScreeningQuestion
    {
        return $this->findMany([$id])[$id] ?? null;
    }

    public function findMany(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $options = $this->optionsOf($ids);
        $questions = [];

        foreach ($this->connection->table('recruitment_screening_questions')->whereIn('id', $ids)->get() as $row) {
            $questions[(int) $row->id] = self::hydrate($row, $options[(int) $row->id] ?? []);
        }

        return $questions;
    }

    public function lock(int $id): void
    {
        $this->connection->table('recruitment_screening_questions')->where('id', $id)->lockForUpdate()->first(['id']);
    }

    public function create(int $companyId, QuestionType $type, string $text, array $labels, int $createdByUserId, DateTimeImmutable $at): int
    {
        $time = $at->format(self::TIME_FORMAT);

        $id = (int) $this->connection->table('recruitment_screening_questions')->insertGetId([
            'company_id' => $companyId, 'type' => $type->value, 'text' => $text, 'created_by_user_id' => $createdByUserId,
            'created_at' => $time, 'updated_at' => $time,
        ]);

        if ($labels !== []) {
            $this->connection->table('recruitment_screening_question_options')->insert(array_map(
                static fn (string $label, int $index): array => ['question_id' => $id, 'label' => $label, 'position' => $index + 1],
                $labels, array_keys($labels),
            ));
        }

        return $id;
    }

    public function updateText(int $id, string $text, DateTimeImmutable $at): void
    {
        $this->connection->table('recruitment_screening_questions')->where('id', $id)->update(['text' => $text, 'updated_at' => $at->format(self::TIME_FORMAT)]);
    }

    public function replaceOptions(int $id, array $options, DateTimeImmutable $at): void
    {
        $keep = array_values(array_filter(array_map(static fn (array $option): ?int => $option['id'], $options)));

        $this->connection->table('recruitment_screening_question_options')->where('question_id', $id)->whereNotIn('id', $keep)->delete();

        // Two steps, so labels can be swapped between two choices without meeting the unique key half way.
        foreach ($keep as $optionId) {
            $this->connection->table('recruitment_screening_question_options')->where('id', $optionId)->update(['label' => '~'.$optionId]);
        }

        foreach ($options as $index => $option) {
            if ($option['id'] === null) {
                $this->connection->table('recruitment_screening_question_options')->insert(['question_id' => $id, 'label' => $option['label'], 'position' => $index + 1]);
            } else {
                $this->connection->table('recruitment_screening_question_options')->where('id', $option['id'])->update(['label' => $option['label'], 'position' => $index + 1]);
            }
        }

        $this->connection->table('recruitment_screening_questions')->where('id', $id)->update(['updated_at' => $at->format(self::TIME_FORMAT)]);
    }

    public function setArchived(int $id, ?DateTimeImmutable $archivedAt, DateTimeImmutable $at): void
    {
        $this->connection->table('recruitment_screening_questions')->where('id', $id)->update([
            'archived_at' => $archivedAt?->format(self::TIME_FORMAT), 'updated_at' => $at->format(self::TIME_FORMAT),
        ]);
    }

    public function delete(int $id): void
    {
        $this->connection->table('recruitment_screening_questions')->where('id', $id)->delete();
    }

    public function isAttached(int $id): bool
    {
        return $this->connection->table('recruitment_job_questions')->where('question_id', $id)->exists()
            || $this->connection->table('recruitment_screening_preset_questions')->where('question_id', $id)->exists();
    }

    public function idealOptionIds(int $id): array
    {
        $options = [];

        foreach (['recruitment_job_question_ideal_options', 'recruitment_screening_preset_ideal_options'] as $table) {
            $options = [...$options, ...$this->connection->table($table)
                ->join('recruitment_screening_question_options', 'recruitment_screening_question_options.id', '=', "{$table}.option_id")
                ->where('recruitment_screening_question_options.question_id', $id)
                ->distinct()
                ->pluck("{$table}.option_id")
                ->all()];
        }

        return array_values(array_unique(array_map('intval', $options)));
    }

    /**
     * The choices of these questions in their order, by question id.
     *
     * @param  list<int>  $questionIds
     * @return array<int, list<QuestionOption>>
     */
    private function optionsOf(array $questionIds): array
    {
        $options = [];

        foreach ($this->connection->table('recruitment_screening_question_options')->whereIn('question_id', $questionIds)->orderBy('position')->orderBy('id')->get() as $row) {
            $options[(int) $row->question_id][] = new QuestionOption((int) $row->id, (string) $row->label, (int) $row->position);
        }

        return $options;
    }

    /** @param  list<QuestionOption>  $options */
    private static function hydrate(stdClass $row, array $options): ScreeningQuestion
    {
        $utc = new DateTimeZone('UTC');

        return new ScreeningQuestion(
            (int) $row->id, (int) $row->company_id, QuestionType::from($row->type), (string) $row->text, $options,
            $row->archived_at === null ? null : new DateTimeImmutable($row->archived_at, $utc),
            (int) $row->created_by_user_id, new DateTimeImmutable($row->created_at, $utc), new DateTimeImmutable($row->updated_at, $utc),
        );
    }
}
