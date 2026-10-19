<?php

namespace Tests\Unit\Recruitment\Domain;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewFormat;
use Src\Recruitment\Domain\InterviewStatus;
use Src\Recruitment\Domain\RecordingConsent;
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

    public function test_an_onsite_interview_keeps_its_work_location_and_the_other_formats_drop_it(): void
    {
        $onsite = Interview::schedule(7, $this->now->modify('+2 days'), 45, InterviewFormat::Onsite, 'Jl. Setiabudi 1', null, 3, 2, $this->now, locationId: 12);
        $online = Interview::schedule(7, $this->now->modify('+2 days'), 45, InterviewFormat::Online, null, 'https://a.test', 3, 2, $this->now, locationId: 12);
        $free = Interview::schedule(7, $this->now->modify('+2 days'), 45, InterviewFormat::Onsite, 'Room 2', null, 3, 2, $this->now);

        $this->assertSame(12, $onsite->locationId);
        $this->assertNull($online->locationId);
        $this->assertNull($free->locationId);
    }

    private function video(bool $recorded): Interview
    {
        return Interview::schedule(7, $this->now->modify('+2 days'), 45, InterviewFormat::Video, 'Somewhere', 'https://a.test', 3, 2, $this->now, recorded: $recorded);
    }

    public function test_a_video_interview_needs_neither_a_place_nor_a_link(): void
    {
        $video = $this->video(false);

        $this->assertSame(InterviewFormat::Video, $video->format);
        $this->assertNull($video->location);
        $this->assertNull($video->meetingUrl);
        $this->assertFalse($video->recorded);
        $this->assertNull($video->recordingConsent);
    }

    public function test_only_a_video_interview_can_be_recorded(): void
    {
        $this->assertTrue($this->video(true)->recorded);

        try {
            Interview::schedule(7, $this->now->modify('+1 day'), 30, InterviewFormat::Online, null, 'https://a.test', 3, 2, $this->now, recorded: true);
            $this->fail('Expected the recording to be refused.');
        } catch (BusinessRuleViolation $violation) {
            $this->assertSame('invalid-interview', $violation->errorCode());
            $this->assertArrayHasKey('recorded', $violation->extensions()['errors']);
        }
    }

    public function test_the_job_seeker_answers_the_recording_when_confirming_a_recorded_interview(): void
    {
        $granted = $this->video(true)->confirm(RecordingConsent::Granted, $this->now);

        $this->assertSame(InterviewStatus::Confirmed, $granted->status);
        $this->assertSame(RecordingConsent::Granted, $granted->recordingConsent);
        $this->assertEquals($this->now, $granted->recordingConsentAt);
        $this->assertSame(RecordingConsent::Refused, $this->video(true)->confirm(RecordingConsent::Refused, $this->now)->recordingConsent);

        foreach ([null, RecordingConsent::Withdrawn] as $answer) {
            try {
                $this->video(true)->confirm($answer, $this->now);
                $this->fail('Expected an answer to the recording.');
            } catch (BusinessRuleViolation $violation) {
                $this->assertSame('recording-consent-required', $violation->errorCode());
            }
        }

        // Not recorded: the answer is ignored.
        $this->assertNull($this->video(false)->confirm(RecordingConsent::Granted, $this->now)->recordingConsent);
    }

    public function test_proposing_another_time_also_answers_the_recording(): void
    {
        $proposed = $this->video(true)->propose($this->now->modify('+3 days'), RecordingConsent::Granted, $this->now);

        $this->assertSame(RecordingConsent::Granted, $proposed->recordingConsent);
        $this->assertSame(RecordingConsent::Granted, $proposed->acceptProposal()->recordingConsent);
        $this->expectException(BusinessRuleViolation::class);
        $this->video(true)->propose($this->now->modify('+3 days'), null, $this->now);
    }

    public function test_a_given_consent_can_be_withdrawn_once(): void
    {
        $granted = $this->video(true)->confirm(RecordingConsent::Granted, $this->now);
        $withdrawn = $granted->withdrawRecordingConsent($this->now->modify('+1 hour'));

        $this->assertSame(RecordingConsent::Withdrawn, $withdrawn->recordingConsent);
        $this->assertSame(InterviewStatus::Confirmed, $withdrawn->status);
        $this->conflict(fn () => $withdrawn->withdrawRecordingConsent($this->now), 'no-recording-consent');
        $this->conflict(fn () => $this->video(true)->confirm(RecordingConsent::Refused, $this->now)->withdrawRecordingConsent($this->now), 'no-recording-consent');
        $this->conflict(fn () => $this->video(false)->withdrawRecordingConsent($this->now), 'no-recording-consent');
        $this->conflict(fn () => $granted->cancel()->withdrawRecordingConsent($this->now), 'interview-not-active');
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
        $confirmed = $this->interview()->confirm(null, $this->now);

        $this->assertSame(InterviewStatus::Confirmed, $confirmed->status);
        $this->conflict(fn () => $confirmed->confirm(null, $this->now), 'interview-not-pending');
        $this->assertSame(InterviewStatus::Completed, $confirmed->complete()->status);
        $this->conflict(fn () => $this->interview()->complete(), 'interview-not-confirmed');
    }

    public function test_the_job_seeker_proposes_at_most_twice_and_one_at_a_time(): void
    {
        $first = $this->interview()->propose($this->now->modify('+3 days'), null, $this->now);

        $this->assertTrue($first->hasProposal());
        $this->assertSame(1, $first->proposalsUsed);
        $this->conflict(fn () => $first->propose($this->now->modify('+4 days'), null, $this->now), 'proposal-pending');
        $this->conflict(fn () => $first->confirm(null, $this->now), 'proposal-pending');

        $accepted = $first->acceptProposal();
        $this->assertSame(InterviewStatus::Confirmed, $accepted->status);
        $this->assertSame('2026-10-11 10:00', $accepted->startsAt->format('Y-m-d H:i'));
        $this->assertSame(45, $accepted->durationMinutes());
        $this->assertFalse($accepted->hasProposal());

        $second = $this->interview()->propose($this->now->modify('+3 days'), null, $this->now)->acceptProposal();
        $this->assertSame(InterviewStatus::Confirmed, $second->status);

        $pending = new Interview(7, $this->now->modify('+2 days'), $this->now->modify('+2 days 45 minutes'), InterviewFormat::Online, null, 'https://a.test', 3, 2, InterviewStatus::Pending, 2, null, $this->now);
        $this->conflict(fn () => $pending->propose($this->now->modify('+5 days'), null, $this->now), 'proposal-limit');
    }

    public function test_a_proposal_must_be_in_the_future(): void
    {
        $this->expectException(BusinessRuleViolation::class);

        $this->interview()->propose($this->now->modify('-1 hour'), null, $this->now);
    }

    public function test_accepting_needs_a_proposal(): void
    {
        $this->conflict(fn () => $this->interview()->acceptProposal(), 'no-proposal');
    }

    public function test_decline_and_cancel_end_an_active_interview_only(): void
    {
        $this->assertSame(InterviewStatus::Declined, $this->interview()->decline()->status);
        $this->assertSame(InterviewStatus::Declined, $this->interview()->confirm(null, $this->now)->decline()->status);
        $this->assertSame(InterviewStatus::Cancelled, $this->interview()->cancel()->status);

        $cancelled = $this->interview()->cancel();
        $this->conflict(fn () => $cancelled->cancel(), 'interview-not-active');
        $this->conflict(fn () => $cancelled->decline(), 'interview-not-active');
        $this->conflict(fn () => $cancelled->acceptProposal(), 'interview-not-active');
    }
}
