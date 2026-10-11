<?php

namespace Src\Assessment\Application\Delivery;

use Src\Assessment\Domain\Events\InvitationChange;

/** The `type` keys of the in-app notifications about tests. The server stores no text: clients write the message from the key and the data. */
final class AssessmentNotificationType
{
    public const string INVITED = 'assessment.invited';

    public const string REMINDER = 'assessment.reminder';

    public const string EXPIRED = 'assessment.expired';

    public const string CANCELLED = 'assessment.cancelled';

    /** To the team: the candidate handed in the test, or the time ran out and the server did. */
    public const string SUBMITTED = 'assessment.submitted';

    /** The email preference of application updates, registered by Recruitment: a candidate who turned it off gets no email about tests either. */
    public const string UPDATE_PREFERENCE = 'application_update';

    public static function forChange(InvitationChange $change): string
    {
        return match ($change) {
            InvitationChange::Invited => self::INVITED,
            InvitationChange::Reminder => self::REMINDER,
            InvitationChange::Expired => self::EXPIRED,
            InvitationChange::Cancelled => self::CANCELLED,
        };
    }
}
