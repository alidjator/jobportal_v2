<?php

namespace Src\Assessment\Domain\Events;

/** What happened to an invitation; the candidate is told about every one of them. */
enum InvitationChange: string
{
    case Invited = 'invited';
    case Reminder = 'reminder';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
