<?php

namespace Src\Messaging\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\Messaging\Domain\MessageRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/** The daily cap on the messages a company's members send (the calendar day in Jakarta time; bulk messages count per recipient). */
final readonly class DailyMessageQuota
{
    private const string ZONE = 'Asia/Jakarta';

    public function __construct(private MessageRepository $messages, private Settings $settings, private Clock $clock, private CompanyDirectory $companies) {}

    public function remaining(int $companyId): int
    {
        $cap = $this->settings->integer(MessagingSettings::MAX_DAILY_COMPANY_MESSAGES, $companyId);

        return max(0, $cap - $this->messages->sentByCompanySince($companyId, $this->dayStart()));
    }

    /**
     * A plain check, for an early refusal before a transaction. It does not hold the room: use `lockAndAssertRoom` where the message is written.
     *
     * @throws TooManyAttempts `daily-limit`
     */
    public function assertRoom(int $companyId): void
    {
        if ($this->remaining($companyId) < 1) {
            $nextDay = $this->dayStart()->modify('+1 day');

            throw new TooManyAttempts(
                'Your company reached its daily limit of chat messages. Try again tomorrow.',
                max(1, $nextDay->getTimestamp() - $this->clock->now()->getTimestamp()),
                'daily-limit',
            );
        }
    }

    /**
     * Locks the company's row, then checks the room. Call it inside the transaction that writes the message, so two requests of one company cannot both count the same room.
     *
     * @throws TooManyAttempts `daily-limit`
     */
    public function lockAndAssertRoom(int $companyId): void
    {
        $this->companies->lock($companyId);
        $this->assertRoom($companyId);
    }

    /** The start of today in Jakarta time, as a UTC moment. */
    private function dayStart(): DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new DateTimeZone(self::ZONE))->setTime(0, 0)->setTimezone(new DateTimeZone('UTC'));
    }
}
