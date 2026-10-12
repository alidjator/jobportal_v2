<?php

namespace Tests\Unit\Recruitment\Domain;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Src\Recruitment\Domain\Recommendation;
use Src\Recruitment\Domain\Scorecard;
use Src\Recruitment\Domain\ScorecardAnswer;
use Src\Recruitment\Domain\ScorecardStatus;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

final class ScorecardTest extends TestCase
{
    private const array WEIGHTS = [10 => 5, 11 => 3, 12 => 1];

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-10-12 09:00:00');
    }

    public function test_the_score_is_the_weighted_average_of_the_answered_questions(): void
    {
        $this->assertSame(3.88, Scorecard::average([new ScorecardAnswer(10, 5, null), new ScorecardAnswer(11, 2, null)], self::WEIGHTS));
        $this->assertSame(4.0, Scorecard::average([new ScorecardAnswer(10, 4, null), new ScorecardAnswer(11, null, 'Skipped')], self::WEIGHTS));
        $this->assertNull(Scorecard::average([new ScorecardAnswer(11, null, 'Only a note')], self::WEIGHTS));
        $this->assertNull(Scorecard::average([], self::WEIGHTS));
    }

    public function test_content_is_trimmed_ordered_by_question_and_drops_empty_answers(): void
    {
        $card = Scorecard::blank(1, 2)->withContent(Recommendation::Yes, '  Good.  ', [
            ['question_id' => 11, 'score' => 3, 'note' => null],
            ['question_id' => 10, 'score' => null, 'note' => '   '],
            ['question_id' => 12, 'score' => 1, 'note' => ' ok '],
        ], self::WEIGHTS, $this->now);

        $this->assertSame('Good.', $card->note);
        $this->assertSame([11, 12], array_map(static fn (ScorecardAnswer $answer): int => $answer->questionId, $card->answers));
        $this->assertSame('ok', $card->answers[1]->note);
        $this->assertSame(ScorecardStatus::Draft, $card->status);
    }

    public function test_every_broken_rule_is_reported_by_its_field(): void
    {
        try {
            Scorecard::blank(1, 2)->withContent(null, str_repeat('n', 2001), [
                ['question_id' => 99, 'score' => 3, 'note' => null],
                ['question_id' => 10, 'score' => 0, 'note' => null],
                ['question_id' => 10, 'score' => 3, 'note' => str_repeat('n', 1001)],
            ], self::WEIGHTS, $this->now);
            $this->fail('The scorecard was accepted.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('invalid-scorecard', $violation->errorCode());
            $this->assertSame(['note', 'answers.0.question_id', 'answers.1.score', 'answers.2.question_id', 'answers.2.note'], array_keys($violation->extensions()['errors']));
        }
    }

    public function test_sending_needs_a_recommendation_and_a_sent_scorecard_keeps_one(): void
    {
        try {
            Scorecard::blank(1, 2)->submit($this->now);
            $this->fail('A scorecard without a recommendation was sent.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('recommendation-required', $violation->errorCode());
        }

        $sent = Scorecard::blank(1, 2)->withContent(Recommendation::No, null, [], self::WEIGHTS, $this->now)->submit($this->now);

        $this->assertTrue($sent->isSubmitted());
        $this->expectException(BusinessRuleViolation::class);

        $sent->withContent(null, null, [], self::WEIGHTS, $this->now);
    }

    public function test_an_edit_of_a_sent_scorecard_is_marked_only_when_the_content_changed(): void
    {
        $sent = Scorecard::blank(1, 2)->withContent(Recommendation::Yes, 'Note', [['question_id' => 10, 'score' => 4, 'note' => null]], self::WEIGHTS, $this->now)->submit($this->now);
        $later = $this->now->modify('+1 hour');

        $same = $sent->withContent(Recommendation::Yes, 'Note', [['question_id' => 10, 'score' => 4, 'note' => null]], self::WEIGHTS, $later);
        $changed = $sent->withContent(Recommendation::StrongYes, 'Note', [['question_id' => 10, 'score' => 4, 'note' => null]], self::WEIGHTS, $later);

        $this->assertNull($same->editedAt);
        $this->assertEquals($later, $changed->editedAt);
        $this->assertTrue($changed->isSubmitted());
    }
}
