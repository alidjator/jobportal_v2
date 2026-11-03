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

    /** To the psychologist: the platform admin approved the licence they submitted, so it is in force. */
    public const string LICENCE_APPROVED = 'assessment.licence_approved';

    /** To the psychologist: the platform admin rejected the licence they submitted; the note is on the submission. */
    public const string LICENCE_REJECTED = 'assessment.licence_rejected';

    /** To a psychologist: reports wait for a review at their provider (`data.count`, and `data.overdue` of them past their review day); sent when the count rises (`phase-8-provider-tools.md` section 5). */
    public const string PROVIDER_REVIEWS_WAITING = 'provider.reviews_waiting';

    /** To the signer and the leads of the institution: a company asked about a signed report (`data.report_id`, `company_name`, `test_title`, `report_number`). */
    public const string PROVIDER_REPORT_QUESTION = 'provider.report_question';

    /** To the company members who asked: the institution answered about a signed report (`data.invitation_id`, `test_title`, `report_number`). */
    public const string REPORT_ANSWERED = 'assessment.report_answered';

    /** To a psychologist: their STR or SILP ends in 60, 30 or 7 days, or ended (`data.kind`, `valid_until`, `days_left`; 0 or less means it ended and they cannot sign). */
    public const string LICENCE_EXPIRING = 'assessment.licence_expiring';

    /** To a platform admin with `assessment_providers.manage`: a psychologist's licence is ending or ended (`data.provider_name`, `psychologist_name`, `kind`, `valid_until`, `days_left`). */
    public const string PROVIDER_LICENCE_EXPIRING = 'assessment.provider_licence_expiring';

    /** To the psychologists of a provider: the licence of the psychologist who answers for the automatic issue of a battery ended, so its reports wait for a psychologist again (`data.test_title`). */
    public const string AUTO_ISSUE_PAUSED = 'provider.auto_issue_paused';

    /** The email preference of the waiting-reviews notice, registered by Assessment for psychologists (the licence emails are always sent). */
    public const string REVIEWS_PREFERENCE = 'provider_reviews';

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
