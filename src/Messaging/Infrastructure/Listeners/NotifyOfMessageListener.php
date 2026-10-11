<?php

namespace Src\Messaging\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Messaging\Application\NotifyOfMessage;
use Src\Messaging\Domain\Events\MessageSent;
use Src\Shared\Infrastructure\Queue\QueueName;

/** Queued, retried 3 times with a growing delay, after the message was saved: a failure here never undoes a send. Messages of a bulk send run on the `bulk` queue. */
final class NotifyOfMessageListener implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly NotifyOfMessage $notify) {}

    public function viaQueue(?MessageSent $event = null): string
    {
        return $event?->bulk === true ? QueueName::Bulk->value : QueueName::Default->value;
    }

    public function viaConnection(?MessageSent $event = null): ?string
    {
        return $event?->bulk === true ? QueueName::Bulk->connection() : null;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(MessageSent $event): void
    {
        $this->notify->inApp($event);
    }
}
