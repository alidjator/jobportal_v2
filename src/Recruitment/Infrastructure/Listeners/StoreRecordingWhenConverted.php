<?php

namespace Src\Recruitment\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Psr\Log\LoggerInterface;
use Src\Recruitment\Application\Video\RecordingPipeline;
use Src\Recruitment\Domain\Events\RecordingConverted;
use Src\Shared\Infrastructure\Queue\QueueName;
use Throwable;

/** Moves the converted file to the private disk. Runs on the `bulk` queue (it may take minutes), retried 3 times with a growing delay; a recording that cannot be stored is marked failed. */
final class StoreRecordingWhenConverted implements ShouldQueue
{
    public int $tries = 3;

    public int $timeout = 540;

    public function __construct(private readonly RecordingPipeline $pipeline, private readonly LoggerInterface $logger) {}

    public function viaQueue(): string
    {
        return QueueName::Bulk->value;
    }

    public function viaConnection(): ?string
    {
        return QueueName::Bulk->connection();
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(RecordingConverted $event): void
    {
        $this->pipeline->store($event->recordingId);
    }

    public function failed(RecordingConverted $event, Throwable $exception): void
    {
        $this->logger->error('A recording could not be stored after the last retry.', ['recording_id' => $event->recordingId, 'exception' => $exception]);
        $this->pipeline->fail($event->recordingId);
    }
}
