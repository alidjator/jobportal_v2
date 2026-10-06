<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\StageType;

/** The `type` keys of the in-app notifications of this context. The server stores no text: clients write the message from the key and the data. */
final class ApplicationNotificationType
{
    public const string RECEIVED = 'application.received';

    public const string MOVED_TO_INTERVIEW = 'application.moved_to_interview';

    public const string MOVED_TO_OFFER = 'application.moved_to_offer';

    public const string HIRED = 'application.hired';

    public const string REJECTED = 'application.rejected';

    /** The in-app type for an application that entered this stage type, or null when the applicant is not told (only these four outcomes are announced). */
    public static function forStage(StageType $type): ?string
    {
        return match ($type) {
            StageType::Interview => self::MOVED_TO_INTERVIEW,
            StageType::Offer => self::MOVED_TO_OFFER,
            StageType::Hired => self::HIRED,
            StageType::Rejected => self::REJECTED,
            StageType::Applied, StageType::Screening, StageType::Withdrawn => null,
        };
    }

    /** The kind the applicant can tune (email delivery of stage changes, step 8): in-app notifications are always written. */
    public const string UPDATE_PREFERENCE = 'application_update';
}
