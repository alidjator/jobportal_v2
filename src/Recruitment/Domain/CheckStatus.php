<?php

namespace Src\Recruitment\Domain;

/** Where a check on an application stands. */
enum CheckStatus: string
{
    case Requested = 'requested';
    case Consented = 'consented';
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Waived = 'waived';
    case Cancelled = 'cancelled';

    /** Whether the check still waits for something: the team may change, waive or cancel it and record its result. */
    public function isOpen(): bool
    {
        return $this === self::Requested || $this === self::Consented || $this === self::Scheduled;
    }
}
