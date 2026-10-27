<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\IdealAnswer;
use Src\Recruitment\Domain\JobQuestion;
use Src\Recruitment\Domain\JobQuestionDraft;
use Src\Recruitment\Domain\QuestionType;
use Src\Recruitment\Domain\ScreeningPreset;
use Src\Recruitment\Domain\ScreeningPresetRepository;
use Src\Recruitment\Domain\ScreeningQuestionRepository;

final readonly class DatabaseScreeningPresetRepository implements ScreeningPresetRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection, private ScreeningQuestionRepository $questions) {}

    public function find(int $id): ?ScreeningPreset
    {
        return $this->findMany([$id])[$id] ?? null;
    }

    public function findMany(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = $this->connection->table('recruitment_screening_presets')->whereIn('id', $ids)->get();
        $questions = $this->questionsOf(array_map('intval', $rows->pluck('id')->all()));
        $result = [];

        foreach ($rows as $row) {
            $result[(int) $row->id] = new ScreeningPreset(
                (int) $row->id,
                (int) $row->company_id,
                $row->name,
                $row->job_level_id === null ? null : (int) $row->job_level_id,
                $questions[(int) $row->id] ?? [],
                (int) $row->created_by_user_id,
                new DateTimeImmutable($row->created_at),
                new DateTimeImmutable($row->updated_at),
            );
        }

        return $result;
    }

    public function nameTaken(int $companyId, string $name, ?int $exceptId = null): bool
    {
        return $this->connection->table('recruitment_screening_presets')
            ->where('company_id', $companyId)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->when($exceptId !== null, static fn ($query) => $query->where('id', '!=', $exceptId))
            ->exists();
    }

    public function create(int $companyId, string $name, ?int $jobLevelId, int $createdByUserId, array $drafts, DateTimeImmutable $at): int
    {
        $time = $at->format(self::TIME_FORMAT);

        $id = (int) $this->connection->table('recruitment_screening_presets')->insertGetId([
            'company_id' => $companyId,
            'name' => $name,
            'job_level_id' => $jobLevelId,
            'created_by_user_id' => $createdByUserId,
            'created_at' => $time,
            'updated_at' => $time,
        ]);

        $this->insertQuestions($id, $drafts, $time);

        return $id;
    }

    public function update(int $id, string $name, ?int $jobLevelId, array $drafts, DateTimeImmutable $at): void
    {
        $time = $at->format(self::TIME_FORMAT);

        $this->connection->table('recruitment_screening_presets')->where('id', $id)->update(['name' => $name, 'job_level_id' => $jobLevelId, 'updated_at' => $time]);

        // Nothing points at a preset question, so the list is rewritten; the ideal options go with their rows (cascade).
        $this->connection->table('recruitment_screening_preset_questions')->where('preset_id', $id)->delete();
        $this->insertQuestions($id, $drafts, $time);
    }

    public function delete(int $id): void
    {
        $this->connection->table('recruitment_screening_presets')->where('id', $id)->delete();
    }

    /** @param  list<JobQuestionDraft>  $drafts */
    private function insertQuestions(int $presetId, array $drafts, string $time): void
    {
        foreach ($drafts as $draft) {
            $rowId = (int) $this->connection->table('recruitment_screening_preset_questions')->insertGetId([
                'preset_id' => $presetId,
                'question_id' => $draft->questionId,
                'position' => $draft->position,
                'is_required' => $draft->isRequired,
                'is_must_have' => $draft->isMustHave,
                'ideal_yes' => $draft->ideal?->yes,
                'ideal_min' => $draft->ideal?->min,
                'ideal_max' => $draft->ideal?->max,
                'created_at' => $time,
                'updated_at' => $time,
            ]);

            if ($draft->ideal !== null && $draft->ideal->optionIds !== []) {
                $this->connection->table('recruitment_screening_preset_ideal_options')->insert(array_map(
                    static fn (int $optionId): array => ['preset_question_id' => $rowId, 'option_id' => $optionId],
                    $draft->ideal->optionIds,
                ));
            }
        }
    }

    /**
     * @param  list<int>  $presetIds
     * @return array<int, list<JobQuestion>> the questions in their order, by preset id
     */
    private function questionsOf(array $presetIds): array
    {
        $rows = $this->connection->table('recruitment_screening_preset_questions')->whereIn('preset_id', $presetIds)->orderBy('position')->orderBy('id')->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $library = $this->questions->findMany(array_map('intval', $rows->pluck('question_id')->all()));
        $ideals = $this->idealOptionIds(array_map('intval', $rows->pluck('id')->all()));
        $result = [];

        foreach ($rows as $row) {
            $question = $library[(int) $row->question_id] ?? null;

            if ($question === null) {
                continue;
            }

            $result[(int) $row->preset_id][] = new JobQuestion(
                $question,
                (int) $row->position,
                (bool) $row->is_required,
                (bool) $row->is_must_have,
                $row->is_must_have ? self::ideal($question->type, $row, $ideals[(int) $row->id] ?? []) : null,
            );
        }

        return $result;
    }

    /**
     * @param  list<int>  $presetQuestionIds
     * @return array<int, list<int>> the ideal option ids by preset question id
     */
    private function idealOptionIds(array $presetQuestionIds): array
    {
        $ids = [];

        foreach ($this->connection->table('recruitment_screening_preset_ideal_options')->whereIn('preset_question_id', $presetQuestionIds)->orderBy('id')->get() as $row) {
            $ids[(int) $row->preset_question_id][] = (int) $row->option_id;
        }

        return $ids;
    }

    /** @param  list<int>  $optionIds */
    private static function ideal(QuestionType $type, object $row, array $optionIds): IdealAnswer
    {
        return IdealAnswer::restore(
            $type,
            $row->ideal_yes === null ? null : (bool) $row->ideal_yes,
            $optionIds,
            $row->ideal_min === null ? null : (int) $row->ideal_min,
            $row->ideal_max === null ? null : (int) $row->ideal_max,
        );
    }
}
