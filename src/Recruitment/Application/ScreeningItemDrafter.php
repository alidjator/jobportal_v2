<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\IdealAnswer;
use Src\Recruitment\Domain\JobQuestionDraft;
use Src\Recruitment\Domain\ScreeningQuestion;
use Src\Recruitment\Domain\ScreeningQuestionRepository;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * Checks an ordered list of library questions with their flags and ideal answers and turns it into drafts. The one place of these rules: a job's list and a
 * preset's list cannot drift apart. A question must be an active one of the company's library, except one the list already holds (it keeps an archived one).
 */
final readonly class ScreeningItemDrafter
{
    public function __construct(private ScreeningQuestionRepository $library) {}

    /**
     * @param  list<array{question_id: int, required: bool, must_have: bool, ideal: array<string, mixed>|null}>  $items
     * @param  list<int>  $currentIds  the questions the list holds now
     * @return list<JobQuestionDraft>
     *
     * @throws BusinessRuleViolation `$code` with `errors` per `questions.<n>.<field>`
     */
    public function drafts(int $companyId, array $items, array $currentIds, string $code, string $message): array
    {
        $library = $this->library->findMany(array_map(static fn (array $item): int => $item['question_id'], $items));
        $errors = [];
        $drafts = [];

        foreach ($items as $index => $item) {
            $question = $library[$item['question_id']] ?? null;

            if ($question === null || $question->companyId !== $companyId || ($question->isArchived() && ! in_array($question->id, $currentIds, true))) {
                $errors["questions.{$index}.question_id"][] = 'This is not an active question of your library.';

                continue;
            }

            try {
                $drafts[] = $this->draft($question, $index + 1, $item);
            } catch (BusinessRuleViolation $violation) {
                foreach ($violation->extensions()['errors'] ?? [] as $field => $messages) {
                    $errors["questions.{$index}.{$field}"] = $messages;
                }
            }
        }

        if ($errors !== []) {
            throw new BusinessRuleViolation($message, $code, extensions: ['errors' => $errors]);
        }

        return $drafts;
    }

    /**
     * @param  array{question_id: int, required: bool, must_have: bool, ideal: array<string, mixed>|null}  $item
     *
     * @throws BusinessRuleViolation with `errors` on `must_have` or `ideal`
     */
    private function draft(ScreeningQuestion $question, int $position, array $item): JobQuestionDraft
    {
        if (! $item['must_have']) {
            if ($item['ideal'] !== null) {
                throw new BusinessRuleViolation('Only a must-have question has an ideal answer.', 'invalid-job-questions', extensions: ['errors' => ['ideal' => ['Only a must-have question has an ideal answer.']]]);
            }

            return new JobQuestionDraft($question->id, $position, $item['required'], false, null);
        }

        if (! $question->type->supportsMustHave()) {
            throw new BusinessRuleViolation('A text question cannot be marked must-have.', 'invalid-job-questions', extensions: ['errors' => ['must_have' => ['A text question cannot be marked must-have.']]]);
        }

        if ($item['ideal'] === null) {
            throw new BusinessRuleViolation('A must-have question needs an ideal answer.', 'invalid-job-questions', extensions: ['errors' => ['ideal' => ['A must-have question needs an ideal answer.']]]);
        }

        // A must-have question is always required, so there is an answer to check.
        return new JobQuestionDraft($question->id, $position, true, true, IdealAnswer::from($question->type, $item['ideal'], $question->optionIds()));
    }
}
