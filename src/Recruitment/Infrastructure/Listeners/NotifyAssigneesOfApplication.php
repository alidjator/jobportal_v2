<?php

namespace Src\Recruitment\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Recruitment\Application\ApplicationNotificationType;
use Src\Recruitment\Domain\Events\ApplicationSubmitted;
use Src\Shared\Application\Company\CompanyTeam;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Infrastructure\Queue\QueueName;

/**
 * Tells the team members a job is assigned to that an application arrived (not its hiring managers: they see it later) (in-app only, no email). Runs on the `default` queue, retried 3 times with a
 * growing delay, after the application was saved: a failure here never undoes an application. The notification carries ids only, never the applicant's name.
 */
final class NotifyAssigneesOfApplication implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly JobDirectory $jobs, private readonly CompanyTeam $team, private readonly InAppNotifier $notifier) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(ApplicationSubmitted $event): void
    {
        // A hiring manager sees an application only from the stage boundary, so a new one is not announced to them (a member who sees every job still hears of it).
        $bound = array_diff($this->jobs->hiringManagerUserIds($event->jobId), $this->team->viewAllUserIds($event->companyId));
        $recipients = array_values(array_diff($this->jobs->notificationRecipients($event->jobId), $bound));

        $this->notifier->send($recipients, ApplicationNotificationType::RECEIVED, [
            'application_id' => $event->applicationId,
            'job_id' => $event->jobId,
        ]);
    }
}
