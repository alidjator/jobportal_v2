<?php

namespace Src\JobSeeker\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Psr\Log\LoggerInterface;
use Src\JobSeeker\Application\Export\BuildDataExport;
use Src\JobSeeker\Domain\Events\DataExportRequested;
use Src\Shared\Infrastructure\Queue\QueueName;
use Throwable;

/** Runs on the `default` queue, retried 3 times with a growing delay; idempotent. */
final class BuildDataExportWhenRequested implements ShouldQueue
{
    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(private readonly BuildDataExport $build, private readonly LoggerInterface $logger) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(DataExportRequested $event): void
    {
        $this->build->execute($event->exportId);
    }

    /** Retries are exhausted: the request is closed as failed, so the person can ask again at once. */
    public function failed(DataExportRequested $event, Throwable $exception): void
    {
        $this->logger->error('Building a personal data export failed after the last retry.', ['export_id' => $event->exportId, 'user_id' => $event->userId, 'exception' => $exception]);
        $this->build->fail($event->exportId, 'The build failed.');
    }
}
