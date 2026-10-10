<?php

namespace Src\Newsletter\Infrastructure\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Src\Newsletter\Application\DispatchIssue;
use Src\Shared\Infrastructure\Queue\QueueName;

/** Queues the deliveries of a sending issue on the `bulk` queue. Running it again is safe: a delivery exists once per subscriber. */
final class DispatchNewsletterIssueJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $issueId)
    {
        $this->onQueue(QueueName::Bulk->value);
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(DispatchIssue $dispatch): void
    {
        $dispatch->execute($this->issueId);
    }
}
