<?php

namespace Src\Assessment\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Assessment\Application\Attempts\NotifyOfAttemptSubmission;
use Src\Assessment\Domain\Events\AttemptSubmitted;
use Src\Shared\Infrastructure\Queue\QueueName;

/** A queued step on the `default` queue, retried 3 times with a growing delay, after the submission was saved. A failure here never undoes the submission. */
final class NotifyOfAttemptSubmissionListener implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly NotifyOfAttemptSubmission $notify) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(AttemptSubmitted $event): void
    {
        $this->notify->inApp($event);
    }
}
