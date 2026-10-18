<?php

namespace Src\Messaging\Domain;

/** Which side of a conversation made a report (the stored value differs from `SenderSide`: the report API says `job_seeker`). */
enum ReporterSide: string
{
    case JobSeeker = 'job_seeker';
    case Company = 'company';

    public static function of(SenderSide $side): self
    {
        return $side === SenderSide::Seeker ? self::JobSeeker : self::Company;
    }
}
