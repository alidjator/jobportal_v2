<?php

namespace Tests\Unit\Recruitment\Domain;

use PHPUnit\Framework\TestCase;
use Src\Recruitment\Domain\IdealAnswer;
use Src\Recruitment\Domain\QuestionType;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

final class IdealAnswerTest extends TestCase
{
    private function refused(QuestionType $type, array $input, array $options = [1, 2, 3]): void
    {
        try {
            IdealAnswer::from($type, $input, $options);
            $this->fail('The ideal answer was accepted: '.json_encode($input));
        } catch (BusinessRuleViolation $e) {
            $this->assertSame('invalid-ideal-answer', $e->errorCode());
            $this->assertArrayHasKey('ideal', $e->extensions()['errors']);
        }
    }

    public function test_a_yes_no_answer_is_met_only_by_the_same_answer(): void
    {
        $ideal = IdealAnswer::from(QuestionType::YesNo, ['yes' => true], []);

        $this->assertTrue($ideal->isMetBy(true));
        $this->assertFalse($ideal->isMetBy(false));
        $this->assertTrue(IdealAnswer::from(QuestionType::YesNo, ['yes' => false], [])->isMetBy(false));
    }

    public function test_a_yes_no_ideal_must_be_a_real_boolean(): void
    {
        foreach ([[], ['yes' => 'true'], ['yes' => 1], ['yes' => null]] as $input) {
            $this->refused(QuestionType::YesNo, $input);
        }
    }

    public function test_a_single_choice_answer_must_be_one_of_the_accepted_options(): void
    {
        $ideal = IdealAnswer::from(QuestionType::SingleChoice, ['option_ids' => [1, 3, 1]], [1, 2, 3]);

        $this->assertSame([1, 3], $ideal->optionIds);
        $this->assertTrue($ideal->isMetBy(1));
        $this->assertTrue($ideal->isMetBy(3));
        $this->assertFalse($ideal->isMetBy(2));
        $this->assertFalse($ideal->isMetBy([1]));
    }

    public function test_a_multiple_choice_answer_must_hold_every_ideal_option_and_may_hold_more(): void
    {
        $ideal = IdealAnswer::from(QuestionType::MultipleChoice, ['option_ids' => [1, 2]], [1, 2, 3]);

        $this->assertTrue($ideal->isMetBy([1, 2]));
        $this->assertTrue($ideal->isMetBy([3, 2, 1]));
        $this->assertFalse($ideal->isMetBy([1]));
        $this->assertFalse($ideal->isMetBy([]));
        $this->assertFalse($ideal->isMetBy(1));
    }

    public function test_the_ideal_options_must_be_a_non_empty_list_of_the_questions_own_options(): void
    {
        foreach ([[], ['option_ids' => []], ['option_ids' => 'a'], ['option_ids' => ['1']], ['option_ids' => [9]], ['option_ids' => [1, 9]]] as $input) {
            $this->refused(QuestionType::SingleChoice, $input);
            $this->refused(QuestionType::MultipleChoice, $input);
        }
    }

    public function test_a_number_answer_is_checked_against_the_minimum_and_the_maximum(): void
    {
        $both = IdealAnswer::from(QuestionType::Number, ['min' => 2, 'max' => 5], []);

        $this->assertTrue($both->isMetBy(2));
        $this->assertTrue($both->isMetBy(5));
        $this->assertFalse($both->isMetBy(1));
        $this->assertFalse($both->isMetBy(6));
        $this->assertTrue(IdealAnswer::from(QuestionType::Number, ['min' => 3], [])->isMetBy(1000));
        $this->assertFalse(IdealAnswer::from(QuestionType::Number, ['min' => 3], [])->isMetBy(2));
        $this->assertTrue(IdealAnswer::from(QuestionType::Number, ['max' => 0], [])->isMetBy(0));
        $this->assertFalse($both->isMetBy(true));
    }

    public function test_the_limits_of_a_number_ideal_are_checked(): void
    {
        foreach ([[], ['min' => -1], ['min' => 1.5], ['max' => '4'], ['min' => 5, 'max' => 4], ['max' => 1_000_000_000]] as $input) {
            $this->refused(QuestionType::Number, $input);
        }
    }

    public function test_a_text_question_cannot_have_an_ideal_answer(): void
    {
        $this->refused(QuestionType::Text, ['yes' => true]);
        $this->assertFalse(QuestionType::Text->supportsMustHave());
        $this->assertTrue(QuestionType::Number->supportsMustHave());
    }
}
