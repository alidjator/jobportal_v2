<?php

namespace Src\Recruitment\Domain;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * Checks what an applicant answered against the questions of the job: every required question is answered, nothing else is answered, and each answer
 * fits its question's type (a choice must be one of the question's own options). A must-have question is only marked: the application still arrives.
 */
final class ApplicationAnswerChecker
{
    /**
     * @param  list<JobQuestion>  $questions  the job's questions
     * @param  array<int, mixed>  $given  the raw value of each answered question, by question id
     *
     * @throws BusinessRuleViolation `invalid-answers` with `errors` per `answers.<question id>`
     */
    public static function check(array $questions, array $given): CheckedAnswers
    {
        $errors = [];
        $answers = [];
        $hasMustHave = false;
        $meetsAll = true;
        $known = [];

        foreach ($questions as $jobQuestion) {
            $question = $jobQuestion->question;
            $known[$question->id] = true;
            $key = 'answers.'.$question->id;
            $value = $given[$question->id] ?? null;

            $hasMustHave = $hasMustHave || ($jobQuestion->isMustHave && $jobQuestion->ideal !== null);

            if ($value === null || $value === '' || $value === []) {
                if ($jobQuestion->isRequired) {
                    $errors[$key] = ['This question needs an answer.'];
                    $meetsAll = false;
                }

                continue;
            }

            $answer = self::answer($question, $value);

            if ($answer === null) {
                $errors[$key] = ['The answer does not fit the question.'];

                continue;
            }

            $answers[] = $answer['answer'];

            if ($jobQuestion->isMustHave && $jobQuestion->ideal !== null && ! $jobQuestion->ideal->isMetBy($answer['comparable'])) {
                $meetsAll = false;
            }
        }

        foreach (array_keys($given) as $questionId) {
            if (! isset($known[$questionId])) {
                $errors['answers.'.$questionId] = ['This question does not belong to the job.'];
            }
        }

        if ($errors !== []) {
            throw new BusinessRuleViolation('Some answers are missing or invalid.', 'invalid-answers', extensions: ['errors' => $errors]);
        }

        return new CheckedAnswers($answers, $hasMustHave ? $meetsAll : null);
    }

    /** @return array{answer: ApplicationAnswer, comparable: bool|int|array<int, int>}|null null when the value does not fit the question */
    private static function answer(ScreeningQuestion $question, mixed $value): ?array
    {
        switch ($question->type) {
            case QuestionType::Text:
                if (! is_string($value)) {
                    return null;
                }

                $text = trim($value);

                return $text === '' || mb_strlen($text) > QuestionRules::TEXT_ANSWER_MAX ? null : ['answer' => new ApplicationAnswer($question->id, text: $text), 'comparable' => false];

            case QuestionType::YesNo:
                return is_bool($value) ? ['answer' => new ApplicationAnswer($question->id, bool: $value), 'comparable' => $value] : null;

            case QuestionType::Number:
                return is_int($value) && $value >= 0 && $value <= QuestionRules::NUMBER_MAX ? ['answer' => new ApplicationAnswer($question->id, number: $value), 'comparable' => $value] : null;

            case QuestionType::SingleChoice:
                return is_int($value) && in_array($value, $question->optionIds(), true)
                    ? ['answer' => new ApplicationAnswer($question->id, optionIds: [$value]), 'comparable' => $value]
                    : null;

            case QuestionType::MultipleChoice:
                if (! is_array($value) || ! array_is_list($value) || array_filter($value, static fn (mixed $id): bool => ! is_int($id)) !== []) {
                    return null;
                }

                $ids = array_values(array_unique($value));

                return array_diff($ids, $question->optionIds()) === [] ? ['answer' => new ApplicationAnswer($question->id, optionIds: $ids), 'comparable' => $ids] : null;
        }
    }
}
