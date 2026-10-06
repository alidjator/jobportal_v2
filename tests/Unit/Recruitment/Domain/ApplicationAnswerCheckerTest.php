<?php

namespace Tests\Unit\Recruitment\Domain;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Src\Recruitment\Domain\ApplicationAnswerChecker;
use Src\Recruitment\Domain\IdealAnswer;
use Src\Recruitment\Domain\JobQuestion;
use Src\Recruitment\Domain\QuestionOption;
use Src\Recruitment\Domain\QuestionType;
use Src\Recruitment\Domain\ScreeningQuestion;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

final class ApplicationAnswerCheckerTest extends TestCase
{
    /** @param  list<int>  $optionIds */
    private function question(int $id, QuestionType $type, bool $required = false, ?IdealAnswer $ideal = null, array $optionIds = []): JobQuestion
    {
        $now = new DateTimeImmutable('2026-10-06');
        $options = array_map(static fn (int $optionId): QuestionOption => new QuestionOption($optionId, 'Option '.$optionId, 1), $optionIds);

        return new JobQuestion(new ScreeningQuestion($id, 1, $type, 'A question', $options, null, 1, $now, $now), 1, $required, $ideal !== null, $ideal);
    }

    /** @return array<string, list<string>> */
    private function errorsOf(array $questions, array $given): array
    {
        try {
            ApplicationAnswerChecker::check($questions, $given);
            $this->fail('The answers were accepted.');
        } catch (BusinessRuleViolation $e) {
            $this->assertSame('invalid-answers', $e->errorCode());

            return $e->extensions()['errors'];
        }
    }

    public function test_a_job_without_questions_accepts_no_answers_and_has_no_must_have_result(): void
    {
        $checked = ApplicationAnswerChecker::check([], []);

        $this->assertSame([], $checked->answers);
        $this->assertNull($checked->meetsMustHave);
    }

    public function test_a_false_yes_no_answer_is_an_answer_not_a_missing_one(): void
    {
        $checked = ApplicationAnswerChecker::check([$this->question(1, QuestionType::YesNo, required: true)], [1 => false]);

        $this->assertFalse($checked->answers[0]->bool);
    }

    public function test_a_multiple_choice_answer_must_be_a_list_of_the_questions_own_options(): void
    {
        $question = $this->question(1, QuestionType::MultipleChoice, optionIds: [10, 11, 12]);

        $this->assertSame([10, 12], ApplicationAnswerChecker::check([$question], [1 => [10, 12, 10]])->answers[0]->optionIds);
        $this->assertArrayHasKey('answers.1', $this->errorsOf([$question], [1 => [10, 99]]));
        $this->assertArrayHasKey('answers.1', $this->errorsOf([$question], [1 => 10]));
        $this->assertArrayHasKey('answers.1', $this->errorsOf([$question], [1 => ['10']]));
    }

    public function test_numbers_must_be_whole_and_in_range(): void
    {
        $question = $this->question(1, QuestionType::Number);

        $this->assertSame(0, ApplicationAnswerChecker::check([$question], [1 => 0])->answers[0]->number);

        foreach ([-1, 1.5, '3', 1_000_000_000] as $value) {
            $this->assertArrayHasKey('answers.1', $this->errorsOf([$question], [1 => $value]));
        }
    }

    public function test_every_error_is_reported_together(): void
    {
        $errors = $this->errorsOf(
            [$this->question(1, QuestionType::Text, required: true), $this->question(2, QuestionType::YesNo)],
            [2 => 'maybe', 3 => true],
        );

        $this->assertSame(['answers.1', 'answers.2', 'answers.3'], array_keys($errors));
    }

    public function test_the_must_have_result_is_met_only_when_every_must_have_is_met(): void
    {
        $yes = IdealAnswer::from(QuestionType::YesNo, ['yes' => true], []);
        $years = IdealAnswer::from(QuestionType::Number, ['min' => 3], []);
        $questions = [
            $this->question(1, QuestionType::YesNo, required: true, ideal: $yes),
            $this->question(2, QuestionType::Number, required: true, ideal: $years),
        ];

        $this->assertTrue(ApplicationAnswerChecker::check($questions, [1 => true, 2 => 3])->meetsMustHave);
        $this->assertFalse(ApplicationAnswerChecker::check($questions, [1 => true, 2 => 2])->meetsMustHave);
        $this->assertFalse(ApplicationAnswerChecker::check($questions, [1 => false, 2 => 9])->meetsMustHave);
    }
}
