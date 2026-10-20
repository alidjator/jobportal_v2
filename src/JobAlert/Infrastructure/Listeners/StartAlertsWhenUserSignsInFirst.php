<?php

namespace Src\JobAlert\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Identity\Domain\AccountScope;
use Src\Identity\Domain\Events\SignedIn;
use Src\JobAlert\Application\StartAlertsAtFirstSignIn;
use Src\Shared\Infrastructure\Queue\QueueName;

/** Runs on the `default` queue, retried 3 times with a growing delay; idempotent (the cursor only moves forward). */
final class StartAlertsWhenUserSignsInFirst implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly StartAlertsAtFirstSignIn $start) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(SignedIn $event): void
    {
        if ($event->scope !== AccountScope::User || ! $event->firstSignIn) {
            return;
        }

        $this->start->execute($event->accountId, $event->occurredAt());
    }
}
