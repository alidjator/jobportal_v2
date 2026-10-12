<?php

namespace Src\Assessment\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Assessment\Application\Delivery\NotifyOfInvitationDeclined;
use Src\Assessment\Domain\Events\InvitationDeclined;
use Src\Shared\Infrastructure\Queue\QueueName;

/** A queued step on the `default` queue, retried 3 times with a growing delay, after the refusal was saved. A failure here never undoes it. */
final class NotifyOfInvitationDeclinedListener implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly NotifyOfInvitationDeclined $notify) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(InvitationDeclined $event): void
    {
        $this->notify->inApp($event);
    }
}
