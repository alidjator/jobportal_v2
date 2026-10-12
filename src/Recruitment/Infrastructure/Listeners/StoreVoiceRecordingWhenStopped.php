<?php

namespace Src\Recruitment\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Psr\Log\LoggerInterface;
use Src\Recruitment\Application\Voice\VoiceRecordingPipeline;
use Src\Recruitment\Domain\Events\VoiceRecordingStopped;
use Src\Shared\Infrastructure\Queue\QueueName;
use Throwable;

/**
 * Fetches the file of a phone call recording from the voice server and stores it. Runs on the `bulk` queue (a long call is tens of megabytes); the voice
 * server needs a moment to finish the file, so it is tried again several times before the recording is marked failed.
 */
final class StoreVoiceRecordingWhenStopped implements ShouldQueue
{
    public int $tries = 6;

    public int $timeout = 540;

    public function __construct(private readonly VoiceRecordingPipeline $pipeline, private readonly LoggerInterface $logger) {}

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
        return [5, 15, 60, 300, 900];
    }

    public function handle(VoiceRecordingStopped $event): void
    {
        $this->pipeline->store($event->recordingId);
    }

    public function failed(VoiceRecordingStopped $event, Throwable $exception): void
    {
        $this->logger->error('A call recording could not be stored after the last retry.', ['recording_id' => $event->recordingId, 'exception' => $exception]);
        $this->pipeline->fail($event->recordingId);
    }
}
