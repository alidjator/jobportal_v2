<?php

namespace Src\JobInvitation\Domain;

/** Where an invitation by email stands. `expired` is set by the purge, when a person never answered. */
enum EmailInvitationStatus: string
{
    case Sent = 'sent';
    case Opened = 'opened';
    case Claimed = 'claimed';
    case Declined = 'declined';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /** Whether the person may still claim or decline it. */
    public function isOpen(): bool
    {
        return $this === self::Sent || $this === self::Opened;
    }
}
