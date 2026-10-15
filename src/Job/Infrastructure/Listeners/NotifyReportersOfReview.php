<?php

namespace Src\Job\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Job\Application\JobNotificationType;
use Src\Job\Domain\Events\JobReportsClosed;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Infrastructure\Queue\QueueName;

/** Tells the job seekers who reported a job that the report was reviewed. The notice carries the job id and nothing about the outcome. Runs on the `default` queue, retried 3 times. */
final class NotifyReportersOfReview implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly InAppNotifier $notifier) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(JobReportsClosed $event): void
    {
        $this->notifier->send($event->reporterIds, JobNotificationType::REPORT_REVIEWED, ['job_id' => $event->jobId]);
    }
}
