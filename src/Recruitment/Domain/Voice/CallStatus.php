<?php

namespace Src\Recruitment\Domain\Voice;

enum CallStatus: string
{
    case Dialing = 'dialing';
    case Ringing = 'ringing';
    case Answered = 'answered';
    case NoAnswer = 'no_answer';
    case Busy = 'busy';
    case Failed = 'failed';
    case Ended = 'ended';

    /** The call is still in progress: being dialled, ringing or answered. */
    public function isLive(): bool
    {
        return in_array($this, [self::Dialing, self::Ringing, self::Answered], true);
    }

    /** The candidate never picked up: the team may try again and the candidate is told of a missed call. */
    public function isMissed(): bool
    {
        return $this === self::NoAnswer || $this === self::Busy;
    }
}
