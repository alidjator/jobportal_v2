<?php

namespace Src\Recruitment\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Recruitment\Application\NotifyOfPanelAnswer;
use Src\Recruitment\Domain\Events\InterviewPanelAnswered;
use Src\Shared\Infrastructure\Queue\QueueName;

/** Two queued steps on the `default` queue, retried 3 times with a growing delay, after the answer was saved: the in-app notice and the email. */
final class NotifyOfPanelAnswerListener implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly NotifyOfPanelAnswer $notify) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function inApp(InterviewPanelAnswered $event): void
    {
        $this->notify->inApp($event);
    }

    public function email(InterviewPanelAnswered $event): void
    {
        $this->notify->email($event);
    }
}
