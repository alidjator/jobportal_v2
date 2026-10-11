<?php

namespace Src\Company\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Company\Application\Team\ReleaseMemberOfAnonymizedAccount;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\Shared\Infrastructure\Queue\QueueName;

/** Runs on the `default` queue, retried 3 times with a growing delay; running it again changes nothing. */
final class ReleaseMembershipWhenUserAnonymized implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly ReleaseMemberOfAnonymizedAccount $release) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(UserAnonymized $event): void
    {
        $this->release->execute($event->userId, $event->occurredAt());
    }
}
