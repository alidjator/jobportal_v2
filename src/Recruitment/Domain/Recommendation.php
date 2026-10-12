<?php

namespace Src\Recruitment\Domain;

/** A panel member's final recommendation on a scorecard. The values match the check constraint of `recruitment_interview_scorecards.recommendation`. */
enum Recommendation: string
{
    case StrongNo = 'strong_no';
    case No = 'no';
    case Yes = 'yes';
    case StrongYes = 'strong_yes';

    public function isPositive(): bool
    {
        return $this === self::Yes || $this === self::StrongYes;
    }
}
