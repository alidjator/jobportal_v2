<?php

namespace Tests\Unit\Recruitment\Domain;

use PHPUnit\Framework\TestCase;
use Src\Recruitment\Domain\QuestionRules;
use Src\Recruitment\Domain\QuestionType;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

final class QuestionRulesTest extends TestCase
{
    public function test_the_text_is_trimmed_and_kept_between_5_and_300_characters(): void
    {
        $this->assertSame('Can you relocate?', QuestionRules::text('  Can you relocate?  '));
        $this->assertSame('abcde', QuestionRules::text('abcde'));
        $this->assertSame(300, mb_strlen(QuestionRules::text(str_repeat('é', 300))));

        foreach (['', 'abcd', '   abcd   ', str_repeat('a', 301)] as $bad) {
            try {
                QuestionRules::text($bad);
                $this->fail('The text was accepted.');
            } catch (BusinessRuleViolation $e) {
                $this->assertSame('invalid-screening-question', $e->errorCode());
                $this->assertArrayHasKey('text', $e->extensions()['errors']);
            }
        }
    }

    public function test_a_choice_question_needs_two_to_ten_trimmed_unique_options(): void
    {
        $this->assertSame(['Yes', 'No'], QuestionRules::options(QuestionType::SingleChoice, [' Yes ', 'No']));
        $this->assertCount(10, QuestionRules::options(QuestionType::MultipleChoice, array_map(fn (int $n): string => "Option {$n}", range(1, 10))));
        $this->assertSame([str_repeat('a', 100), 'b'], QuestionRules::options(QuestionType::SingleChoice, [str_repeat('a', 100), 'b']));
    }

    public function test_bad_options_are_refused(): void
    {
        $bad = [
            ['only one'],
            [],
            array_map(fn (int $n): string => "Option {$n}", range(1, 11)),
            ['Same', 'same'],
            ['Same', ' SAME '],
            ['ok', ''],
            ['ok', '   '],
            ['ok', str_repeat('a', 101)],
        ];

        foreach ($bad as $labels) {
            try {
                QuestionRules::options(QuestionType::SingleChoice, $labels);
                $this->fail('The options were accepted: '.json_encode($labels));
            } catch (BusinessRuleViolation $e) {
                $this->assertArrayHasKey('options', $e->extensions()['errors']);
            }
        }
    }

    public function test_only_choice_questions_have_options(): void
    {
        foreach ([QuestionType::Text, QuestionType::YesNo, QuestionType::Number] as $type) {
            $this->assertSame([], QuestionRules::options($type, []));

            try {
                QuestionRules::options($type, ['a', 'b']);
                $this->fail('Options were accepted for the type '.$type->value);
            } catch (BusinessRuleViolation $e) {
                $this->assertArrayHasKey('options', $e->extensions()['errors']);
            }
        }
    }
}
