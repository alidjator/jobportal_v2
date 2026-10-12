<?php

namespace Tests\Unit\Recruitment\Domain;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Src\Recruitment\Domain\InterviewKit;
use Src\Recruitment\Domain\KitQuestion;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

final class InterviewKitTest extends TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-10-12 09:00:00');
    }

    /** @return array{text: string, guidance: ?string, weight: int} */
    private function question(string $text = 'Tell us about a hard bug.', ?string $guidance = null, int $weight = 3): array
    {
        return ['text' => $text, 'guidance' => $guidance, 'weight' => $weight];
    }

    /** @return array<string, list<string>> */
    private function errorsOf(callable $attempt): array
    {
        try {
            $attempt();
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('invalid-kit', $violation->errorCode());

            return $violation->extensions()['errors'];
        }

        $this->fail('The kit was accepted.');
    }

    public function test_a_kit_trims_its_name_and_questions_and_drops_an_empty_guidance(): void
    {
        $kit = InterviewKit::create(4, '  Backend round  ', [$this->question('  Explain a transaction.  ', '   ', 5)], 9, $this->now);

        $this->assertSame('Backend round', $kit->name);
        $this->assertEquals([new KitQuestion('Explain a transaction.', null, 5)], $kit->questions);
        $this->assertSame(9, $kit->createdBy);
        $this->assertSame(9, $kit->updatedBy);
    }

    public function test_a_kit_needs_a_name_of_at_most_one_hundred_characters(): void
    {
        $this->assertArrayHasKey('name', $this->errorsOf(fn () => InterviewKit::create(4, '   ', [$this->question()], 9, $this->now)));
        $this->assertArrayHasKey('name', $this->errorsOf(fn () => InterviewKit::create(4, str_repeat('a', 101), [$this->question()], 9, $this->now)));
        $this->assertSame('a', InterviewKit::create(4, 'a', [$this->question()], 9, $this->now)->name);
    }

    public function test_a_kit_has_one_to_twenty_questions(): void
    {
        $this->assertArrayHasKey('questions', $this->errorsOf(fn () => InterviewKit::create(4, 'Kit', [], 9, $this->now)));
        $this->assertArrayHasKey('questions', $this->errorsOf(fn () => InterviewKit::create(4, 'Kit', array_fill(0, 21, $this->question()), 9, $this->now)));
        $this->assertCount(20, InterviewKit::create(4, 'Kit', array_fill(0, 20, $this->question()), 9, $this->now)->questions);
    }

    public function test_every_broken_rule_is_reported_by_its_field(): void
    {
        $errors = $this->errorsOf(fn () => InterviewKit::create(4, 'Kit', [
            $this->question(),
            $this->question(' '),
            $this->question(str_repeat('q', 501)),
            $this->question('Fine', str_repeat('g', 1001)),
            $this->question('Fine', null, 0),
            $this->question('Fine', null, 6),
        ], 9, $this->now));

        $this->assertSame(['questions.1.text', 'questions.2.text', 'questions.3.guidance', 'questions.4.weight', 'questions.5.weight'], array_keys($errors));
    }

    public function test_a_revision_keeps_the_creator_and_the_creation_time(): void
    {
        $kit = InterviewKit::create(4, 'Kit', [$this->question()], 9, $this->now);
        $later = $this->now->modify('+1 day');

        $revised = $kit->revise('Kit v2', [$this->question('Another one')], 11, $later);

        $this->assertSame('Kit v2', $revised->name);
        $this->assertSame(9, $revised->createdBy);
        $this->assertSame(11, $revised->updatedBy);
        $this->assertEquals($this->now, $revised->createdAt);
        $this->assertEquals($later, $revised->updatedAt);
    }

    public function test_the_questions_of_an_interview_may_be_an_empty_list(): void
    {
        $this->assertSame([], KitQuestion::many([], 0));
        $this->expectException(BusinessRuleViolation::class);

        KitQuestion::many([], 1);
    }
}
