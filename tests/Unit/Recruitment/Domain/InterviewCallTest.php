<?php

namespace Tests\Unit\Recruitment\Domain;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Src\Recruitment\Application\Voice\VoiceNames;
use Src\Recruitment\Domain\Voice\CallStatus;
use Src\Recruitment\Domain\Voice\InterviewCall;

final class InterviewCallTest extends TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-10-12 09:00:00');
    }

    private function stored(): InterviewCall
    {
        $call = InterviewCall::dial(7, 3, '+6281234567890', $this->now);

        return new InterviewCall(1, $call->interviewId, $call->startedBy, $call->status, $call->number, $call->channelId, $call->dialedAt);
    }

    public function test_a_new_call_is_dialling_on_a_unique_channel(): void
    {
        $call = InterviewCall::dial(7, 3, '+6281234567890', $this->now);

        $this->assertSame(CallStatus::Dialing, $call->status);
        $this->assertMatchesRegularExpression('/^call-[0-9a-f]{16}$/', $call->channelId);
        $this->assertNotSame($call->channelId, InterviewCall::dial(7, 3, '+6281234567890', $this->now)->channelId);
    }

    public function test_an_answered_call_ends_with_its_duration(): void
    {
        $ended = $this->stored()->ringing()->answered($this->now->modify('+8 seconds'))->ended($this->now->modify('+68 seconds'), 16);

        $this->assertSame(CallStatus::Ended, $ended->status);
        $this->assertSame(60, $ended->durationSeconds);
        $this->assertSame(16, $ended->hangupCause);
    }

    public function test_a_call_nobody_answered_ends_by_its_cause(): void
    {
        $this->assertSame(CallStatus::NoAnswer, $this->stored()->ended($this->now, 19)->status);
        $this->assertSame(CallStatus::NoAnswer, $this->stored()->ringing()->ended($this->now, 21)->status);
        $this->assertSame(CallStatus::Busy, $this->stored()->ended($this->now, 17)->status);
        $this->assertSame(CallStatus::Failed, $this->stored()->ended($this->now, 1)->status);
        $this->assertSame(CallStatus::Failed, $this->stored()->ended($this->now, null)->status);
        $this->assertNull($this->stored()->ended($this->now, 19)->durationSeconds);
    }

    public function test_a_call_that_is_over_does_not_change_again(): void
    {
        $ended = $this->stored()->ended($this->now, 19);

        $this->assertSame($ended, $ended->answered($this->now));
        $this->assertSame($ended, $ended->ended($this->now->modify('+1 minute'), 16));
        $this->assertSame($ended, $ended->ringing());
    }

    public function test_only_the_first_ring_and_the_first_answer_count(): void
    {
        $answered = $this->stored()->answered($this->now);

        $this->assertSame($answered, $answered->answered($this->now->modify('+5 seconds')));
        $this->assertSame($answered, $answered->ringing());
        $this->assertTrue(CallStatus::Busy->isMissed());
        $this->assertFalse(CallStatus::Failed->isMissed());
        $this->assertTrue(CallStatus::Ringing->isLive());
        $this->assertFalse(CallStatus::Ended->isLive());
    }

    public function test_the_names_on_the_voice_server_are_derived_and_parsed_strictly(): void
    {
        $this->assertSame('interview-12', VoiceNames::bridge(12));
        $this->assertSame(12, VoiceNames::interviewOfBridge('interview-12'));
        $this->assertNull(VoiceNames::interviewOfBridge('interview-12; drop'));
        $this->assertNull(VoiceNames::interviewOfBridge('interview-'));
        $this->assertSame('t12-5', VoiceNames::endpoint(12, 5));
        $this->assertSame('t12-5', VoiceNames::endpointOfChannelName('PJSIP/t12-5-0000001a'));
        $this->assertNull(VoiceNames::endpointOfChannelName('PJSIP/trunk-00000001'));
        $this->assertNull(VoiceNames::endpointOfChannelName('SIP/t12-5-00000001'));
    }
}
