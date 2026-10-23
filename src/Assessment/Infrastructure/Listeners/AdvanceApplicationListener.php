<?php

namespace Src\Assessment\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Assessment\Application\Defaults\AdvanceOnFinalResult;
use Src\Assessment\Domain\Events\ResultFinalized;
use Src\Shared\Infrastructure\Queue\QueueName;

/** One queued step on the `default` queue, retried 3 times with a growing delay, after the result was saved: the automatic advance. A failure here never undoes the result. */
final class AdvanceApplicationListener implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly AdvanceOnFinalResult $advance) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(ResultFinalized $event): void
    {
        $this->advance->handle($event);
    }
}
