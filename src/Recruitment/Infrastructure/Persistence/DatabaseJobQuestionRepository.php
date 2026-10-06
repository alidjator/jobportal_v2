<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Domain\IdealAnswer;
use Src\Recruitment\Domain\JobQuestion;
use Src\Recruitment\Domain\JobQuestionDraft;
use Src\Recruitment\Domain\JobQuestionRepository;
use Src\Recruitment\Domain\QuestionType;
use Src\Recruitment\Domain\ScreeningQuestionRepository;

final readonly class DatabaseJobQuestionRepository implements JobQuestionRepository
{
    private const string TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private ConnectionInterface $connection, private ScreeningQuestionRepository $questions) {}

    public function forJob(int $jobId): array
    {
        $rows = $this->connection->table('recruitment_job_questions')->where('job_id', $jobId)->orderBy('position')->orderBy('id')->get();

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

            $result[] = new JobQuestion(
                $question,
                (int) $row->position,
                (bool) $row->is_required,
                (bool) $row->is_must_have,
                $row->is_must_have ? self::ideal($question->type, $row, $ideals[(int) $row->id] ?? []) : null,
            );
        }

        return $result;
    }

    public function replaceForJob(int $jobId, array $drafts, DateTimeImmutable $at): void
    {
        $time = $at->format(self::TIME_FORMAT);
        $existing = $this->connection->table('recruitment_job_questions')->where('job_id', $jobId)->pluck('id', 'question_id')->all();
        $wanted = array_map(static fn (JobQuestionDraft $draft): int => $draft->questionId, $drafts);

        $removed = array_values(array_diff(array_keys($existing), $wanted));

        if ($removed !== []) {
            $this->connection->table('recruitment_job_questions')->where('job_id', $jobId)->whereIn('question_id', $removed)->delete();
        }

        foreach ($drafts as $draft) {
            $columns = [
                'position' => $draft->position,
                'is_required' => $draft->isRequired,
                'is_must_have' => $draft->isMustHave,
                'ideal_yes' => $draft->ideal?->yes,
                'ideal_min' => $draft->ideal?->min,
                'ideal_max' => $draft->ideal?->max,
                'updated_at' => $time,
            ];

            if (isset($existing[$draft->questionId])) {
                $rowId = (int) $existing[$draft->questionId];
                $this->connection->table('recruitment_job_questions')->where('id', $rowId)->update($columns);
                $this->connection->table('recruitment_job_question_ideal_options')->where('job_question_id', $rowId)->delete();
            } else {
                $rowId = (int) $this->connection->table('recruitment_job_questions')->insertGetId(
                    ['job_id' => $jobId, 'question_id' => $draft->questionId, 'created_at' => $time, ...$columns],
                );
            }

            if ($draft->ideal !== null && $draft->ideal->optionIds !== []) {
                $this->connection->table('recruitment_job_question_ideal_options')->insert(array_map(
                    static fn (int $optionId): array => ['job_question_id' => $rowId, 'option_id' => $optionId],
                    $draft->ideal->optionIds,
                ));
            }
        }
    }

    /**
     * @param  list<int>  $jobQuestionIds
     * @return array<int, list<int>> the ideal option ids by attachment id
     */
    private function idealOptionIds(array $jobQuestionIds): array
    {
        $ids = [];

        foreach ($this->connection->table('recruitment_job_question_ideal_options')->whereIn('job_question_id', $jobQuestionIds)->orderBy('id')->get() as $row) {
            $ids[(int) $row->job_question_id][] = (int) $row->option_id;
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
