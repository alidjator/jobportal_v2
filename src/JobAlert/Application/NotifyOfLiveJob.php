<?php

namespace Src\JobAlert\Application;

use DateTimeZone;
use Src\JobAlert\Domain\AlertDeliveryRepository;
use Src\JobAlert\Domain\AlertFrequency;
use Src\JobAlert\Domain\JobAlert;
use Src\JobAlert\Domain\JobAlertRepository;
use Src\Shared\Application\Applicant\ApplicantContact;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Job\JobMatcher;

/**
 * A job went live: every immediate alert that matches it, by the rule of the public search and only when the job first went live after the alert started,
 * tells its owner, once per job. The in-app notification is always written; the email is limited to 5 per alert per Jakarta day. Accounts that are not
 * active are skipped. Safe to run again for the same job.
 */
final readonly class NotifyOfLiveJob
{
    private const int BATCH = 200;

    public function __construct(
        private JobAlertRepository $alerts,
        private AlertDeliveryRepository $deliveries,
        private JobMatcher $matcher,
        private ApplicantContacts $contacts,
        private AlertDelivery $delivery,
        private Clock $clock,
    ) {}

    /** @return int how many alerts told their owner */
    public function execute(int $jobId): int
    {
        $day = $this->clock->now()->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y-m-d');
        $told = 0;
        $afterId = 0;

        while (($batch = $this->alerts->activeAfter(AlertFrequency::Immediate, $afterId, self::BATCH)) !== []) {
            foreach ($batch as $alert) {
                $afterId = (int) $alert->id;
                $told += $this->tell($alert, $jobId, $day) ? 1 : 0;
            }
        }

        return $told;
    }

    private function tell(JobAlert $alert, int $jobId, string $day): bool
    {
        $matches = $this->matcher->match($alert->criteria->toArray(), $alert->lastRunAt, null, $jobId, 1);

        if ($matches->total === 0) {
            return false;
        }

        $contact = $this->contacts->contact($alert->userId);

        if (! $contact instanceof ApplicantContact || ! $contact->active || ! $this->deliveries->claim((int) $alert->id, $jobId)) {
            return false;
        }

        $email = $this->delivery->wantsEmail($alert, $contact) && $this->alerts->reserveEmail((int) $alert->id, $day);
        $this->delivery->deliver($alert, $contact, [$jobId], 1, $email);

        return true;
    }
}
