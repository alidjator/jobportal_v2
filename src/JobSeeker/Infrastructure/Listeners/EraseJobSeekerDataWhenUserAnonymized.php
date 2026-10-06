<?php

namespace Src\JobSeeker\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\JobSeeker\Application\EraseJobSeekerData;
use Src\Shared\Infrastructure\Queue\QueueName;

/** Runs on the `default` queue, retried 3 times with a growing delay; idempotent. */
final class EraseJobSeekerDataWhenUserAnonymized implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly EraseJobSeekerData $erase) {}

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
        $this->erase->execute($event->userId);
    }
}
