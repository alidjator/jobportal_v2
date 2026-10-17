<?php

namespace Src\Recruitment\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Recruitment\Application\NotifyOfReviews;
use Src\Recruitment\Domain\Events\ReviewCompleted;
use Src\Recruitment\Domain\Events\ReviewRequested;
use Src\Shared\Infrastructure\Queue\QueueName;

/** Queued steps on the `default` queue, retried 3 times with a growing delay, after the change was saved: the notice and the email of a new request, and the notice of a completed one. A failure here never undoes the change. */
final class NotifyOfReviewsListener implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly NotifyOfReviews $notify) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function requestedInApp(ReviewRequested $event): void
    {
        $this->notify->requestedInApp($event);
    }

    public function requestedEmail(ReviewRequested $event): void
    {
        $this->notify->requestedEmail($event);
    }

    public function completed(ReviewCompleted $event): void
    {
        $this->notify->completed($event);
    }
}
