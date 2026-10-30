<?php

namespace Src\Recruitment\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Recruitment\Application\RejectRemainingApplications;
use Src\Recruitment\Domain\Events\RejectRemainingRequested;
use Src\Shared\Infrastructure\Queue\QueueName;

/** Rejects the remaining applications of a closed job one by one, on the `default` queue after the request was accepted. Retried 3 times with a growing delay; one that was rejected is not rejected again (it left its stage). */
final class RejectRemainingListener implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly RejectRemainingApplications $reject) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(RejectRemainingRequested $event): void
    {
        $this->reject->run($event);
    }
}
