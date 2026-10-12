<?php

namespace Src\Recruitment\Application\Voice;

use DateTimeImmutable;
use DateTimeZone;
use Src\Recruitment\Application\InterviewSettings;
use Src\Recruitment\Domain\Voice\CallRepository;
use Src\Shared\Application\Settings\Settings;

/**
 * The guards on phone calls (a guard, not billing): how long one call may last and how many call minutes a company has left in the calendar month (Jakarta
 * time). A call in progress counts with the longest it may still last, so two calls started together cannot both use up the last minutes.
 */
final readonly class VoiceLimits
{
    private const string ZONE = 'Asia/Jakarta';

    public function __construct(private Settings $settings, private CallRepository $calls) {}

    public function maxCallSeconds(int $companyId): int
    {
        return $this->settings->integer(InterviewSettings::VOICE_MAX_CALL_MINUTES, $companyId) * 60;
    }

    /** The seconds the company can still call this month; 0 or less means used up. */
    public function remainingSeconds(int $companyId, DateTimeImmutable $now): int
    {
        $zone = new DateTimeZone(self::ZONE);
        $from = $now->setTimezone($zone)->modify('first day of this month')->setTime(0, 0);
        $until = $from->modify('+1 month');

        return $this->settings->integer(InterviewSettings::VOICE_MONTHLY_MINUTES, $companyId) * 60
            - $this->calls->secondsUsed($companyId, $from, $until, $this->maxCallSeconds($companyId));
    }

    /** How long a call that starts now may last: the cap of one call, or what is left of the month if that is less. */
    public function allowedSeconds(int $companyId, DateTimeImmutable $now): int
    {
        return max(0, min($this->maxCallSeconds($companyId), $this->remainingSeconds($companyId, $now)));
    }
}
