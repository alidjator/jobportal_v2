<?php

namespace Src\Recruitment\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Recruitment\Application\NotifyOfInterviewChange;
use Src\Recruitment\Domain\Events\InterviewChanged;
use Src\Shared\Infrastructure\Queue\QueueName;

/** Two queued steps on the `default` queue, retried 3 times with a growing delay, after the change was saved: the in-app notifications and the email. A failure here never undoes the change. */
final class NotifyOfInterviewChangeListener implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly NotifyOfInterviewChange $notify) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function inApp(InterviewChanged $event): void
    {
        $this->notify->inApp($event);
    }

    public function email(InterviewChanged $event): void
    {
        $this->notify->email($event);
    }
}
