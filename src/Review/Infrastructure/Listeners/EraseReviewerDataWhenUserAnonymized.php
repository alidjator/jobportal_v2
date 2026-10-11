<?php

namespace Src\Review\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Psr\Log\LoggerInterface;
use Src\Identity\Domain\Events\UserAnonymized;
use Src\Review\Application\EraseReviewerData;
use Src\Shared\Application\Accounts\ErasureRunner;
use Src\Shared\Infrastructure\Queue\QueueName;
use Throwable;

/** Runs on the `default` queue, retried 3 times with a growing delay; idempotent. */
final class EraseReviewerDataWhenUserAnonymized implements ShouldQueue
{
    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(private readonly EraseReviewerData $erase, private readonly ErasureRunner $runner, private readonly LoggerInterface $logger) {}

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
        $this->runner->run($this->erase, $event->userId);
    }

    /** Retries are exhausted: the data stays until the daily reconciliation (`identity:reconcile-erasure`) runs the step again. */
    public function failed(UserAnonymized $event, Throwable $exception): void
    {
        $this->logger->error('Erasure of personal data failed after the last retry.', [
            'step' => $this->erase->name(),
            'user_id' => $event->userId,
            'exception' => $exception,
        ]);
    }
}
