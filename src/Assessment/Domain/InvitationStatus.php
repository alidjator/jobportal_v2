<?php

namespace Src\Assessment\Domain;

/** Where an invitation stands. The values match the check constraint of `assessment_invitations.status`. */
enum InvitationStatus: string
{
    case Invited = 'invited';
    case Started = 'started';
    case Submitted = 'submitted';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /** An application has at most one active invitation: the candidate can still take the test, or is taking it. */
    public function isActive(): bool
    {
        return $this === self::Invited || $this === self::Started;
    }
}
