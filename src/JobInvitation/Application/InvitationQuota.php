<?php

namespace Src\JobInvitation\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\JobInvitation\Domain\InvitationRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * The daily cap on the invitations a company sends (the calendar day in Jakarta time). The cap is read before the invitation is written, as the chat quota is,
 * so two requests at the very same moment may overshoot it by one.
 */
final readonly class InvitationQuota
{
    private const string ZONE = 'Asia/Jakarta';

    public function __construct(private InvitationRepository $invitations, private Settings $settings, private Clock $clock) {}

    /** @throws TooManyAttempts `invitation-limit` */
    public function assertRoom(int $companyId): void
    {
        $cap = $this->settings->integer(InvitationSettings::MAX_PER_DAY, $companyId);
        $start = $this->dayStart();

        if ($this->invitations->countOfCompanySince($companyId, $start) >= $cap) {
            throw new TooManyAttempts(
                'Your company reached its daily limit of job invitations. Try again tomorrow.',
                max(1, $start->modify('+1 day')->getTimestamp() - $this->clock->now()->getTimestamp()),
                'invitation-limit',
            );
        }
    }

    /** The start of today in Jakarta time, as a UTC moment. */
    private function dayStart(): DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new DateTimeZone(self::ZONE))->setTime(0, 0)->setTimezone(new DateTimeZone('UTC'));
    }
}
