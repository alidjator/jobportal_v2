<?php

namespace Src\JobAlert\Application;

use Src\JobAlert\Domain\JobAlert;
use Src\Shared\Application\Applicant\ApplicantContact;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferences;

/**
 * Tells the owner of an alert about the jobs it found: one in-app notification (when the alert has it on; it carries ids only) and one email (when the alert
 * has it on, the address is verified, the user left the `job_alert` email preference on and `$emailAllowed` says the day's cap is not used up).
 */
final readonly class AlertDelivery
{
    /** The most jobs named in one notification or email. */
    public const int MAX_JOBS = 10;

    public function __construct(
        private InAppNotifier $notifier,
        private NotificationPreferences $preferences,
        private JobDirectory $jobs,
        private AlertEmailSender $emails,
    ) {}

    /** Whether the alert would send an email to this person at all, before the day's cap is looked at. */
    public function wantsEmail(JobAlert $alert, ApplicantContact $contact): bool
    {
        return $alert->email && $contact->emailVerified && $this->preferences->isEnabled($alert->userId, JobAlertNotificationType::EMAIL_PREFERENCE, NotificationChannel::Email);
    }

    /**
     * @param  list<int>  $jobIds  the newest matching jobs, newest first (at most `MAX_JOBS` are named)
     * @param  int  $total  how many jobs matched
     * @param  bool  $email  whether to send the email too (decided by the caller with `wantsEmail` and the cap)
     */
    public function deliver(JobAlert $alert, ApplicantContact $contact, array $jobIds, int $total, bool $email): void
    {
        $jobIds = array_slice($jobIds, 0, self::MAX_JOBS);

        if ($alert->inApp) {
            $this->notifier->send([$alert->userId], JobAlertNotificationType::MATCHES, ['alert_id' => $alert->id, 'job_ids' => $jobIds, 'total' => $total]);
        }

        if ($email) {
            $titles = $this->jobs->titles($jobIds);

            $this->emails->send($contact, [
                'alert_name' => $alert->name,
                'job_count' => (string) $total,
                'jobs' => implode("\n\n", array_values(array_filter(array_map(static fn (int $id): ?string => $titles[$id] ?? null, $jobIds)))),
            ]);
        }
    }
}
