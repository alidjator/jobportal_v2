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
    case RecordingWithdrawn = 'recording_withdrawn';

    /** The extra members of the panel changed: `addedUserIds` are invited, `removedUserIds` lose the calendar event. */
    case PanelChanged = 'panel_changed';

    /** Only the details (the note for the candidate) changed: the time and the confirmation stay. */
    case Updated = 'updated';
}
