<?php

namespace Src\Recruitment\Domain\Voice;

use DateTimeImmutable;

/**
 * One call of the team to the candidate. It is dialled, rings, and is either answered (then it ends when someone hangs up or the time cap is reached) or
 * ends unanswered (`no_answer`, `busy` or `failed`). The hangup cause is the Q.850 code the voice server reports.
 */
final readonly class InterviewCall
{
    /** Q.850 causes that mean the candidate was busy, and did not answer. Any other cause of a call that was never answered is a failure (a wrong number, a trunk problem). */
    private const array BUSY_CAUSES = [17];

    private const array NO_ANSWER_CAUSES = [18, 19, 21];

    public function __construct(
        public ?int $id,
        public int $interviewId,
        public int $startedBy,
        public CallStatus $status,
        public string $number,
        public string $channelId,
        public DateTimeImmutable $dialedAt,
        public ?DateTimeImmutable $answeredAt = null,
        public ?DateTimeImmutable $endedAt = null,
        public ?int $durationSeconds = null,
        public ?int $hangupCause = null,
    ) {}

    /** A call about to be dialled on a channel named `call-` and 16 random hex digits (unique, and known before the voice server is asked to dial). */
    public static function dial(int $interviewId, int $startedBy, string $number, DateTimeImmutable $now): self
    {
        return new self(null, $interviewId, $startedBy, CallStatus::Dialing, $number, 'call-'.bin2hex(random_bytes(8)), $now);
    }

    /** The phone rings at the candidate's end; later signals of the same kind change nothing. */
    public function ringing(): self
    {
        return $this->status === CallStatus::Dialing ? $this->changed(status: CallStatus::Ringing) : $this;
    }

    /** The candidate picked up; a call that is not live any more (it ended first) stays as it is. */
    public function answered(DateTimeImmutable $at): self
    {
        return $this->status->isLive() && $this->status !== CallStatus::Answered ? $this->changed(status: CallStatus::Answered, answeredAt: $at) : $this;
    }

    /** The call is over, by hangup, timeout or failure. An ended call is never changed again. */
    public function ended(DateTimeImmutable $at, ?int $cause): self
    {
        if (! $this->status->isLive()) {
            return $this;
        }

        if ($this->answeredAt !== null) {
            return $this->changed(status: CallStatus::Ended, endedAt: $at, durationSeconds: max(0, $at->getTimestamp() - $this->answeredAt->getTimestamp()), hangupCause: $cause);
        }

        $status = match (true) {
            in_array($cause, self::BUSY_CAUSES, true) => CallStatus::Busy,
            in_array($cause, self::NO_ANSWER_CAUSES, true) => CallStatus::NoAnswer,
            default => CallStatus::Failed,
        };

        return $this->changed(status: $status, endedAt: $at, hangupCause: $cause);
    }

    /** The call was dialled but the server gave up on it with no word from the network (a call left live when the voice session closed). */
    public function abandoned(DateTimeImmutable $at): self
    {
        return $this->ended($at, null);
    }

    private function changed(
        ?CallStatus $status = null,
        ?DateTimeImmutable $answeredAt = null,
        ?DateTimeImmutable $endedAt = null,
        ?int $durationSeconds = null,
        ?int $hangupCause = null,
    ): self {
        return new self(
            $this->id,
            $this->interviewId,
            $this->startedBy,
            $status ?? $this->status,
            $this->number,
            $this->channelId,
            $this->dialedAt,
            $answeredAt ?? $this->answeredAt,
            $endedAt ?? $this->endedAt,
            $durationSeconds ?? $this->durationSeconds,
            $hangupCause ?? $this->hangupCause,
        );
    }
}
