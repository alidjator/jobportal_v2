<?php

namespace Src\Recruitment\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Recruitment\Application\ApplicationNotificationType;
use Src\Recruitment\Domain\Events\JobFilled;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Infrastructure\Queue\QueueName;

/**
 * Tells the team members a job is assigned to that its hires reached its vacancies (in-app only, no email), so they can close it and reject the rest. Runs on the
 * `default` queue, retried 3 times with a growing delay, after the hire was saved: a failure here never undoes a hire. The notification holds the job and the numbers.
 */
final class NotifyAssigneesOfJobFilled implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly JobDirectory $jobs, private readonly InAppNotifier $notifier) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(JobFilled $event): void
    {
        $this->notifier->send($this->jobs->notificationRecipients($event->jobId), ApplicationNotificationType::JOB_FILLED, [
            'job_id' => $event->jobId,
            'job_title' => $this->jobs->titles([$event->jobId])[$event->jobId] ?? '',
            'hires' => $event->hires,
            'vacancies' => $event->vacancies,
        ]);
    }
}
