<?php

namespace Src\JobInvitation\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\JobInvitation\Application\MarkEmailInvitationApplied;
use Src\Recruitment\Domain\Events\ApplicationSubmitted;
use Src\Shared\Infrastructure\Queue\QueueName;

/** Runs on the `default` queue, retried 3 times with a growing delay; idempotent. */
final class MarkEmailInvitationAppliedWhenApplicationSubmitted implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly MarkEmailInvitationApplied $mark) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(ApplicationSubmitted $event): void
    {
        $this->mark->execute($event->applicationId, $event->jobId);
    }
}
