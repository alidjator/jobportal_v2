<?php

namespace Src\JobInvitation\Domain;

/** Where an invitation stands. `expired` is not a status: it is derived while the job is not live. */
enum InvitationStatus: string
{
    case Sent = 'sent';
    case Viewed = 'viewed';
    case Applied = 'applied';
    case Declined = 'declined';

    /** Whether the candidate may still answer it. */
    public function isOpen(): bool
    {
        return $this === self::Sent || $this === self::Viewed;
    }
}
