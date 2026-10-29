<?php

namespace Src\DemoData\Application;

use LogicException;
use Src\Recruitment\Application\CreateScreeningQuestion;
use Src\Recruitment\Application\ReplaceJobScreeningQuestions;
use Src\Recruitment\Domain\QuestionType;
use Src\Recruitment\Domain\ScreeningQuestion;

/**
 * The screening questions of the verified company (`DemoScreeningContent`): the company admin fills the library, then the recruiter, who wrote the jobs,
 * sets the questions of each job. It runs after the jobs and before the applications, because a job's questions are locked once it has applications.
 */
final readonly class DemoScreening
{
    public function __construct(
        private CreateScreeningQuestion $createQuestion,
        private ReplaceJobScreeningQuestions $replaceJobQuestions,
        private DemoActors $actors,
    ) {}

    /**
     * @param  array<string, array{company_id: int, admin_id: int, members: array<string, int>}>  $teams
     * @param  array<string, int>  $jobIds
     */
    public function build(array $teams, array $jobIds): DemoScreeningAnswers
    {
        $team = $teams[DemoCast::SINAR];
        $admin = $this->actors->member($team['admin_id']);
        $recruiter = $this->actors->member($team['members']['recruiter']);
        $library = [];
        $keys = [];

        foreach (DemoScreeningContent::questions() as $key => $question) {
            $created = $this->createQuestion->execute($admin, $question['type'], $question['text'], $question['labels']);
            $library[$key] = $created;
            $keys[$created->id] = $key;
        }

        $jobQuestions = [];

        foreach (DemoScreeningContent::jobs() as $job => $items) {
            $this->replaceJobQuestions->execute($recruiter, $jobIds[$job], array_map(
                fn (array $item): array => [
                    'question_id' => $library[$item[0]]->id,
                    'required' => $item[1],
                    'must_have' => $item[2],
                    'ideal' => $item[2] ? $this->ideal($item[0], $library[$item[0]]) : null,
                ],
                $items,
            ));

            $jobQuestions[$job] = array_map(static fn (array $item): ScreeningQuestion => $library[$item[0]], $items);
        }

        return new DemoScreeningAnswers($jobQuestions, $keys);
    }

    /** @return array<string, mixed> */
    private function ideal(string $key, ScreeningQuestion $question): array
    {
        $ideal = DemoScreeningContent::ideals()[$key] ?? throw new LogicException("The must-have question {$key} has no ideal answer.");

        return match ($question->type) {
            QuestionType::YesNo => ['yes' => $ideal['yes']],
            QuestionType::Number => ['min' => $ideal['min']],
            default => ['option_ids' => $this->optionIds($question, $ideal['labels'])],
        };
    }

    /**
     * @param  list<string>  $labels
     * @return list<int>
     */
    private function optionIds(ScreeningQuestion $question, array $labels): array
    {
        $ids = [];

        foreach ($question->options as $option) {
            if (in_array($option->label, $labels, true)) {
                $ids[] = $option->id;
            }
        }

        return $ids;
    }
}
