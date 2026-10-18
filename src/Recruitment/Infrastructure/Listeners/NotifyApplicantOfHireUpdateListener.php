<?php

namespace Src\Recruitment\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Recruitment\Application\NotifyApplicantOfHireUpdate;
use Src\Recruitment\Domain\Events\ApplicationHireUpdated;
use Src\Shared\Infrastructure\Queue\QueueName;

/**
 * Two queued steps on the `default` queue, retried 3 times with a growing delay, after the change was saved: the in-app notification and the email. They are
 * separate listeners, so a retry of one never repeats the other, and a failure here never undoes the change.
 */
final class NotifyApplicantOfHireUpdateListener implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly NotifyApplicantOfHireUpdate $notify) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function inApp(ApplicationHireUpdated $event): void
    {
        $this->notify->inApp($event);
    }

    public function email(ApplicationHireUpdated $event): void
    {
        $this->notify->email($event);
    }
}
