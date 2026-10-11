<?php

namespace Src\JobInvitation\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\JobInvitation\Domain\InvitationRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * The daily cap on the invitations a company sends (the calendar day in Jakarta time). `assertRoom` runs inside the transaction that writes the invitation and
 * locks the company's row first, so two requests of one company cannot both count the same room.
 */
final readonly class InvitationQuota
{
    private const string ZONE = 'Asia/Jakarta';

    public function __construct(private InvitationRepository $invitations, private Settings $settings, private Clock $clock, private CompanyDirectory $companies) {}

    /**
     * Call it inside the transaction that writes the invitation.
     *
     * @throws TooManyAttempts `invitation-limit`
     */
    public function assertRoom(int $companyId): void
    {
        $this->companies->lock($companyId);

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
