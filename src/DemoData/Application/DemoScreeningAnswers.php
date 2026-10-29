<?php

namespace Src\DemoData\Application;

use Src\Recruitment\Domain\QuestionType;
use Src\Recruitment\Domain\ScreeningQuestion;

/** The screening questions the run set on each job, and the answers a demo job seeker gives to them when applying. */
final readonly class DemoScreeningAnswers
{
    /**
     * @param  array<string, list<ScreeningQuestion>>  $jobQuestions  the library questions of each job by job key, in the job's order
     * @param  array<int, string>  $keys  the key of each library question by its id
     */
    public function __construct(private array $jobQuestions, private array $keys) {}

    /**
     * The raw answers by question id, as the application endpoint takes them; a job without questions gets none.
     *
     * @return array<int, mixed>
     */
    public function forApplication(string $jobKey, int $seeker): array
    {
        $given = DemoScreeningContent::answers($seeker);
        $answers = [];

        foreach ($this->jobQuestions[$jobKey] ?? [] as $question) {
            $value = $given[$this->keys[$question->id]];

            $answers[$question->id] = match ($question->type) {
                QuestionType::SingleChoice => $this->optionId($question, $value),
                QuestionType::MultipleChoice => array_map(fn (string $label): int => $this->optionId($question, $label), $value),
                default => $value,
            };
        }

        return $answers;
    }

    private function optionId(ScreeningQuestion $question, string $label): int
    {
        foreach ($question->options as $option) {
            if ($option->label === $label) {
                return $option->id;
            }
        }

        throw new \LogicException("The question has no option {$label}.");
    }
}
