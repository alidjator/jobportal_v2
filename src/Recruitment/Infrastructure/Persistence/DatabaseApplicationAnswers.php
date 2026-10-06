<?php

namespace Src\Recruitment\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Src\Recruitment\Application\ApplicationAnswerView;
use Src\Recruitment\Domain\QuestionType;
use stdClass;

/** The answers of one application with their questions and chosen options, in the order of the job's questions. Shared by the applicant's and the recruiter's readers. */
final readonly class DatabaseApplicationAnswers
{
    public function __construct(private ConnectionInterface $connection) {}

    /** @return list<ApplicationAnswerView> */
    public function forApplication(int $applicationId, int $jobId): array
    {
        $answers = $this->connection->table('recruitment_application_answers as a')
            ->join('recruitment_screening_questions as q', 'q.id', '=', 'a.question_id')
            ->leftJoin('recruitment_job_questions as jq', fn ($join) => $join->on('jq.question_id', '=', 'a.question_id')->where('jq.job_id', '=', $jobId))
            ->where('a.application_id', $applicationId)
            ->orderBy('jq.position')
            ->orderBy('a.id')
            ->get(['a.id as answer_id', 'a.question_id', 'q.text as question_text', 'q.type as question_type', 'a.answer_text', 'a.answer_number', 'a.answer_bool'])
            ->all();

        $options = [];

        if ($answers !== []) {
            foreach ($this->connection->table('recruitment_application_answer_options as ao')
                ->join('recruitment_screening_question_options as o', 'o.id', '=', 'ao.option_id')
                ->whereIn('ao.answer_id', array_map(static fn (stdClass $answer): int => (int) $answer->answer_id, $answers))
                ->orderBy('o.position')
                ->get(['ao.answer_id', 'o.id', 'o.label']) as $option) {
                $options[(int) $option->answer_id][] = ['id' => (int) $option->id, 'label' => (string) $option->label];
            }
        }

        return array_map(static fn (stdClass $answer): ApplicationAnswerView => new ApplicationAnswerView(
            (int) $answer->question_id,
            (string) $answer->question_text,
            QuestionType::from($answer->question_type),
            $answer->answer_text,
            $answer->answer_number === null ? null : (int) $answer->answer_number,
            $answer->answer_bool === null ? null : (bool) $answer->answer_bool,
            $options[(int) $answer->answer_id] ?? [],
        ), $answers);
    }
}
