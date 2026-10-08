<?php

namespace Tests\Unit\Recruitment\Domain;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewFormat;
use Src\Recruitment\Domain\InterviewStatus;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

final class InterviewTest extends TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-10-08 10:00:00 UTC');
    }

    private function interview(InterviewFormat $format = InterviewFormat::Online, ?string $location = null, ?string $url = 'https://meet.example.test/a'): Interview
    {
        return Interview::schedule(7, $this->now->modify('+2 days'), 45, $format, $location, $url, 3, 2, $this->now);
    }

    private function conflict(callable $action, string $code): void
    {
        try {
            $action();
            $this->fail('Expected a conflict.');
        } catch (StateConflict $conflict) {
            $this->assertSame($code, $conflict->errorCode());
        }
    }

    public function test_a_new_interview_is_pending_and_keeps_only_what_its_format_needs(): void
    {
        $online = $this->interview(location: 'Somewhere');
        $onsite = $this->interview(InterviewFormat::Onsite, ' Room 2 ', 'https://x.test');

        $this->assertSame(InterviewStatus::Pending, $online->status);
        $this->assertNull($online->location);
        $this->assertSame('https://meet.example.test/a', $online->meetingUrl);
        $this->assertSame('Room 2', $onsite->location);
        $this->assertNull($onsite->meetingUrl);
        $this->assertSame(45, $online->durationMinutes());
    }

    public function test_the_rules_of_a_new_interview(): void
    {
        $cases = [
            [fn () => Interview::schedule(7, $this->now, 30, InterviewFormat::Online, null, 'https://a.test', 3, 2, $this->now), 'starts_at'],
            [fn () => Interview::schedule(7, $this->now->modify('+1 day'), 4, InterviewFormat::Online, null, 'https://a.test', 3, 2, $this->now), 'duration_minutes'],
            [fn () => Interview::schedule(7, $this->now->modify('+1 day'), 481, InterviewFormat::Online, null, 'https://a.test', 3, 2, $this->now), 'duration_minutes'],
            [fn () => Interview::schedule(7, $this->now->modify('+1 day'), 30, InterviewFormat::Onsite, ' ', 'https://a.test', 3, 2, $this->now), 'location'],
            [fn () => Interview::schedule(7, $this->now->modify('+1 day'), 30, InterviewFormat::Online, 'Room', null, 3, 2, $this->now), 'meeting_url'],
        ];

        foreach ($cases as [$action, $field]) {
            try {
                $action();
                $this->fail("Expected {$field} to be refused.");
            } catch (BusinessRuleViolation $violation) {
                $this->assertSame('invalid-interview', $violation->errorCode());
                $this->assertArrayHasKey($field, $violation->extensions()['errors']);
            }
        }
    }

    public function test_confirm_then_complete(): void
    {
        $confirmed = $this->interview()->confirm();

        $this->assertSame(InterviewStatus::Confirmed, $confirmed->status);
        $this->conflict(fn () => $confirmed->confirm(), 'interview-not-pending');
        $this->assertSame(InterviewStatus::Completed, $confirmed->complete()->status);
        $this->conflict(fn () => $this->interview()->complete(), 'interview-not-confirmed');
    }

    public function test_the_job_seeker_proposes_at_most_twice_and_one_at_a_time(): void
    {
        $first = $this->interview()->propose($this->now->modify('+3 days'), $this->now);

        $this->assertTrue($first->hasProposal());
        $this->assertSame(1, $first->proposalsUsed);
        $this->conflict(fn () => $first->propose($this->now->modify('+4 days'), $this->now), 'proposal-pending');
        $this->conflict(fn () => $first->confirm(), 'proposal-pending');

        $accepted = $first->acceptProposal();
        $this->assertSame(InterviewStatus::Confirmed, $accepted->status);
        $this->assertSame('2026-10-11 10:00', $accepted->startsAt->format('Y-m-d H:i'));
        $this->assertSame(45, $accepted->durationMinutes());
        $this->assertFalse($accepted->hasProposal());

        $second = $this->interview()->propose($this->now->modify('+3 days'), $this->now)->acceptProposal();
        $this->assertSame(InterviewStatus::Confirmed, $second->status);

        $pending = new Interview(7, $this->now->modify('+2 days'), $this->now->modify('+2 days 45 minutes'), InterviewFormat::Online, null, 'https://a.test', 3, 2, InterviewStatus::Pending, 2, null, $this->now);
        $this->conflict(fn () => $pending->propose($this->now->modify('+5 days'), $this->now), 'proposal-limit');
    }

    public function test_a_proposal_must_be_in_the_future(): void
    {
        $this->expectException(BusinessRuleViolation::class);

        $this->interview()->propose($this->now->modify('-1 hour'), $this->now);
    }

    public function test_accepting_needs_a_proposal(): void
    {
        $this->conflict(fn () => $this->interview()->acceptProposal(), 'no-proposal');
    }

    public function test_decline_and_cancel_end_an_active_interview_only(): void
    {
        $this->assertSame(InterviewStatus::Declined, $this->interview()->decline()->status);
        $this->assertSame(InterviewStatus::Declined, $this->interview()->confirm()->decline()->status);
        $this->assertSame(InterviewStatus::Cancelled, $this->interview()->cancel()->status);

        $cancelled = $this->interview()->cancel();
        $this->conflict(fn () => $cancelled->cancel(), 'interview-not-active');
        $this->conflict(fn () => $cancelled->decline(), 'interview-not-active');
        $this->conflict(fn () => $cancelled->acceptProposal(), 'interview-not-active');
    }
}
