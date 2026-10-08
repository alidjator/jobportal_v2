<?php

namespace Src\Recruitment\Domain\Events;

enum InterviewChange: string
{
    case Scheduled = 'scheduled';
    case Rescheduled = 'rescheduled';
    case Cancelled = 'cancelled';
    case ProposalAccepted = 'proposal_accepted';
    case Confirmed = 'confirmed';
    case Proposed = 'proposed';
    case Declined = 'declined';
    case Reminder = 'reminder';
}
