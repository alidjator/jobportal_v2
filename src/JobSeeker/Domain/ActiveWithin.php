<?php

namespace Src\JobSeeker\Domain;

use DateTimeImmutable;

/**
 * How recently a job seeker was active, rounded up to a bucket: companies and the job seeker see this, never the date. A day counts as inside a bucket
 * when it is at most that many days ago (a Jakarta date compared with today's Jakarta date).
 */
enum ActiveWithin: string
{
    case Week = '7d';
    case Month = '30d';
    case Quarter = '90d';
    case Older = 'older';

    /** Null when the account was never seen active (an imported account that has not signed in). */
    public static function of(?DateTimeImmutable $lastActiveOn, DateTimeImmutable $today): ?self
    {
        if ($lastActiveOn === null) {
            return null;
        }

        $days = (int) $lastActiveOn->diff($today)->format('%r%a');

        return match (true) {
            $days <= 7 => self::Week,
            $days <= 30 => self::Month,
            $days <= 90 => self::Quarter,
            default => self::Older,
        };
    }
}
