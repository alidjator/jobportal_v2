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

    /** The team kept the current time instead of the one the job seeker proposed; the event carries the team's optional message. */
    case ProposalRefused = 'proposal_refused';

    /** The team recorded that the job seeker did not come. */
    case NoShow = 'no_show';

    /** The job seeker is reminded that the confirmed interview starts within two hours. */
    case StartingSoon = 'starting_soon';
}
