<?php

namespace Src\Newsletter\Infrastructure\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Src\Newsletter\Application\DeliverNewsletter;
use Src\Shared\Infrastructure\Queue\QueueName;
use Throwable;

/** Sends one issue to one subscriber on the `bulk` queue, retried 3 times with a growing delay; after the last failure the delivery is marked failed. */
final class SendNewsletterDeliveryJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $deliveryId)
    {
        $this->onQueue(QueueName::Bulk->value);
        $this->onConnection(QueueName::Bulk->connection());
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(DeliverNewsletter $deliver): void
    {
        $deliver->execute($this->deliveryId);
    }

    public function failed(Throwable $exception): void
    {
        app(DeliverNewsletter::class)->failed($this->deliveryId);
    }
}
