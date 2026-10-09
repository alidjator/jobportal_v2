<?php

namespace Src\JobAlert\Application;

use DateTimeImmutable;
use DateTimeZone;
use Src\JobAlert\Domain\AlertFrequency;
use Src\JobAlert\Domain\JobAlert;
use Src\JobAlert\Domain\JobAlertRepository;
use Src\Shared\Application\Applicant\ApplicantContact;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Job\JobMatcher;

/**
 * The daily (08:00) and weekly (Monday 08:00, Jakarta time) summaries. An alert is due when its cursor is before the latest slot that has come; the summary
 * covers the jobs that first went live after the cursor, up to the slot, and moves the cursor to the slot, so running it twice, or late, never repeats or skips
 * a job. Nothing is sent when no job matches. The alerts of accounts that are not active are moved on without sending.
 */
final readonly class SendJobAlertDigests
{
    private const string ZONE = 'Asia/Jakarta';

    private const int BATCH = 200;

    public function __construct(
        private JobAlertRepository $alerts,
        private JobMatcher $matcher,
        private ApplicantContacts $contacts,
        private AlertDelivery $delivery,
        private Clock $clock,
    ) {}

    /** @return int how many summaries were sent */
    public function execute(): int
    {
        $now = $this->clock->now();

        return $this->run(AlertFrequency::Daily, $this->dailySlot($now)) + $this->run(AlertFrequency::Weekly, $this->weeklySlot($now));
    }

    private function run(AlertFrequency $frequency, DateTimeImmutable $slot): int
    {
        $sent = 0;
        $afterId = 0;

        while (($batch = $this->alerts->activeAfter($frequency, $afterId, self::BATCH)) !== []) {
            foreach ($batch as $alert) {
                $afterId = (int) $alert->id;

                if ($alert->lastRunAt < $slot) {
                    $sent += $this->summarise($alert, $slot) ? 1 : 0;
                }
            }
        }

        return $sent;
    }

    private function summarise(JobAlert $alert, DateTimeImmutable $slot): bool
    {
        $matches = $this->matcher->match($alert->criteria->toArray(), $alert->lastRunAt, $slot, null, AlertDelivery::MAX_JOBS);
        $contact = $this->contacts->contact($alert->userId);
        $sent = false;

        if ($matches->total > 0 && $contact instanceof ApplicantContact && $contact->active) {
            $this->delivery->deliver($alert, $contact, $matches->jobIds, $matches->total, $this->delivery->wantsEmail($alert, $contact));
            $sent = true;
        }

        $this->alerts->update($alert->ranUntil($slot));

        return $sent;
    }

    /** The latest 08:00 in Jakarta that has come, as a UTC moment. */
    private function dailySlot(DateTimeImmutable $now): DateTimeImmutable
    {
        $local = $now->setTimezone(new DateTimeZone(self::ZONE));
        $slot = $local->setTime(8, 0);

        return ($slot > $local ? $slot->modify('-1 day') : $slot)->setTimezone(new DateTimeZone('UTC'));
    }

    /** The latest Monday 08:00 in Jakarta that has come, as a UTC moment. */
    private function weeklySlot(DateTimeImmutable $now): DateTimeImmutable
    {
        $slot = $this->dailySlot($now)->setTimezone(new DateTimeZone(self::ZONE));
        $back = ((int) $slot->format('N') + 6) % 7;

        return $slot->modify("-{$back} days")->setTimezone(new DateTimeZone('UTC'));
    }
}
