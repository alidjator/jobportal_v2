<?php

namespace Tests\Unit\Recruitment\Domain;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Src\Recruitment\Domain\Offer;
use Src\Recruitment\Domain\OfferStatus;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

final class OfferTest extends TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-10-10 03:00:00 UTC');
    }

    private function offer(string $start = '2026-11-02', string $expires = '2026-10-20 10:00:00 UTC', int $salary = 10_000_000, string $position = 'Developer'): Offer
    {
        return Offer::make(1, $position, $salary, new DateTimeImmutable($start), '  note  ', new DateTimeImmutable($expires), 7, $this->now);
    }

    public function test_a_new_offer_is_pending_with_a_trimmed_note(): void
    {
        $offer = $this->offer();

        $this->assertSame(OfferStatus::Pending, $offer->status);
        $this->assertSame('note', $offer->note);
    }

    public function test_today_in_jakarta_is_a_valid_start_date(): void
    {
        $this->assertSame('2026-10-10', $this->offer(start: '2026-10-10')->startDate->format('Y-m-d'));
    }

    public function test_invalid_values_are_refused(): void
    {
        foreach ([
            ['start' => '2026-10-09'],
            ['expires' => '2026-10-10 03:00:00 UTC'],
            ['expires' => '2026-11-10 03:00:01 UTC'],
            ['salary' => 0],
            ['position' => '  '],
        ] as $arguments) {
            try {
                $this->offer(...$arguments);
                $this->fail('Expected a refusal for '.json_encode($arguments));
            } catch (BusinessRuleViolation $violation) {
                $this->assertSame('invalid-offer', $violation->errorCode());
            }
        }
    }

    public function test_the_status_turns_expired_at_the_deadline(): void
    {
        $offer = $this->offer();

        $this->assertSame(OfferStatus::Pending, $offer->statusAt($this->now));
        $this->assertSame(OfferStatus::Expired, $offer->statusAt($offer->expiresAt));
    }

    public function test_answering_needs_a_pending_offer_inside_its_deadline(): void
    {
        $offer = $this->offer();

        $this->assertSame(OfferStatus::Accepted, $offer->accept($this->now)->status);
        $this->assertSame('No.', $offer->decline(' No. ', $this->now)->answerNote);

        foreach ([['offer-expired', fn () => $offer->accept($offer->expiresAt)], ['offer-not-pending', fn () => $offer->withdraw()->accept($this->now)]] as [$code, $attempt]) {
            try {
                $attempt();
                $this->fail('Expected '.$code);
            } catch (StateConflict $conflict) {
                $this->assertSame($code, $conflict->errorCode());
            }
        }
    }
}
