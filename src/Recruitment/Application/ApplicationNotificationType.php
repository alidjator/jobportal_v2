<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Events\InterviewChange;
use Src\Recruitment\Domain\Events\OfferChange;
use Src\Recruitment\Domain\StageType;

/** The `type` keys of the in-app notifications of this context. The server stores no text: clients write the message from the key and the data. */
final class ApplicationNotificationType
{
    public const string RECEIVED = 'application.received';

    public const string MOVED_TO_INTERVIEW = 'application.moved_to_interview';

    public const string MOVED_TO_OFFER = 'application.moved_to_offer';

    public const string HIRED = 'application.hired';

    public const string REJECTED = 'application.rejected';

    /** The team marked the application withdrawn: the job seeker is told. */
    public const string WITHDRAWN_BY_COMPANY = 'application.withdrawn_by_company';

    /** An application of the job was withdrawn (by the job seeker, by an offer decline or by a team member): the job's team is told. */
    public const string WITHDRAWN = 'application.withdrawn';

    public const string INTERVIEW_SCHEDULED = 'interview.scheduled';

    public const string INTERVIEW_RESCHEDULED = 'interview.rescheduled';

    public const string INTERVIEW_CANCELLED = 'interview.cancelled';

    /** A withdrawal cancelled the interview: its interviewer and panel are told. */
    public const string INTERVIEW_CANCELLED_FOR_TEAM = 'interview.cancelled_for_team';

    /** The details of the interview (its note) changed; the time and the confirmation stay. */
    public const string INTERVIEW_UPDATED = 'interview.updated';

    public const string INTERVIEW_PROPOSAL_ACCEPTED = 'interview.proposal_accepted';

    public const string INTERVIEW_CONFIRMED = 'interview.confirmed';

    public const string INTERVIEW_PROPOSED = 'interview.proposed';

    public const string INTERVIEW_DECLINED = 'interview.declined';

    public const string INTERVIEW_REMINDER = 'interview.reminder';

    /** The team called the candidate of a phone interview and nobody answered (the candidate is the one told). */
    public const string INTERVIEW_CALL_MISSED = 'interview.call_missed';

    public const string INTERVIEW_RECORDING_WITHDRAWN = 'interview.recording_withdrawn';

    public const string INTERVIEW_SCORECARD_REMINDER = 'interview.scorecard_reminder';

    public const string OFFER_SENT = 'offer.sent';

    public const string OFFER_REVISED = 'offer.revised';

    public const string OFFER_WITHDRAWN = 'offer.withdrawn';

    public const string OFFER_ACCEPTED = 'offer.accepted';

    public const string OFFER_DECLINED = 'offer.declined';

    public const string OFFER_EXPIRING = 'offer.expiring';

    /** The in-app type of a change to an offer. */
    public static function forOffer(OfferChange $change): string
    {
        return match ($change) {
            OfferChange::Sent => self::OFFER_SENT,
            OfferChange::Revised => self::OFFER_REVISED,
            OfferChange::Withdrawn => self::OFFER_WITHDRAWN,
            OfferChange::Accepted => self::OFFER_ACCEPTED,
            OfferChange::Declined => self::OFFER_DECLINED,
            OfferChange::Expiring => self::OFFER_EXPIRING,
        };
    }

    /** The in-app type of a change to an interview. */
    public static function forInterview(InterviewChange $change): string
    {
        return match ($change) {
            InterviewChange::Scheduled => self::INTERVIEW_SCHEDULED,
            InterviewChange::Rescheduled => self::INTERVIEW_RESCHEDULED,
            InterviewChange::Cancelled => self::INTERVIEW_CANCELLED,
            InterviewChange::ProposalAccepted => self::INTERVIEW_PROPOSAL_ACCEPTED,
            InterviewChange::Confirmed => self::INTERVIEW_CONFIRMED,
            InterviewChange::Proposed => self::INTERVIEW_PROPOSED,
            InterviewChange::Declined => self::INTERVIEW_DECLINED,
            InterviewChange::Reminder => self::INTERVIEW_REMINDER,
            InterviewChange::RecordingWithdrawn => self::INTERVIEW_RECORDING_WITHDRAWN,
            InterviewChange::Updated => self::INTERVIEW_UPDATED,
        };
    }

    /** The in-app type for an application that entered this stage type, or null when the applicant is not told (only these four outcomes are announced). */
    public static function forStage(StageType $type): ?string
    {
        return match ($type) {
            StageType::Interview => self::MOVED_TO_INTERVIEW,
            StageType::Offer => self::MOVED_TO_OFFER,
            StageType::Hired => self::HIRED,
            StageType::Rejected => self::REJECTED,
            StageType::Applied, StageType::Screening, StageType::Assessment, StageType::Withdrawn => null,
        };
    }

    /** The kind the applicant can tune (email delivery of stage changes, step 8, and of interview schedules): in-app notifications are always written. */
    public const string UPDATE_PREFERENCE = 'application_update';
}
