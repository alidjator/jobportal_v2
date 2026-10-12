<?php

namespace Src\Recruitment\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Psr\Log\LoggerInterface;
use Src\Recruitment\Application\Video\RecordingPipeline;
use Src\Recruitment\Domain\Events\RecordingStopped;
use Src\Shared\Infrastructure\Queue\QueueName;
use Throwable;

/** Asks the converter to make one file of the recording. Runs on the `default` queue, retried 3 times with a growing delay; a recording that cannot be converted is marked failed. */
final class ConvertRecordingWhenStopped implements ShouldQueue
{
    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(private readonly RecordingPipeline $pipeline, private readonly LoggerInterface $logger) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(RecordingStopped $event): void
    {
        $this->pipeline->requestConversion($event->recordingId);
    }

    public function failed(RecordingStopped $event, Throwable $exception): void
    {
        $this->logger->error('A recording could not be sent to the converter after the last retry.', ['recording_id' => $event->recordingId, 'exception' => $exception]);
        $this->pipeline->fail($event->recordingId);
    }
}
