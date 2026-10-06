<?php

namespace Src\Recruitment\Domain;

/** The fixed reasons for rejecting an application. The values match the check constraint of `recruitment_application_stage_history.rejection_reason`. */
enum RejectionReason: string
{
    case QualificationsMismatch = 'qualifications_mismatch';
    case ExperienceInsufficient = 'experience_insufficient';
    case SalaryExpectationMismatch = 'salary_expectation_mismatch';
    case LocationMismatch = 'location_mismatch';
    case FailedScreening = 'failed_screening';
    case PositionFilled = 'position_filled';
    case Other = 'other';

    /** `other` needs a text note; the others may carry one. */
    public function requiresNote(): bool
    {
        return $this === self::Other;
    }
}
