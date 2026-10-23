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

    /** To the team: the candidate turned the test down (for example because it records video), so another way to assess them can be planned. */
    public const string DECLINED = 'assessment.declined';

    /** To the team: the candidate handed in the test, or the time ran out and the server did. */
    public const string SUBMITTED = 'assessment.submitted';

    /** To the team: a psychologist signed the report of a psychological test, so its scores can be read. */
    public const string REPORT_SIGNED = 'assessment.report_signed';

    /** To the candidate: the psychologist signed the report, so the explanation of the result can be read. */
    public const string EXPLANATION_READY = 'assessment.explanation_ready';

    /** To the team: a psychologist revised the report that was issued automatically, so the scores and the text may differ from what was read before. */
    public const string REPORT_REVISED = 'assessment.report_revised';

    /** To the candidate: the explanation of the result was revised, so it can be read again. */
    public const string EXPLANATION_REVISED = 'assessment.explanation_revised';

    /** To the team: an application moved on by itself because the score of its test reached the threshold of the job's default test (`phase-8-assessment-automation.md` section 2). */
    public const string AUTO_ADVANCED = 'application.auto_advanced';

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
